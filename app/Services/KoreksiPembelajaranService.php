<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\KoreksiAbsensi;
use App\Models\PengajuanIzin;
use App\Models\PeriodePenggajian;
use App\Models\Santri;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KOREKSI PEMBELAJARAN — satu pintu untuk semua pembetulan sesi & absensi santri.
 *
 * Empat jalur, satu aturan dasar yang sama:
 *
 *  1. GURU memperbaiki absensi santrinya sendiri — HANYA selama sesinya masih
 *     dalam jendela (jam_selesai + tenggang). Banyak guru lupa mengisi atau
 *     salah tekan; sebelumnya roster terkunci sejak simpan pertama sehingga
 *     satu kekeliruan harus menunggu admin.
 *  2. ADMIN memperbaiki absensi santri sesi apa pun, kapan pun (tanpa jendela).
 *  3. ADMIN mengatur terlaksananya sesi (status/JP/jam/materi).
 *  4. ADMIN memberi inval cepat tanpa menunggu izin resmi guru.
 *
 * Pengaman yang berlaku untuk SEMUA jalur:
 *  - periode penggajian yang sudah dikunci ATAU slipnya sudah terbit ditolak,
 *    karena mengubah JP di situ membuat slip yang sudah dipegang guru tidak
 *    cocok lagi dengan datanya;
 *  - setiap perubahan meninggalkan jejak: `KoreksiAbsensi` untuk sesi dan
 *    `absensi_santri.dikoreksi_oleh/pada` untuk tiap baris santri;
 *  - WA ke wali hanya dikirim untuk baris yang BENAR-BENAR berubah.
 */
class KoreksiPembelajaranService
{
    /** Status sesi yang sah dipakai koreksi admin. */
    public const STATUS_SESI = ['terlaksana', 'tidak_terlaksana', 'pengganti', 'libur', 'izin'];

    // ══════════════════════════════════════════════════════════════════════
    // 1. GURU — edit absensi santri dalam jendela
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Mengapa guru ini tidak boleh mengedit roster sesi tsb (null = boleh).
     *
     * Jendelanya sengaja memakai batas yang SAMA dengan batas JP
     * (KebijakanMengajar::batasAbsenSesi) — satu istilah "masih dalam jam" untuk
     * absen, jurnal, dan koreksi, supaya guru tidak perlu menghafal dua aturan.
     */
    public function alasanGuruTakBolehEdit(AbsensiMengajar $am, ?TenagaPendidik $tp, ?Carbon $now = null): ?string
    {
        if (!$tp) return 'Akun ini bukan tenaga pendidik.';

        $jadwal = $am->jadwalMengajar;
        if (!$jadwal) return 'Jadwal sesi ini tidak ditemukan.';

        // Sesi kegiatan punya jalur koreksinya sendiri (boleh berulang, tanpa jendela).
        if ($am->status === 'libur' && $am->libur_pembelajaran_id) {
            return 'Sesi ini diliburkan karena kegiatan — pakai Koreksi Absensi Kegiatan.';
        }
        if (in_array($am->status, ['libur', 'izin', 'tidak_terlaksana'], true)) {
            return 'Sesi ini berstatus "' . $am->status . '" — hubungi admin untuk koreksi.';
        }

        $pengampu  = (int) $am->tenaga_pendidik_id === $tp->id && !$am->digantikan_oleh;
        $pengganti = (int) $am->digantikan_oleh === $tp->id;
        if (!$pengampu && !$pengganti) {
            return $am->digantikan_oleh
                ? 'Sesi ini dialihkan ke guru pengganti — yang berhak mengoreksi adalah dia.'
                : 'Sesi mengajar ini bukan milik Anda.';
        }

        $tanggal = $am->tanggal instanceof Carbon ? $am->tanggal->toDateString() : (string) $am->tanggal;
        $now   ??= TimezoneHelper::now();
        $batas   = KebijakanMengajar::batasAbsenSesi($tanggal, (string) $jadwal->jam_selesai);

        if ($now->gt($batas)) {
            return 'Batas koreksi sesi ini sudah lewat (pukul ' . $batas->format('H:i')
                . '). Hubungi admin bila masih perlu diperbaiki.';
        }
        if ($this->periodeTerkunci($tanggal)) {
            return 'Periode penggajian tanggal ini sudah terkunci atau slipnya sudah terbit.';
        }

        return null;
    }

    /** Batas waktu koreksi guru untuk sesi tsb (null bila jadwalnya tak ada). */
    public function batasEdit(AbsensiMengajar $am): ?Carbon
    {
        $jadwal = $am->jadwalMengajar;
        if (!$jadwal) return null;

        $tanggal = $am->tanggal instanceof Carbon ? $am->tanggal->toDateString() : (string) $am->tanggal;

        return KebijakanMengajar::batasAbsenSesi($tanggal, (string) $jadwal->jam_selesai);
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. ROSTER SANTRI — dipakai guru (dalam jendela) & admin (tanpa jendela)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Tulis ulang status santri pada satu sesi. Hanya baris yang BERUBAH yang
     * disentuh dan dicatat; santri di luar kelas diabaikan.
     *
     * @param  array $rows  [['santri_id'=>int,'status'=>string], ...]
     * @param  bool  $olehAdmin  true = jejaknya dicatat sebagai koreksi admin
     * @return array{diubah:int,ditambah:int,tetap:int,berubah:Collection}
     */
    public function tulisRoster(
        AbsensiMengajar $am, array $rows, int $olehUserId, bool $olehAdmin = false, ?string $alasan = null
    ): array {
        $kelasId = $am->jadwalMengajar?->kelas_id;
        if (!$kelasId) {
            throw new \DomainException('Sesi ini belum punya kelas — absensi santri tidak bisa disimpan.');
        }

        $sah  = Santri::aktif()->anggotaKelas((int) $kelasId)->pluck('id')->flip();
        $lama = AbsensiSantri::where('absensi_mengajar_id', $am->id)->get()->keyBy('santri_id');

        $diubah = 0; $ditambah = 0; $tetap = 0;
        $berubah = collect();
        $jejak = [];

        DB::transaction(function () use (
            $rows, $sah, $lama, $am, $olehUserId, &$diubah, &$ditambah, &$tetap, &$berubah, &$jejak
        ) {
            foreach ($rows as $r) {
                $sid    = (int) ($r['santri_id'] ?? 0);
                $status = $r['status'] ?? null;
                if (!$sid || !$status || !$sah->has($sid)) continue;

                $baris = $lama->get($sid);
                if ($baris && $baris->status === $status) { $tetap++; continue; }

                if ($baris) {
                    $jejak[] = ['santri_id' => $sid, 'dari' => $baris->status, 'ke' => $status];
                    $baris->update([
                        'status'         => $status,
                        'dikoreksi_oleh' => $olehUserId,
                        'dikoreksi_pada' => now(),
                    ]);
                    $diubah++;
                } else {
                    $jejak[] = ['santri_id' => $sid, 'dari' => null, 'ke' => $status];
                    $baris = AbsensiSantri::create([
                        'absensi_mengajar_id' => $am->id,
                        'santri_id'           => $sid,
                        'status'              => $status,
                        'sumber'              => 'guru',
                        'dikoreksi_oleh'      => $olehUserId,
                        'dikoreksi_pada'      => now(),
                    ]);
                    $ditambah++;
                }
                $berubah->push($baris->fresh());
            }
        });

        // Jejak sesi-level hanya untuk koreksi admin: guru memperbaiki pekerjaannya
        // sendiri di dalam jam, itu bagian normal dari mengisi absensi.
        if ($olehAdmin && $jejak) {
            $nama = Santri::whereIn('id', collect($jejak)->pluck('santri_id'))->pluck('nama_lengkap', 'id');
            $ringkas = collect($jejak)->take(8)->map(fn ($j) => ($nama[$j['santri_id']] ?? $j['santri_id'])
                . ': ' . ($j['dari'] ?? 'kosong') . ' → ' . $j['ke'])->implode('; ');

            KoreksiAbsensi::create([
                'tenaga_pendidik_id'  => $am->tenaga_pendidik_id,
                'tanggal'             => $am->tanggal,
                'tipe_absensi'        => 'mengajar',
                'absensi_mengajar_id' => $am->id,
                'field_dikoreksi'     => 'absensi_santri',
                'nilai_lama'          => 'diubah ' . count($jejak) . ' santri',
                'nilai_baru'          => mb_substr($ringkas, 0, 250),
                'alasan'              => $alasan ?: 'Koreksi absensi santri oleh admin',
                'status'              => 'disetujui',
                'dikoreksi_oleh'      => $olehUserId,
            ]);
        }

        return ['diubah' => $diubah, 'ditambah' => $ditambah, 'tetap' => $tetap, 'berubah' => $berubah];
    }

    /** Kirim WA wali HANYA untuk baris yang berubah (aturan anti-ganda ada di service kehadiran). */
    public function kabariWali(AbsensiMengajar $am, Collection $berubah): void
    {
        if ($berubah->isEmpty()) return;

        $tgl = $am->tanggal instanceof Carbon ? $am->tanggal->toDateString() : (string) $am->tanggal;

        app(KehadiranSantriService::class)->kirimWa(
            $berubah,
            $am->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM',
            $tgl
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. ADMIN — mengatur terlaksananya sesi
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Koreksi status/JP/jam/materi sesi. Dipakai admin dari laporan & jurnal.
     *
     * Sesi yang belum pernah tercatat pun bisa dikoreksi: barisnya dibuat dari
     * jadwalnya — ini yang dibutuhkan saat guru lupa sama sekali mengabsen.
     *
     * @throws \DomainException
     */
    public function koreksiSesi(AbsensiMengajar $am, array $d, int $olehUserId): AbsensiMengajar
    {
        $tanggal = $am->tanggal instanceof Carbon ? $am->tanggal->toDateString() : (string) $am->tanggal;
        if ($this->periodeTerkunci($tanggal)) {
            throw new \DomainException('Periode penggajian tanggal ini sudah terkunci atau slipnya sudah terbit.');
        }

        $status = $d['status'];
        if (!in_array($status, self::STATUS_SESI, true)) {
            throw new \DomainException('Status sesi tidak dikenal.');
        }
        if ($status === 'pengganti' && empty($d['digantikan_oleh'])) {
            throw new \DomainException('Status "pengganti" wajib menyebut guru penggantinya.');
        }

        $jpJadwal = (int) ($am->jadwalMengajar?->jumlah_jp ?? 0);

        return DB::transaction(function () use ($am, $d, $status, $olehUserId, $jpJadwal) {
            KoreksiAbsensi::create([
                'tenaga_pendidik_id'  => $am->tenaga_pendidik_id,
                'tanggal'             => $am->tanggal,
                'tipe_absensi'        => 'mengajar',
                'absensi_mengajar_id' => $am->id,
                'field_dikoreksi'     => 'status',
                'nilai_lama'          => $am->status . ' / ' . (int) $am->jp_terlaksana . ' JP',
                'nilai_baru'          => $status . ' / ' . ($d['jp_terlaksana'] ?? $jpJadwal) . ' JP',
                'alasan'              => $d['alasan_koreksi'],
                'status'              => 'disetujui',
                'dikoreksi_oleh'      => $olehUserId,
            ]);

            // JP mengikuti status bila tidak disebut: status yang tidak menghasilkan
            // pembelajaran tidak boleh membawa JP.
            $jp = array_key_exists('jp_terlaksana', $d) && $d['jp_terlaksana'] !== null
                ? (int) $d['jp_terlaksana']
                : (in_array($status, ['terlaksana', 'libur', 'izin'], true) ? $jpJadwal : 0);

            $am->update([
                'status'             => $status,
                'jp_terlaksana'      => $jp,
                'jam_mulai_aktual'   => $d['jam_mulai_aktual']   ?? $am->jam_mulai_aktual,
                'jam_selesai_aktual' => $d['jam_selesai_aktual'] ?? $am->jam_selesai_aktual,
                'materi'             => $d['materi']     ?? $am->materi,
                'keterangan'         => $d['keterangan'] ?? $am->keterangan,
                'digantikan_oleh'    => $status === 'pengganti'
                    ? (int) ($d['digantikan_oleh'] ?? $am->digantikan_oleh) : null,
                'is_koreksi'         => true,
                'dikoreksi_oleh'     => $olehUserId,
            ]);

            return $am->fresh();
        });
    }

    /**
     * Pastikan ada baris absensi untuk satu jadwal+tanggal (dibuat bila belum ada).
     * Dipakai admin saat guru lupa mengabsen sama sekali.
     */
    public function pastikanBaris(JadwalMengajar $jadwal, string $tanggal): AbsensiMengajar
    {
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB(Carbon::parse($tanggal))) {
            throw new \DomainException('Jadwal ini tidak berlangsung pada tanggal tersebut.');
        }

        return AbsensiMengajar::firstOrCreate(
            ['jadwal_mengajar_id' => $jadwal->id, 'tanggal' => $tanggal],
            [
                'tenaga_pendidik_id' => $jadwal->tenaga_pendidik_id,
                'status'             => 'tidak_terlaksana',
                'jp_terlaksana'      => 0,
                'sudah_buka_jurnal'  => false,
                'keterangan'         => 'Dibuat admin untuk koreksi',
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4. ADMIN — inval cepat
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Alihkan satu sesi ke guru lain TANPA menunggu izin resmi guru aslinya.
     *
     * `PenggantiMengajarService::tunjukPengganti()` sengaja mensyaratkan izin
     * disetujui — itu benar untuk guru yang menunjuk penggantinya sendiri. Untuk
     * admin, syarat itu justru menghalangi: guru bisa berhalangan mendadak tanpa
     * sempat mengajukan izin. Kelayakan PENGGANTI tetap diperiksa dengan aturan
     * yang sama agar tidak ada guru dijadwalkan di dua tempat sekaligus.
     *
     * @throws \DomainException
     */
    public function invalCepat(
        JadwalMengajar $jadwal, string $tanggal, int $penggantiId, int $olehUserId, ?string $alasan = null
    ): AbsensiMengajar {
        if ($jadwal->ujian_sesi_id) {
            throw new \DomainException('Sesi ujian di-inval dari halaman Ujian Sekolah.');
        }
        if ($this->periodeTerkunci($tanggal)) {
            throw new \DomainException('Periode penggajian tanggal ini sudah terkunci atau slipnya sudah terbit.');
        }
        if ($penggantiId === (int) $jadwal->tenaga_pendidik_id) {
            throw new \DomainException('Pengganti harus guru lain, bukan guru pengampunya sendiri.');
        }

        $tgl = Carbon::parse($tanggal);
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($tgl)) {
            throw new \DomainException('Jadwal ini tidak berlangsung pada tanggal tersebut.');
        }

        $pengganti = TenagaPendidik::where('id', $penggantiId)->where('is_aktif', true)->first();
        if (!$pengganti) {
            throw new \DomainException('Guru pengganti tidak ditemukan / tidak aktif.');
        }

        // Kelayakan pengganti: aturan yang sama dengan daftar calon inval biasa.
        $svc = app(PenggantiMengajarService::class);
        $kelayakan = $svc->kelayakanInval($penggantiId, $jadwal, $tgl);
        if ($kelayakan['alasan']) {
            throw new \DomainException($kelayakan['alasan']);
        }

        $ada = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal)->first();
        if ($ada && in_array($ada->status, ['terlaksana', 'hadir', 'libur'], true)) {
            throw new \DomainException('Sesi ini sudah tercatat "' . $ada->status . '" — koreksi statusnya dulu.');
        }
        if ($ada && $ada->status === 'pengganti' && (int) $ada->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sebelumnya sudah mengajar sesi ini.');
        }

        return DB::transaction(function () use ($jadwal, $tanggal, $penggantiId, $olehUserId, $alasan, $ada) {
            KoreksiAbsensi::create([
                'tenaga_pendidik_id'  => $jadwal->tenaga_pendidik_id,
                'tanggal'             => $tanggal,
                'tipe_absensi'        => 'mengajar',
                'absensi_mengajar_id' => $ada?->id,
                'field_dikoreksi'     => 'digantikan_oleh',
                'nilai_lama'          => $ada?->digantikan_oleh ? (string) $ada->digantikan_oleh : 'tidak ada',
                'nilai_baru'          => (string) $penggantiId,
                // Tanpa syarat izin, alasan inilah satu-satunya jejak keputusan admin.
                'alasan'              => $alasan ?: 'Inval cepat oleh admin',
                'status'              => 'disetujui',
                'dikoreksi_oleh'      => $olehUserId,
            ]);

            $am = AbsensiMengajar::updateOrCreate(
                ['jadwal_mengajar_id' => $jadwal->id, 'tanggal' => $tanggal],
                [
                    'tenaga_pendidik_id' => $jadwal->tenaga_pendidik_id,  // jejak guru asli
                    'digantikan_oleh'    => $penggantiId,
                    'status'             => 'pengganti',
                    'jp_terlaksana'      => 0,          // belum diajar → belum dibayar
                    'materi'             => null,
                    'keterangan'         => 'Inval oleh admin' . ($alasan ? ' — ' . $alasan : ''),
                    'is_koreksi'         => true,
                    'dikoreksi_oleh'     => $olehUserId,
                ]
            );

            return $am;
        });
    }

    /** Batalkan inval: sesi kembali ke guru pengampunya. */
    public function batalkanInval(AbsensiMengajar $am, int $olehUserId): void
    {
        if (!$am->digantikan_oleh) {
            throw new \DomainException('Sesi ini tidak sedang di-inval.');
        }
        if ($am->status === 'pengganti' && (int) $am->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sudah mengajar sesi ini — koreksi statusnya, jangan dibatalkan.');
        }

        $tanggal = $am->tanggal instanceof Carbon ? $am->tanggal->toDateString() : (string) $am->tanggal;
        if ($this->periodeTerkunci($tanggal)) {
            throw new \DomainException('Periode penggajian tanggal ini sudah terkunci atau slipnya sudah terbit.');
        }

        DB::transaction(function () use ($am, $olehUserId) {
            KoreksiAbsensi::create([
                'tenaga_pendidik_id'  => $am->tenaga_pendidik_id,
                'tanggal'             => $am->tanggal,
                'tipe_absensi'        => 'mengajar',
                // Barisnya akan dihapus, jadi jangan dirujuk (FK tanpa cascade).
                'absensi_mengajar_id' => null,
                'field_dikoreksi'     => 'digantikan_oleh',
                'nilai_lama'          => (string) $am->digantikan_oleh,
                'nilai_baru'          => 'dibatalkan',
                'alasan'              => 'Inval dibatalkan oleh admin',
                'status'              => 'disetujui',
                'dikoreksi_oleh'      => $olehUserId,
            ]);

            // Dihapus, bukan diubah: tanpa catatan, sesi kembali ke keadaan semula
            // dan scheduler akan menilainya ulang apa adanya.
            $this->lepaskanJejakAbsensi($am->id);
            $am->delete();
        });
    }

    /** Calon pengganti untuk inval cepat — semua tendik aktif + alasan bila tak bisa. */
    public function calonInval(JadwalMengajar $jadwal, string $tanggal): Collection
    {
        $tgl = Carbon::parse($tanggal);
        $svc = app(PenggantiMengajarService::class);

        return TenagaPendidik::where('is_aktif', true)->with('user:id,name')->get()
            ->filter(fn ($tp) => $tp->id !== (int) $jadwal->tenaga_pendidik_id)
            ->map(function ($tp) use ($svc, $jadwal, $tgl) {
                $k = $svc->kelayakanInval($tp->id, $jadwal, $tgl);
                return [
                    'id'     => $tp->id,
                    'nama'   => $tp->user?->name ?? '—',
                    'boleh'  => $k['alasan'] === null,
                    'alasan' => $k['alasan'],
                ];
            })
            ->sortBy([['boleh', 'desc'], ['nama', 'asc']])
            ->values();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pembantu
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Lepaskan rujukan log koreksi dari baris absensi yang akan DIHAPUS.
     *
     * `koreksi_absensi.absensi_mengajar_id` ber-foreign key tanpa cascade, jadi
     * menghapus baris absensi yang pernah dikoreksi akan gagal di tengah jalan
     * (terjadi nyata saat membatalkan inval). Jejaknya sendiri harus tetap ada —
     * itu riwayat keputusan — sehingga rujukannya dikosongkan, bukan dihapus.
     */
    public function lepaskanJejakAbsensi(int|array $absensiIds): void
    {
        $ids = array_filter((array) $absensiIds);
        if (!$ids) return;

        KoreksiAbsensi::whereIn('absensi_mengajar_id', $ids)->update(['absensi_mengajar_id' => null]);
    }

    /** Guru sedang izin resmi pada tanggal itu? (dipakai UI inval cepat) */
    public function izinAktif(int $tpId, string $tanggal): ?PengajuanIzin
    {
        return PengajuanIzin::where('tenaga_pendidik_id', $tpId)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->with('jenisPengajuan')->first();
    }

    private function periodeTerkunci(string $tanggal): bool
    {
        $periode = PeriodePenggajian::untukTanggal($tanggal);
        if (!$periode) return false;

        return $periode->dikunci_pada !== null || $periode->penggajian()->exists();
    }
}
