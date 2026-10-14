<?php

namespace App\Services;

use App\Models\PengajuanIzin;
use App\Models\PenugasanTambahan;
use App\Models\TenagaPendidik;
use App\Models\TugasTambahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TUGAS TAMBAHAN OTOMATIS DARI DINAS LUAR — satu pintu.
 *
 * ══ MENGAPA HANYA DINAS LUAR ══
 * Dinas luar satu-satunya pengajuan yang isinya memang PEKERJAAN: guru pergi
 * atas perintah pesantren lalu mengerjakan sesuatu di sana (di produksi:
 * "Workshop Guru Ismuba", "Pelatihan guru ismuba", "Undangan peresmian",
 * "mengantar sosialisasi PPDB"). Sakit, izin, dan cuti adalah ketidakhadiran —
 * menerbitkan tugas untuk itu tidak punya makna. Karena itu sakelarnya ada di
 * `setting_jenis_pengajuan.buat_tugas_tambahan` dan dinyalakan hanya untuk
 * kategori `dinas`.
 *
 * ══ KAPAN TERBIT: SAAT DISETUJUI, BUKAN SAAT MENGISI ══
 * Tugas tambahan membawa vakasi. Bila diterbitkan saat guru mengisi formulir,
 * siapa pun bisa menerbitkan pekerjaan berbayar untuk dirinya sendiri. Jadi
 * pemicunya `prosesSetelahDisetujui()`.
 *
 * Jenis "Dinas Luar" di produksi ber-`auto_approve = 0` (wajib disetujui
 * pimpinan), sehingga jalur itu aman. Tetapi sakelar auto-approve bisa diubah
 * admin kapan saja; bila itu terjadi, tugasnya tetap terbit TETAPI
 * penugasannya ditandai BELUM disetujui, sehingga payroll melewatinya sampai
 * ada manusia yang menyetujui. Otomatisasi tidak boleh menjadi jalan pintas
 * menuju uang.
 *
 * ══ VAKASI BARU CAIR SETELAH DILAPORKAN ══
 * `wajib_laporan = true` dan `tipe_pengerjaan = 'mandiri'`. Payroll membayar
 * tugas mandiri hanya bila `disetujui = true` DAN `status_pengerjaan =
 * 'selesai'` DAN `dilaporkan_pada` ada di periode itu. Jadi guru tetap harus
 * melaporkan apa yang ia kerjakan — persis maksud "tugas tambahan yang dia
 * lakukan". Nominalnya dari Setting Vakasi tipe `dinas_luar`; bila belum
 * diatur, tugasnya tetap terbit dengan vakasi 0 sebagai catatan kerja.
 *
 * ══ IDEMPOTEN & BISA DIBATALKAN ══
 * Tautannya disimpan di `pengajuan_izin.tugas_tambahan_id`, jadi proses yang
 * terulang tidak menggandakan tugas. Pengajuan yang dibatalkan ikut
 * membatalkan tugasnya — kecuali vakasinya sudah masuk periode berslip, karena
 * gaji yang sudah terbit tidak boleh berubah diam-diam.
 */
class TugasDinasLuarService
{
    public const TIPE_VAKASI = 'dinas_luar';

    /** Hari jendela laporan setelah hari dinas berakhir. */
    public const HARI_JENDELA_LAPORAN = 7;

    /**
     * Nominal berlaku untuk guru tertentu; 0 bila belum diatur di Setting Vakasi.
     *
     * Memakai resolver penggajian apa adanya, bukan query sendiri. Lingkup,
     * masa berlaku, dan prioritas (individu → jabatan → semua) WAJIB sama
     * dengan yang dipakai slip gaji: nominalnya menempel di tugas lewat
     * `vakasi_override`, jadi selisih tafsir sekecil apa pun langsung menjadi
     * uang yang salah.
     */
    public function nominal(?TenagaPendidik $tp = null): float
    {
        if (!$tp) {
            return 0.0;
        }

        $setting = app(PayrollCalculationService::class)
            ->getVakasiUntukGuru(self::TIPE_VAKASI, $tp);

        return (float) ($setting->nominal ?? 0);
    }

    /**
     * Apakah pengajuan ini berhak menerbitkan tugas tambahan?
     *
     * @return string|null null = berhak; selain itu alasannya (untuk log/UI)
     */
    public function alasanTidakTerbit(PengajuanIzin $p): ?string
    {
        $jenis = $p->jenisPengajuan;

        if (!$jenis) {
            return 'Jenis pengajuan tidak ditemukan.';
        }
        if ($jenis->kategori !== 'dinas') {
            return 'Hanya pengajuan berkategori dinas yang menerbitkan tugas tambahan.';
        }
        if (!$jenis->buat_tugas_tambahan) {
            return "Otomatisasi tugas tambahan untuk jenis \"{$jenis->nama}\" sedang dimatikan.";
        }
        // Izin sementara (berbasis jam) bukan penugasan sehari penuh; tidak
        // diterbitkan agar tidak ada vakasi untuk keluar sebentar. Diperiksa
        // SEBELUM status: ini sifat bawaan pengajuannya, bukan tahap alurnya,
        // jadi alasannya harus sama baik pengajuannya masih pending maupun
        // sudah disetujui — kalau tidak, admin yang bertanya "kenapa dinas
        // sejam tidak dapat tugas?" akan dijawab "belum disetujui".
        if ($p->is_sementara) {
            return 'Dinas luar berbasis jam (izin sementara) tidak menerbitkan tugas tambahan.';
        }
        if ($p->status !== 'disetujui') {
            return 'Tugas tambahan hanya terbit untuk pengajuan yang sudah disetujui.';
        }
        if ($p->tugas_tambahan_id) {
            return 'Pengajuan ini sudah punya tugas tambahan.';
        }

        return null;
    }

    /**
     * Terbitkan tugas tambahan dari data pengajuan dinas luar.
     *
     * Tidak pernah melempar galat: dipanggil dari dalam transaksi persetujuan
     * izin, dan kegagalan menerbitkan tugas tidak boleh membatalkan
     * persetujuan izinnya sendiri. Kegagalan dikembalikan sebagai null +
     * tercatat di log.
     */
    public function buatDariPengajuan(PengajuanIzin $p, ?int $userId = null): ?TugasTambahan
    {
        if ($alasan = $this->alasanTidakTerbit($p)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($p, $userId) {
                $guru    = $p->tenagaPendidik;
                $jenis   = $p->jenisPengajuan;
                $nominal = $this->nominal($guru);

                // Judul diambil dari ALASAN yang diisi guru — di produksi kolom
                // itu sudah berisi nama kegiatan ("Workshop Guru Ismuba di SMP
                // Muhammadiyah"), jadi tidak perlu menambah kolom baru di
                // formulir yang sudah dipakai sehari-hari.
                $pokok = Str::limit(trim((string) $p->alasan) ?: $jenis->nama, 120, '');

                $tt = TugasTambahan::create([
                    'judul'           => 'Dinas Luar: ' . $pokok,
                    'deskripsi'       => $this->deskripsi($p),
                    'tanggal_mulai'   => $p->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
                    'tipe'            => 'individu',
                    'tipe_pengerjaan' => 'mandiri',
                    'vakasi_override' => $nominal,
                    // Guru wajib melaporkan apa yang dikerjakan; vakasinya baru
                    // cair setelah laporan masuk.
                    'wajib_laporan'   => true,
                    'status'          => 'aktif',
                    // `dibuat_oleh` NOT NULL tanpa default. Bila pemicunya
                    // bukan manusia yang masuk (scheduler, perintah konsol,
                    // jalur auto-approve), tanpa cadangan ini insert-nya gagal
                    // dan tugasnya hilang DIAM-DIAM karena galatnya hanya
                    // dicatat di log.
                    'dibuat_oleh'     => $userId
                        ?? $p->diproses_oleh
                        ?? $guru->user_id,
                ]);

                // Bila jenisnya auto-approve, tidak ada manusia yang memeriksa
                // pengajuannya — maka vakasinya JANGAN langsung sah.
                $disetujui = !$jenis->auto_approve;

                $pn = PenugasanTambahan::create([
                    'tugas_tambahan_id'  => $tt->id,
                    'tenaga_pendidik_id' => $guru->id,
                    'status_pengerjaan'  => 'belum',
                    'disetujui'          => $disetujui,
                    // Tenggat laporan BAWAAN tugas adalah akhir `tanggal_selesai`,
                    // dan tanggal itu di sini adalah hari dinasnya sendiri —
                    // artinya guru yang pulang malam lalu melapor besok pagi
                    // selalu tertolak "tenggat lewat" dan harus minta
                    // perpanjangan ke admin SETIAP kali. Otomatisasi yang
                    // menambah pekerjaan admin bukan otomatisasi. Diberi
                    // jendela laporan; admin tetap bisa mengubah/mencabutnya
                    // dari halaman Tugas Tambahan.
                    'tenggat_perpanjangan' => $p->tanggal_selesai->copy()
                        ->addDays(self::HARI_JENDELA_LAPORAN)->toDateString(),
                ]);

                $p->update(['tugas_tambahan_id' => $tt->id, 'penugasan_id' => $pn->id]);

                return $tt;
            });
        } catch (\Throwable $e) {
            \Log::warning("Gagal menerbitkan tugas tambahan dinas luar untuk pengajuan {$p->id}: "
                . $e->getMessage());

            return null;
        }
    }

    /**
     * Batalkan tugas yang terbit dari pengajuan (saat izinnya dibatalkan).
     *
     * @return string|null null = berhasil/tidak ada yang perlu dibatalkan;
     *                     selain itu alasan penolakan untuk disampaikan admin
     */
    public function batalkanDariPengajuan(PengajuanIzin $p, string $alasan): ?string
    {
        if (!$p->tugas_tambahan_id) {
            return null;
        }

        $pn = $p->penugasan_id ? PenugasanTambahan::find($p->penugasan_id) : null;

        // Vakasi yang sudah terbit di slip tidak boleh hilang diam-diam.
        if ($pn && $pn->dilaporkan_pada && app(KoreksiPembelajaranService::class)
            ->periodeSudahBerslip(\Carbon\Carbon::parse($pn->dilaporkan_pada)->toDateString())) {
            return 'Tugas dinas luar ini sudah dilaporkan dan masuk periode penggajian yang berslip, '
                . 'jadi tugasnya dibiarkan apa adanya. Bereskan lewat koreksi penggajian bila perlu.';
        }

        DB::transaction(function () use ($p, $pn, $alasan) {
            if ($pn) {
                $pn->update([
                    'disetujui'         => false,   // payroll melewati yang tidak disetujui
                    'status_pengerjaan' => 'tidak_selesai',
                    'dilaporkan_pada'   => null,
                    'laporan'           => 'Pengajuan dinas luar dibatalkan — ' . $alasan,
                ]);
            }

            TugasTambahan::where('id', $p->tugas_tambahan_id)->update(['status' => 'dibatalkan']);

            // Tautannya DIPUTUS supaya bila kelak pengajuannya disetujui lagi,
            // tugas baru bisa terbit tanpa tertahan penjaga idempoten.
            $p->update(['tugas_tambahan_id' => null, 'penugasan_id' => null]);
        });

        return null;
    }

    /** Deskripsi tugas: seluruh data yang diisi guru, apa adanya. */
    private function deskripsi(PengajuanIzin $p): string
    {
        $baris = [];
        $baris[] = 'Diterbitkan otomatis dari pengajuan ' . $p->jenisPengajuan->nama
            . ' #' . $p->id . ' yang telah disetujui.';
        $baris[] = 'Keperluan: ' . (trim((string) $p->alasan) ?: '—');
        $baris[] = 'Tanggal: ' . $p->tanggal_mulai->locale('id')->isoFormat('D MMMM YYYY')
            . ($p->tanggal_selesai->ne($p->tanggal_mulai)
                ? ' s.d. ' . $p->tanggal_selesai->locale('id')->isoFormat('D MMMM YYYY') : '')
            . ' (' . $p->jumlah_hari . ' hari)';
        if ($p->nama_dokumen) {
            $baris[] = 'Dokumen: ' . $p->nama_dokumen;
        }
        $baris[] = 'Laporkan hasil dinas pada tugas ini agar vakasinya dapat diproses '
            . '(batas pengisian ' . $p->tanggal_selesai->copy()
                ->addDays(self::HARI_JENDELA_LAPORAN)->locale('id')->isoFormat('D MMMM YYYY') . ').';

        return implode("\n", $baris);
    }
}
