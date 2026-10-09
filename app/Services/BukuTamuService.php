<?php

namespace App\Services;

use App\Jobs\KirimKonfirmasiTamuJob;
use App\Jobs\KirimNotulensiTamuJob;
use App\Models\KegiatanTamu;
use App\Models\Tamu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * BUKU TAMU — satu pintu untuk pengisian dari halaman publik.
 *
 * Halaman tamu adalah SATU-SATUNYA halaman sistem yang bisa diakses tanpa login,
 * jadi seluruh pemeriksaan ada di sini, bukan di view:
 *  - kegiatan harus dibuka & belum kedaluwarsa;
 *  - nomor urut diambil di dalam transaksi berkunci — di acara, puluhan tamu
 *    memindai QR dan menekan kirim hampir bersamaan;
 *  - tanda tangan divalidasi isinya, bukan hanya ada-tidaknya.
 */
class BukuTamuService
{
    /** Ukuran maksimal data tanda tangan yang diterima (base64), ±1,5 MB. */
    public const MAKS_TTD_BYTE = 1_500_000;

    /** Panjang minimal data PNG agar bukan kanvas kosong / satu titik. */
    private const MIN_TTD_BYTE = 1_500;

    /**
     * Jeda antar email dalam antrean (detik).
     *
     * Hostinger Business Email membatasi jumlah kiriman per jam. Mengirim 50
     * notulensi serentak berisiko ditolak massal — dan kiriman yang ditolak
     * tetap memakan kuota. 6 detik ≈ 600 email/jam, aman untuk acara terbesar
     * sekalipun, dan 50 tamu tetap tuntas dalam ±5 menit.
     */
    public const JEDA_KIRIM_DETIK = 6;

    /**
     * Setelah berapa menit baris "menunggu" dianggap macet dan boleh diantre ulang.
     *
     * Harus lebih panjang daripada waktu antrean terburuk (100 tamu × 6 detik
     * = 10 menit) agar klik kirim kedua tidak menggandakan email yang masih
     * dalam perjalanan.
     */
    private const MENIT_MACET = 30;

    // ══════════════════════════════════════════════════════════════════════
    // Kegiatan
    // ══════════════════════════════════════════════════════════════════════

    public function buatKegiatan(array $d, ?int $userId = null): KegiatanTamu
    {
        return KegiatanTamu::create([
            'nama'            => $d['nama'],
            'deskripsi'       => $d['deskripsi'] ?? null,
            'tanggal'         => $d['tanggal'],
            'tanggal_selesai' => $d['tanggal_selesai'] ?? null,
            'lokasi'          => $d['lokasi'] ?? null,
            'penyelenggara'   => $d['penyelenggara'] ?? null,
            'token'           => KegiatanTamu::tokenBaru(),
            'is_dibuka'       => true,
            // Tanpa batas waktu, tautan yang beredar di grup WA masih bisa diisi
            // berbulan-bulan kemudian. Bawaannya: tutup sehari setelah acara.
            'dibuka_sampai'   => $d['dibuka_sampai']
                ?? \Carbon\Carbon::parse($d['tanggal_selesai'] ?? $d['tanggal'])->endOfDay()->addDay(),
            'dibuat_oleh'     => $userId,
        ]);
    }

    /**
     * Mengapa buku tamu ini tidak bisa diisi (null = boleh diisi).
     * Pesannya langsung ditampilkan ke tamu, jadi ditulis untuk orang luar.
     */
    public function alasanTertutup(KegiatanTamu $kegiatan): ?string
    {
        if (!$kegiatan->is_dibuka) {
            return 'Buku tamu kegiatan ini sudah ditutup.';
        }
        if ($kegiatan->dibuka_sampai && TimezoneHelper::now()->gt($kegiatan->dibuka_sampai)) {
            return 'Masa pengisian buku tamu ini sudah berakhir pada '
                . $kegiatan->dibuka_sampai->locale('id')->isoFormat('D MMMM YYYY, HH:mm') . '.';
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pengisian oleh tamu
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Simpan satu tamu. Nomor urut diberikan sistem.
     *
     * @param  array  $d  nama, asal, pekerjaan, email, tanda_tangan (data-URL PNG)
     * @throws \DomainException
     */
    public function simpanTamu(KegiatanTamu $kegiatan, array $d, ?Request $request = null): Tamu
    {
        if ($alasan = $this->alasanTertutup($kegiatan)) {
            throw new \DomainException($alasan);
        }

        $relatif = $this->simpanTandaTangan($d['tanda_tangan'], $kegiatan);

        try {
            $tamu = DB::transaction(function () use ($kegiatan, $d, $request, $relatif) {
                // Kunci baris kegiatannya, bukan tabel tamu: dua tamu yang menekan
                // kirim pada detik yang sama tidak boleh mendapat nomor yang sama.
                $terkunci = KegiatanTamu::whereKey($kegiatan->id)->lockForUpdate()->first();

                $nomor = (int) Tamu::where('kegiatan_tamu_id', $terkunci->id)->max('nomor_urut') + 1;

                return Tamu::create([
                    'kegiatan_tamu_id' => $terkunci->id,
                    'nomor_urut'       => $nomor,
                    'nama'             => $this->rapikan($d['nama']),
                    'asal'             => $this->rapikan($d['asal']),
                    'pekerjaan'        => $this->rapikan($d['pekerjaan']),
                    'email'            => Str::lower(trim($d['email'])),
                    'tanda_tangan'     => $relatif,
                    'ip'               => $request?->ip(),
                    'perangkat'        => Str::limit((string) $request?->userAgent(), 240, ''),
                    'diisi_pada'       => TimezoneHelper::now(),
                ]);
            });
        } catch (\Throwable $e) {
            // Gagal menyimpan baris → jangan tinggalkan berkas tanda tangan menggantung.
            Storage::disk('public')->delete($relatif);
            throw $e;
        }

        // Konfirmasi dikirim SETELAH transaksi selesai, lewat antrean. Tamu
        // tidak boleh menunggu SMTP: di lokasi acara jaringannya lemah, dan
        // email gagal tidak boleh membatalkan pencatatan kehadirannya.
        if ($this->emailSiap()) {
            KirimKonfirmasiTamuJob::dispatch($tamu->id)->afterCommit();
        }

        return $tamu;
    }

    /**
     * Simpan tanda tangan dari data-URL kanvas menjadi berkas PNG.
     *
     * @throws \DomainException bila bukan PNG, terlalu besar, atau nyaris kosong
     */
    public function simpanTandaTangan(string $dataUrl, KegiatanTamu $kegiatan): string
    {
        if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            throw new \DomainException('Tanda tangan tidak terbaca. Mohon tanda tangani ulang.');
        }
        if (strlen($dataUrl) > self::MAKS_TTD_BYTE) {
            throw new \DomainException('Tanda tangan terlalu besar. Mohon tanda tangani ulang.');
        }

        $biner = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($biner === false || strlen($biner) < self::MIN_TTD_BYTE) {
            // Kanvas kosong atau satu titik menghasilkan PNG sangat kecil.
            throw new \DomainException('Tanda tangan belum terisi. Mohon tanda tangani pada kotak yang tersedia.');
        }

        $nama = 'buku-tamu/' . $kegiatan->id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($nama, $biner);

        return $nama;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Notulensi & pengiriman email
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Apakah sistem benar-benar bisa mengirim email?
     *
     * Ini bukan pemeriksaan basa-basi. Tanpa MAIL_* di `.env`, Laravel jatuh ke
     * pengirim `log`: `Mail::send()` SUKSES, tidak ada kesalahan apa pun, dan
     * setiap tamu akan ditandai "terkirim" padahal tidak ada satu pun email
     * yang keluar. Jadi pengiriman diblokir sejak awal, bukan dibiarkan
     * "berhasil" secara palsu.
     *
     * @return string|null  null = siap; selain itu alasan untuk superadmin
     */
    public function alasanEmailBelumSiap(): ?string
    {
        $pengirim = config('mail.default');

        if (in_array($pengirim, ['log', 'array', null], true)) {
            return 'Pengiriman email belum diaktifkan di server (MAIL_MAILER masih "' . ($pengirim ?: 'kosong')
                . '"). Isi pengaturan SMTP di server terlebih dahulu, lalu muat ulang kontainer aplikasi.';
        }

        if ($pengirim === 'smtp') {
            if (blank(config('mail.mailers.smtp.host'))) {
                return 'Alamat server SMTP (MAIL_HOST) belum diisi di server.';
            }
            if (blank(config('mail.mailers.smtp.username')) || blank(config('mail.mailers.smtp.password'))) {
                return 'Akun SMTP (MAIL_USERNAME / MAIL_PASSWORD) belum diisi di server.';
            }
        }

        if (blank(config('mail.from.address'))) {
            return 'Alamat pengirim (MAIL_FROM_ADDRESS) belum diisi di server.';
        }

        return null;
    }

    public function emailSiap(): bool
    {
        return $this->alasanEmailBelumSiap() === null;
    }

    /** Simpan/perbarui naskah notulensi. Tidak mengirim apa pun. */
    public function simpanNotulensi(KegiatanTamu $kegiatan, ?string $naskah): KegiatanTamu
    {
        $kegiatan->update(['notulensi' => filled($naskah) ? trim($naskah) : null]);

        return $kegiatan->refresh();
    }

    /**
     * Antre pengiriman notulensi ke alamat email para tamu.
     *
     * @param  string  $mode  'belum' = semua yang belum pernah terkirim ·
     *                        'gagal' = hanya yang gagal ·
     *                        'semua' = kirim ulang ke semua (naskah direvisi)
     * @return array{dikirim:int,duplikat:int,dilewati:int}
     * @throws \DomainException
     */
    public function kirimNotulensi(KegiatanTamu $kegiatan, string $mode = 'belum', ?int $userId = null): array
    {
        if ($alasan = $this->alasanEmailBelumSiap()) {
            throw new \DomainException($alasan);
        }
        if (blank($kegiatan->notulensi)) {
            throw new \DomainException('Notulensi belum ditulis. Simpan naskahnya dulu sebelum dikirim.');
        }

        $semua = $kegiatan->tamu()->get();
        if ($semua->isEmpty()) {
            throw new \DomainException('Belum ada tamu yang mengisi buku tamu kegiatan ini.');
        }

        $kandidat = match ($mode) {
            'gagal' => $semua->where('email_status', 'gagal'),
            'semua' => $semua,
            // "menunggu" TIDAK ikut diantre ulang selama masih segar: kalau
            // superadmin menekan kirim dua kali sementara antrean belum habis,
            // tamu yang sama akan menerima dua email. Yang sudah menggantung
            // lebih dari MENIT_MACET (pekerja mati, antrean dibersihkan) tetap
            // boleh diulang, supaya tidak ada tamu yang terkunci selamanya.
            default => $semua->filter(fn ($t) => in_array($t->email_status, ['belum', 'gagal', 'duplikat'], true)
                || ($t->email_status === 'menunggu'
                    && $t->updated_at
                    && $t->updated_at->lt(now()->subMinutes(self::MENIT_MACET)))),
        };

        if ($kandidat->isEmpty()) {
            if ($mode === 'gagal') {
                throw new \DomainException('Tidak ada pengiriman yang gagal.');
            }
            // Bedakan "sudah selesai semua" dari "masih dalam perjalanan" —
            // kalau disamakan, superadmin mengira pengirimannya tidak jalan.
            throw new \DomainException($semua->where('email_status', 'menunggu')->isNotEmpty()
                ? 'Masih ada ' . $semua->where('email_status', 'menunggu')->count()
                    . ' email dalam antrean pengiriman. Tunggu beberapa menit, lalu segarkan halaman ini.'
                : 'Semua tamu sudah menerima notulensi. Gunakan "kirim ulang ke semua" bila naskahnya direvisi.');
        }

        // Alamat yang sudah benar-benar menerima — jangan dikirimi dua kali,
        // kecuali memang kirim-ulang-semua.
        $sudahTerkirim = $mode === 'semua'
            ? collect()
            : $semua->where('email_status', 'terkirim')->pluck('email')->map(fn ($e) => Str::lower($e))->unique();

        $dikirim = 0;
        $duplikat = 0;
        $urutan = 0;

        // Satu alamat boleh mengisi dua kali di acara yang sama (keputusan
        // user), tapi orangnya satu — cukup satu email. Baris lainnya ditandai
        // "duplikat", bukan "terkirim", agar laporan tidak mengaku mengirim
        // sesuatu yang tidak dikirim.
        foreach ($kandidat->groupBy(fn ($t) => Str::lower($t->email)) as $alamat => $baris) {
            $baris = $baris->sortBy('nomor_urut')->values();

            if ($sudahTerkirim->contains($alamat)) {
                foreach ($baris as $t) {
                    $t->update(['email_status' => 'duplikat', 'email_error' => null]);
                    $duplikat++;
                }
                continue;
            }

            $utama = $baris->first();
            $utama->update(['email_status' => 'menunggu', 'email_error' => null]);

            KirimNotulensiTamuJob::dispatch($utama->id)
                ->delay(now()->addSeconds($urutan * self::JEDA_KIRIM_DETIK));

            $dikirim++;
            $urutan++;

            foreach ($baris->skip(1) as $t) {
                $t->update(['email_status' => 'duplikat', 'email_error' => null]);
                $duplikat++;
            }
        }

        $kegiatan->update([
            'notulensi_dikirim_pada'  => TimezoneHelper::now(),
            'notulensi_dikirim_oleh'  => $userId,
        ]);

        return [
            'dikirim'  => $dikirim,
            'duplikat' => $duplikat,
            'dilewati' => $semua->count() - $kandidat->count(),
        ];
    }

    /**
     * Kirim satu email uji ke alamat yang dipilih superadmin.
     *
     * Dijalankan LANGSUNG (tanpa antrean) supaya kesalahan SMTP terlihat
     * seketika di layar — kalau lewat antrean, salah kata sandi baru terbaca di
     * log pekerja, dan superadmin mengira sudah beres lalu mengirim ke 50 tamu.
     *
     * @throws \DomainException
     */
    public function ujiKirim(KegiatanTamu $kegiatan, string $email): void
    {
        if ($alasan = $this->alasanEmailBelumSiap()) {
            throw new \DomainException($alasan);
        }
        if (blank($kegiatan->notulensi)) {
            throw new \DomainException('Tulis dan simpan notulensinya dulu agar ada yang bisa diuji.');
        }

        // Tamu contoh — tidak disimpan ke basis data.
        $contoh = new Tamu([
            'nama'       => 'Contoh Penerima (uji kirim)',
            'asal'       => '—',
            'pekerjaan'  => '—',
            'email'      => $email,
            'nomor_urut' => 0,
        ]);
        $contoh->setRelation('kegiatan', $kegiatan);

        try {
            \Illuminate\Support\Facades\Mail::to($email)
                ->send(new \App\Mail\NotulensiKegiatan($kegiatan, $contoh));
        } catch (\Throwable $e) {
            throw new \DomainException('Gagal mengirim: ' . Str::limit($e->getMessage(), 200, ''));
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pembantu
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Apakah domain email tamu benar-benar bisa menerima surat?
     *
     * Penting karena notulensi dikirim lewat email: salah ketik seperti
     * "gmial.com" baru ketahuan berbulan kemudian saat pengiriman gagal, dan
     * setiap kiriman gagal memakan kuota email.
     *
     * TIDAK memakai aturan `email:dns` bawaan Laravel: bila DNS kontainer
     * sedang tak bisa dihubungi, aturan itu menolak SEMUA alamat dan tamu di
     * lokasi acara gagal mengisi sama sekali. Di sini, bila DNS-nya sendiri
     * yang bermasalah, pemeriksaan dilewati — lebih baik satu alamat salah
     * lolos daripada seluruh buku tamu lumpuh.
     *
     * @return string|null  null = diterima; selain itu pesan untuk tamu
     */
    public function alasanEmailDitolak(string $email): ?string
    {
        $domain = Str::after(Str::lower(trim($email)), '@');
        if ($domain === '' || !str_contains($email, '@')) {
            return 'Alamat email tidak valid.';
        }
        if (!function_exists('checkdnsrr')) return null;

        // Patokan: bila domain yang pasti ada pun tak terbaca, DNS-nya bermasalah.
        $dnsSehat = @checkdnsrr('gmail.com', 'MX');
        if (!$dnsSehat) return null;

        if (@checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A')) return null;

        return 'Domain email "' . $domain . '" tidak ditemukan. Mohon periksa kembali alamat email Anda.';
    }

    /** Ringkasan untuk kartu admin. */
    public function ringkasan(KegiatanTamu $kegiatan): array
    {
        $tamu = $kegiatan->tamu()->get(['id', 'email', 'email_status', 'konfirmasi_terkirim_pada']);

        return [
            'tamu'      => $tamu->count(),
            // Alamat unik = jumlah email yang benar-benar akan terkirim.
            'email'     => $tamu->pluck('email')->map(fn ($e) => Str::lower($e))->unique()->count(),
            'terkirim'  => $tamu->where('email_status', 'terkirim')->count(),
            'gagal'     => $tamu->where('email_status', 'gagal')->count(),
            'menunggu'  => $tamu->where('email_status', 'menunggu')->count(),
            'belum'     => $tamu->where('email_status', 'belum')->count(),
            'duplikat'  => $tamu->where('email_status', 'duplikat')->count(),
            'konfirmasi'=> $tamu->whereNotNull('konfirmasi_terkirim_pada')->count(),
        ];
    }

    /** Rapikan isian tamu: spasi ganda & huruf kapital asal-asalan. */
    private function rapikan(string $teks): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', trim($teks)), 175, '');
    }
}
