<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\LogKerjaHarian;
use App\Models\PenugasanTambahan;
use App\Models\PiketPenilaian;
use App\Models\RealisasiTugasJabatan;
use App\Models\RekapKinerjaBulanan;
use App\Models\SettingJamKerja;
use App\Models\SettingKinerja;
use App\Models\TenagaPendidik;
use App\Models\TugasJabatan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * KinerjaCalculationService
 *
 * ═══════════════════════════════════════════════════════════════════
 * FORMULA KINERJA (3 Komponen inti bobot 100% + PENYESUAIAN Piket)
 * ═══════════════════════════════════════════════════════════════════
 *
 * SKOR_DASAR = (Skor_Absensi × bobot%) + (Skor_Tugas × bobot%) + (Skor_Admin × bobot%)   // Σbobot = 100
 * SKOR_TOTAL = clamp( SKOR_DASAR + PENYESUAIAN_PIKET , 0 , 100 )
 *
 * PENYESUAIAN_PIKET = Σpoin_apresiasi − Σpoin_catatan (dibatasi −(100−skor_min_piket)).
 *   → Guru piket TIDAK punya bobot; ia hanya PENUNJANG (+/−) di atas skor dasar:
 *     apresiasi menaikkan, catatan menurunkan kinerja bulan itu.
 *
 * ── KOMPONEN 1: ABSENSI (default 50%) ──────────────────────────────
 *   Skor_Absensi = (Skor_Harian × 70%) + (Skor_Mengajar × 30%)
 *
 *   Skor_Harian:
 *     → Setiap hari kerja dihitung nilainya berdasarkan status:
 *       hadir=100, terlambat=75, izin=70, sakit=80, dinas_luar=100, alfa=0
 *       (libur dikecualikan / tidak dihitung)
 *     → Skor_Harian = SUM(nilai_status) / (hari_kerja × 100) × 100
 *     → Penalty tambahan jika hitung_penalty_terlambat = true:
 *       skor dikurangi (n_terlambat × penalty%) dengan batas max
 *
 *   Skor_Mengajar:
 *     → Berdasarkan sesi jadwal yang terlaksana:
 *       skor = (sesi_terlaksana / sesi_jadwal_bulan) × 100
 *
 * ── KOMPONEN 2: TUGAS (default 30%) ────────────────────────────────
 *   Skor_Tugas = (Skor_Penugasan × 60%) + (Skor_Jabatan × 40%)
 *
 *   Skor_Penugasan = penugasan_selesai_disetujui / total_penugasan × 100
 *   Skor_Jabatan   = realisasi_disetujui / target_tugas_wajib × 100
 *
 * ── KOMPONEN 3: ADMINISTRASI (default 20%) ─────────────────────────
 *   Skor_Admin = (Skor_Laporan × 60%) + (Skor_Log × 40%)
 *
 *   Skor_Laporan (Laporan Mengajar):
 *     → Sesi yang dilaporkan = sesi absen mengajar + ada materi terisi
 *     → skor = sesi_dilaporkan / sesi_jadwal_aktif × 100
 *
 *   Skor_Log (Log Kerja Harian):
 *     → skor = log_submitted / (hari_kerja × target_log) × 100
 *
 * ═══════════════════════════════════════════════════════════════════
 */
class KinerjaCalculationService
{
    public function hitungRekap(TenagaPendidik $guru, int $bulan, int $tahun): RekapKinerjaBulanan
    {
        $rekap = RekapKinerjaBulanan::firstOrNew([
            'tenaga_pendidik_id' => $guru->id,
            'bulan'              => $bulan,
            'tahun'              => $tahun,
        ]);

        if ($rekap->exists && $rekap->sudah_dikunci) return $rekap;

        $setting = SettingKinerja::getDefault();
        if (!$setting) {
            throw new \RuntimeException(
                'Setting kinerja belum dikonfigurasi. Hubungi administrator untuk membuat setting kinerja terlebih dahulu.'
            );
        }
        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        // Denominator skor = hari kerja yang SUDAH BERJALAN (s/d kemarin), bukan
        // sebulan penuh → di tengah bulan guru rajin tetap ~100; skor turun hanya
        // saat ada alfa/kelalaian (auto-alfa menandai yg tak check-in). Bulan lampau
        // otomatis = sebulan penuh; awal bulan (belum ada hari berjalan) = 0 → netral 100.
        $hinggaHitung = Carbon::now()->copy()->subDay()->endOfDay();
        if ($hinggaHitung->gt($selesai)) $hinggaHitung = $selesai->copy();
        $hariKerja = $hinggaHitung->lt($mulai)
            ? 0
            : $this->hitungHariKerja($mulai, $hinggaHitung, $guru->jamKerjaAktif(), $guru->id);

        $k1 = $this->komponenAbsensi($guru, $bulan, $tahun, $hariKerja, $setting);
        $k2 = $this->komponenTugas($guru, $mulai, $selesai, $setting);
        $k3 = $this->komponenAdministrasi($guru, $bulan, $tahun, $hariKerja, $mulai, $selesai, $setting);
        $kp = $this->komponenPiket($guru, $bulan, $tahun, (float) ($setting->skor_min_piket ?? 50));

        // Skor DASAR = rata-rata TERBOBOT dari 3 komponen inti, DINORMALISASI ke
        // jumlah bobotnya sendiri → guru sempurna selalu = 100, walau bobot inti
        // tak persis berjumlah 100 (piket = penyesuaian terpisah, bukan slot bobot).
        $bobotInti = (float) ($setting->bobot_absensi + $setting->bobot_tugas + $setting->bobot_administrasi);
        $bobotInti = $bobotInti > 0 ? $bobotInti : 1;
        $skorDasar = (
              ($k1['skor'] * $setting->bobot_absensi)
            + ($k2['skor'] * $setting->bobot_tugas)
            + ($k3['skor'] * $setting->bobot_administrasi)
        ) / $bobotInti;
        // PIKET = penyesuaian (+/−) DI ATAS skor dasar → total dibatasi [0..100].
        $skorTotal = round(max(0, min(100, $skorDasar + $kp['penyesuaian'])), 2);

        $rekap->fill([
            // Skor komponen
            'skor_absensi'              => $k1['skor'],
            'skor_tugas'                => $k2['skor'],
            'skor_administrasi'         => $k3['skor'],
            'skor_piket'                => $kp['penyesuaian'], // penyesuaian signed (+/−)
            'skor_total'                => $skorTotal,
            // backward compat
            'skor_keaktifan'            => $k3['skor_log'],
            'skor_penugasan'            => $k2['skor'],
            // Data mentah absensi
            'total_hadir'               => $k1['hadir'],
            'total_terlambat'           => $k1['terlambat'],
            'total_izin'                => $k1['izin'],
            'total_sakit'               => $k1['sakit'],
            'total_alfa'                => $k1['alfa'],
            'total_dinas_luar'          => $k1['dinas_luar'],
            'total_hari_kerja'          => $hariKerja,
            // Data mentah mengajar
            'total_sesi_jadwal'         => $k3['sesi_jadwal'],
            'total_sesi_terlaksana'     => $k3['sesi_terlaksana'],
            'total_sesi_dilaporkan'     => $k3['sesi_dilaporkan'],
            'total_jp_jadwal'           => $k1['jp_jadwal'],
            'total_jp_terlaksana'       => $k1['jp_terlaksana'],
            // Data mentah log
            'total_log_submitted'       => $k3['log_submitted'],
            'total_log_diverifikasi'    => $k3['log_diverifikasi'],
            'total_durasi_menit'        => $k3['durasi_menit'],
            // Data mentah tugas
            'total_penugasan_diterima'  => $k2['penugasan_total'],
            'total_penugasan_selesai'   => $k2['penugasan_selesai'],
            'total_realisasi_jabatan'   => $k2['jabatan_total'],
            'total_realisasi_disetujui' => $k2['jabatan_disetujui'],
            'setting_kinerja_id'        => $setting->id ?? null,
        ]);

        $rekap->save();
        return $rekap;
    }

    public function hitungRekapSemua(int $bulan, int $tahun): int
    {
        $setting = \App\Models\SettingKinerja::getDefault();
        $ambang  = (float) ($setting->grade_c ?? 0);
        $guru    = TenagaPendidik::aktif()->with('user')->get();

        foreach ($guru as $g) {
            $rekap = $this->hitungRekap($g, $bulan, $tahun);

            // Notifikasi 'kinerja.rendah' bila skor di bawah ambang (guru + pimpinan).
            if ($ambang > 0 && $g->user && (float) $rekap->skor_total < $ambang) {
                \App\Services\NotifikasiService::event('kinerja.rendah', [
                    'user'  => $g->user,
                    'judul' => 'Kinerja Perlu Perhatian',
                    'pesan' => "Skor kinerja {$g->user->name} bulan ini {$rekap->skor_total} (di bawah ambang {$ambang}).",
                    'tipe'  => 'pengumuman',
                    'data'  => ['type' => 'kinerja', 'route' => '/kinerja', 'guru_id' => $g->id],
                    'dedup' => "kinerja-{$g->id}-{$bulan}-{$tahun}",
                ]);
            }
        }
        return $guru->count();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 1: ABSENSI
    // ═══════════════════════════════════════════════════════════════════════

    private function komponenAbsensi(
        TenagaPendidik $guru, int $bulan, int $tahun,
        int $hariKerja, SettingKinerja $s
    ): array {
        $absensi = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->get();

        // Hitung per status
        $hadir     = $absensi->where('status', 'hadir')->count();
        $terlambat = $absensi->where('status', 'terlambat')->count();
        $izin      = $absensi->whereIn('status', ['izin', 'izin_sakit'])->count();
        $sakit     = $absensi->where('status', 'sakit')->count();
        $alfa      = $absensi->where('status', 'alfa')->count();
        $dinasLuar = $absensi->where('status', 'dinas_luar')->count();
        // libur TIDAK dihitung — dikecualikan dari hari kerja

        // ── Skor Harian ────────────────────────────────────────────────────
        // Rumus: setiap hari kerja dinilai berdasarkan nilaiStatus
        // Total nilai dibagi (hariKerja × 100) karena basis nilai = 100
        $totalNilai = ($hadir      * $s->nilai_hadir)
                    + ($terlambat  * $s->nilai_terlambat)
                    + ($izin       * $s->nilai_izin)
                    + ($sakit      * $s->nilai_sakit)
                    + ($dinasLuar  * $s->nilai_dinas_luar)
                    + ($alfa       * $s->nilai_alfa);

        // Denominator = HARI YANG ADA CATATAN (bukan seluruh hari kerja berjalan).
        // Cegah 1 catatan (mis. 1× telat) terencer ke banyak hari → skor jeblok tak adil.
        // Di produksi auto-alfa menandai hari bolos jadi 'alfa' → tetap ikut terhitung.
        // Tanpa catatan sama sekali → belum ada data → netral 100 (bukan 0).
        $hariDinilai = $hadir + $terlambat + $izin + $sakit + $dinasLuar + $alfa;
        $skorHarian = $hariDinilai > 0
            ? min(100, round($totalNilai / ($hariDinilai * 100) * 100, 2))
            : 100;

        // Penalty tambahan terlambat (jika diaktifkan)
        if ($s->hitung_penalty_terlambat && $terlambat > 0) {
            $terlambatKenaPenalty = $absensi->where('status', 'terlambat')
                ->filter(fn($a) => ($a->menit_terlambat ?? 0) > $s->toleransi_terlambat_menit)
                ->count();

            $penalty = min(
                $terlambatKenaPenalty * $s->penalty_per_terlambat,
                $s->max_penalty_terlambat
            );
            $skorHarian = max(0, $skorHarian - $penalty);
        }

        // ── Skor Mengajar (dari sisi absensi — sesi terlaksana) ─────────────
        // Berapa sesi jadwal yang benar-benar terlaksana bulan ini
        $absensiMengajar = AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->get();

        // NETRAL: sesi yang DIALIHKAN ke guru pengganti (digantikan_oleh terisi —
        // mis. akibat izin sementara / izin harian yang menyediakan pengganti)
        // TIDAK dihitung dalam kinerja mengajar guru asli (bukan pelanggaran).
        $absensiDinilai = $absensiMengajar->whereNull('digantikan_oleh');

        $sesiJadwalBulan   = $absensiDinilai->count();
        $sesiTerlaksana    = $absensiDinilai->where('status', 'terlaksana')->count();

        // JP jadwal: hitung via DB join (Collection tidak support join) — sesi
        // yang dialihkan (digantikan_oleh) dikecualikan agar denominator adil.
        $jpJadwal = (int) DB::table('absensi_mengajar')
            ->join('jadwal_mengajar', 'absensi_mengajar.jadwal_mengajar_id', '=', 'jadwal_mengajar.id')
            ->where('absensi_mengajar.tenaga_pendidik_id', $guru->id)
            ->whereNull('absensi_mengajar.digantikan_oleh')
            ->whereMonth('absensi_mengajar.tanggal', $bulan)
            ->whereYear('absensi_mengajar.tanggal', $tahun)
            ->sum('jadwal_mengajar.jumlah_jp');

        // JP terlaksana: sum dari Collection (sudah di-load di atas)
        $jpTerlaksana = (int) $absensiDinilai->where('status', 'terlaksana')->sum('jp_terlaksana');

        // Jika tidak ada jadwal mengajar, tidak diperhitungkan (100)
        $skorMengajar = $sesiJadwalBulan > 0
            ? min(100, round($sesiTerlaksana / $sesiJadwalBulan * 100, 2))
            : 100;

        // ── Gabungkan ─────────────────────────────────────────────────────
        $skorAbsensi = round(
            ($skorHarian   * $s->bobot_absensi_harian   / 100) +
            ($skorMengajar * $s->bobot_absensi_mengajar / 100),
            2
        );

        return [
            'skor'          => $skorAbsensi,
            'skor_harian'   => $skorHarian,
            'skor_mengajar' => $skorMengajar,
            'hadir'         => $hadir,
            'terlambat'     => $terlambat,
            'izin'          => $izin,
            'sakit'         => $sakit,
            'alfa'          => $alfa,
            'dinas_luar'    => $dinasLuar,
            'jp_jadwal'     => $jpJadwal,
            'jp_terlaksana' => $jpTerlaksana,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 2: TUGAS
    // ═══════════════════════════════════════════════════════════════════════

    private function komponenTugas(
        TenagaPendidik $guru,
        Carbon $mulai, Carbon $selesai,
        SettingKinerja $s
    ): array {
        // ── Sub 2a: Penugasan Tambahan ──────────────────────────────────────
        $penugasan = PenugasanTambahan::where('tenaga_pendidik_id', $guru->id)
            ->whereHas('tugasTambahan', fn($q) =>
                $q->where('tanggal_mulai', '<=', $selesai)
                  ->where(fn($q2) => $q2->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $mulai))
            )->get();

        $penugasanTotal   = $penugasan->count();
        $penugasanSelesai = $penugasan
            ->where('status_pengerjaan', 'selesai')
            ->where('disetujui', true)->count();

        $skorPenugasan = $penugasanTotal > 0
            ? round($penugasanSelesai / $penugasanTotal * 100, 2)
            : ($s->jika_tidak_ada_tugas === 'nol' ? 0.0 : 100.0);

        // ── Sub 2b: Realisasi Tugas Jabatan (berbasis frekuensi, rekap bulanan) ──
        // Target bulanan = Σ occurrence SEMUA tugas dari SEMUA jabatan aktif:
        //   harian → hari kerja · mingguan → jumlah minggu · bulanan → 1 · insidental → 0.
        // Skor = Σ terpenuhi (realisasi disetujui, di-cap target per tugas) / Σ target.
        // Melewatkan satu occurrence → skor turun (faktor kelayakan jabatan).
        $jabatanIds = $guru->jabatan_aktif->pluck('id')->toArray();
        if (empty($jabatanIds) && $guru->jabatan_id) {
            $jabatanIds = [$guru->jabatan_id];
        }

        $hariKerja = $this->hitungHariKerja($mulai, $selesai, $guru->jamKerjaAktif(), $guru->id);
        $jmlMinggu = $this->hitungJumlahMinggu($mulai, $selesai);

        $daftarTugas = TugasJabatan::whereIn('jabatan_id', $jabatanIds)->aktif()
            ->get(['id', 'frekuensi']);

        // Realisasi DISETUJUI per tugas (auto-sah utk tugas tanpa verifikasi sudah disetujui=true)
        $realisasiPerTugas = RealisasiTugasJabatan::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->where('disetujui', true)
            ->selectRaw('tugas_jabatan_id, COUNT(*) as c')
            ->groupBy('tugas_jabatan_id')
            ->pluck('c', 'tugas_jabatan_id');

        $targetTotal    = 0;
        $terpenuhiTotal = 0;
        foreach ($daftarTugas as $t) {
            $expected = $this->targetOccurrence($t->frekuensi, $hariKerja, $jmlMinggu);
            if ($expected <= 0) continue; // insidental tidak masuk denominator
            $done = (int) ($realisasiPerTugas[$t->id] ?? 0);
            $targetTotal    += $expected;
            $terpenuhiTotal += min($done, $expected);
        }

        // Data mentah utk rekap
        $realisasiTotal     = RealisasiTugasJabatan::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])->count();
        $realisasiDisetujui = (int) $realisasiPerTugas->sum();

        $skorJabatan = $targetTotal > 0
            ? min(100, round($terpenuhiTotal / $targetTotal * 100, 2))
            : ($s->jika_tidak_ada_tugas === 'nol' ? 0.0 : 100.0);

        // ── Gabungkan ─────────────────────────────────────────────────────
        $skorTugas = round(
            ($skorPenugasan * $s->bobot_tugas_tambahan / 100) +
            ($skorJabatan   * $s->bobot_tugas_jabatan  / 100),
            2
        );

        return [
            'skor'               => $skorTugas,
            'skor_penugasan'     => $skorPenugasan,
            'skor_jabatan'       => $skorJabatan,
            'penugasan_total'    => $penugasanTotal,
            'penugasan_selesai'  => $penugasanSelesai,
            'jabatan_total'      => $realisasiTotal,
            'jabatan_disetujui'  => $realisasiDisetujui,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 3: ADMINISTRASI
    // ═══════════════════════════════════════════════════════════════════════

    private function komponenAdministrasi(
        TenagaPendidik $guru, int $bulan, int $tahun,
        int $hariKerja, Carbon $mulai, Carbon $selesai,
        SettingKinerja $s
    ): array {
        // ── Sub 3a: Laporan Mengajar ─────────────────────────────────────────
        // Sesi yang DILAPORKAN = absensi mengajar ada + materi diisi
        // Sesi jadwal aktif = semua sesi yang seharusnya mengajar bulan ini

        $absensiMengajar = AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get();

        $sesiJadwal      = $absensiMengajar->count();
        $sesiTerlaksana  = $absensiMengajar->where('status', 'terlaksana')->count();
        $sesiDilaporkan  = $absensiMengajar
            ->where('status', 'terlaksana')
            ->filter(fn($a) => !empty(trim($a->materi ?? '')))
            ->count();

        // Skor laporan: sesi yang absen mengajar DAN ada materinya
        // Jika tidak ada jadwal = sempurna
        $skorLaporan = $sesiJadwal > 0
            ? min(100, round($sesiDilaporkan / $sesiJadwal * 100, 2))
            : 100;

        // ── Sub 3b: Log Kerja Harian ─────────────────────────────────────────
        $logs = LogKerjaHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->get();

        $logSubmitted    = $logs->whereIn('status', ['submitted', 'diverifikasi'])->count();
        $logDiverifikasi = $logs->where('status', 'diverifikasi')->count();
        $durasiMenit     = (int) $logs->whereIn('status', ['submitted', 'diverifikasi'])->sum('durasi_menit');

        $targetLog  = max(1, $hariKerja * $s->target_log_per_hari);
        // Belum ada log kerja sama sekali → belum ada data (netral 100), bukan 0.
        $skorLog    = ($hariKerja > 0 && $logs->count() > 0)
            ? min(100, round($logSubmitted / $targetLog * 100, 2))
            : 100;

        // ── Gabungkan ─────────────────────────────────────────────────────
        $skorAdmin = round(
            ($skorLaporan * $s->bobot_laporan_mengajar / 100) +
            ($skorLog     * $s->bobot_log_kerja        / 100),
            2
        );

        return [
            'skor'             => $skorAdmin,
            'skor_laporan'     => $skorLaporan,
            'skor_log'         => $skorLog,
            'sesi_jadwal'      => $sesiJadwal,
            'sesi_terlaksana'  => $sesiTerlaksana,
            'sesi_dilaporkan'  => $sesiDilaporkan,
            'log_submitted'    => $logSubmitted,
            'log_diverifikasi' => $logDiverifikasi,
            'durasi_menit'     => $durasiMenit,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PREVIEW (tanpa simpan) — untuk UI dinamis
    // ═══════════════════════════════════════════════════════════════════════

    public function preview(TenagaPendidik $guru, int $bulan, int $tahun, SettingKinerja $setting): array
    {
        $mulai     = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai   = $mulai->copy()->endOfMonth();
        $hariKerja = $this->hitungHariKerja($mulai, $selesai, $guru->jamKerjaAktif(), $guru->id);

        $k1 = $this->komponenAbsensi($guru, $bulan, $tahun, $hariKerja, $setting);
        $k2 = $this->komponenTugas($guru, $mulai, $selesai, $setting);
        $k3 = $this->komponenAdministrasi($guru, $bulan, $tahun, $hariKerja, $mulai, $selesai, $setting);
        $kp = $this->komponenPiket($guru, $bulan, $tahun, (float) ($setting->skor_min_piket ?? 50));

        $skorDasar = ($k1['skor'] * $setting->bobot_absensi      / 100)
                   + ($k2['skor'] * $setting->bobot_tugas        / 100)
                   + ($k3['skor'] * $setting->bobot_administrasi / 100);
        // PIKET = penyesuaian (+/−) di atas skor dasar.
        $skorTotal = round(max(0, min(100, $skorDasar + $kp['penyesuaian'])), 2);

        return [
            'skor_total'   => $skorTotal,
            'skor_dasar'   => round($skorDasar, 2),
            'grade'        => $setting->getGrade($skorTotal),
            'label_grade'  => $setting->getLabelGrade($skorTotal),
            'badge_grade'  => $setting->getBadgeGrade($skorTotal),
            'komponen' => [
                'absensi' => [
                    'skor'       => $k1['skor'],
                    'bobot'      => $setting->bobot_absensi,
                    'kontribusi' => round($k1['skor'] * $setting->bobot_absensi / 100, 2),
                    'detail' => [
                        'skor_harian'   => $k1['skor_harian'],
                        'skor_mengajar' => $k1['skor_mengajar'],
                        'hadir'         => $k1['hadir'],
                        'terlambat'     => $k1['terlambat'],
                        'izin'          => $k1['izin'],
                        'sakit'         => $k1['sakit'],
                        'alfa'          => $k1['alfa'],
                        'dinas_luar'    => $k1['dinas_luar'],
                        'hari_kerja'    => $hariKerja,
                        'nilai_per_status' => [
                            'hadir'      => $setting->nilai_hadir,
                            'terlambat'  => $setting->nilai_terlambat,
                            'izin'       => $setting->nilai_izin,
                            'sakit'      => $setting->nilai_sakit,
                            'dinas_luar' => $setting->nilai_dinas_luar,
                            'alfa'       => $setting->nilai_alfa,
                        ],
                    ],
                ],
                'tugas' => [
                    'skor'       => $k2['skor'],
                    'bobot'      => $setting->bobot_tugas,
                    'kontribusi' => round($k2['skor'] * $setting->bobot_tugas / 100, 2),
                    'detail' => [
                        'skor_penugasan'    => $k2['skor_penugasan'],
                        'skor_jabatan'      => $k2['skor_jabatan'],
                        'penugasan_total'   => $k2['penugasan_total'],
                        'penugasan_selesai' => $k2['penugasan_selesai'],
                        'jabatan_total'     => $k2['jabatan_total'],
                        'jabatan_disetujui' => $k2['jabatan_disetujui'],
                    ],
                ],
                'administrasi' => [
                    'skor'       => $k3['skor'],
                    'bobot'      => $setting->bobot_administrasi,
                    'kontribusi' => round($k3['skor'] * $setting->bobot_administrasi / 100, 2),
                    'detail' => [
                        'skor_laporan'    => $k3['skor_laporan'],
                        'skor_log'        => $k3['skor_log'],
                        'sesi_jadwal'     => $k3['sesi_jadwal'],
                        'sesi_terlaksana' => $k3['sesi_terlaksana'],
                        'sesi_dilaporkan' => $k3['sesi_dilaporkan'],
                        'log_submitted'   => $k3['log_submitted'],
                    ],
                ],
                // PIKET sebagai penyesuaian (+/−), bukan komponen berbobot.
                'piket' => [
                    'penyesuaian'    => $kp['penyesuaian'],   // signed, langsung ditambahkan ke skor
                    'poin_apresiasi' => $kp['poin_apresiasi'],
                    'poin_catatan'   => $kp['poin_catatan'],
                    'apresiasi'      => $kp['apresiasi'],
                    'catatan'        => $kp['catatan'],
                ],
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 4: PIKET
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Skor penilaian piket: mulai baseline 100, lalu ± poin dari penilaian piket
     * (apresiasi +, catatan −), di-clamp [0,100]. Poin disimpan positif; jenis menentukan tanda.
     */
    private function komponenPiket(TenagaPendidik $guru, int $bulan, int $tahun, float $floor = 0): array
    {
        $penilaian = PiketPenilaian::where('guru_dinilai_id', $guru->id)
            ->where('status_sanggah', '!=', 'diterima') // sanggahan diterima = penilaian dibatalkan
            ->whereHas('jadwal', fn($q) => $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun))
            ->get(['jenis', 'poin', 'status_sanggah']);

        $apresiasi     = $penilaian->where('jenis', 'apresiasi');
        $catatan       = $penilaian->where('jenis', 'catatan');
        $poinApresiasi = (float) $apresiasi->sum('poin');
        $poinCatatan   = (float) $catatan->sum('poin');

        // Kegiatan Penting Guru (Sholat Dzuhur dll): hadir=+poin_hadir (apresiasi),
        // tidak_hadir=+poin_absen (catatan). Ikut penyesuaian piket.
        $kegiatan = \App\Models\AbsensiKegiatanPenting::where('absensi_kegiatan_penting.tenaga_pendidik_id', $guru->id)
            ->whereMonth('absensi_kegiatan_penting.tanggal', $bulan)
            ->whereYear('absensi_kegiatan_penting.tanggal', $tahun)
            ->join('kegiatan_penting', 'kegiatan_penting.id', '=', 'absensi_kegiatan_penting.kegiatan_penting_id')
            ->get(['absensi_kegiatan_penting.status', 'kegiatan_penting.poin_hadir', 'kegiatan_penting.poin_absen']);
        $poinApresiasi += (float) $kegiatan->where('status', 'hadir')->sum('poin_hadir');
        $poinCatatan   += (float) $kegiatan->where('status', 'tidak_hadir')->sum('poin_absen');

        // PIKET = PENYESUAIAN (+/−) di atas skor dasar (BUKAN komponen berbobot).
        // Guru piket "menunjang": apresiasi menambah, catatan mengurangi kinerja.
        // Pengurangan dari piket dibatasi maks (100 − skor_min_piket) agar tetap adil.
        $maxPotong   = max(0, 100 - min(100, $floor)); // $floor = skor_min_piket
        $penyesuaian = round(max(-$maxPotong, $poinApresiasi - $poinCatatan), 2);

        return [
            'penyesuaian'    => $penyesuaian,
            'poin_apresiasi' => round($poinApresiasi, 2),
            'poin_catatan'   => round($poinCatatan, 2),
            'apresiasi'      => $apresiasi->count(),
            'catatan'        => $catatan->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function hitungHariKerja(Carbon $mulai, Carbon $selesai, ?SettingJamKerja $jamKerja = null, ?int $tpId = null): int
    {
        // Hari kerja = hari kalender − HariLibur (nasional/pesantren/darurat) − libur
        // mingguan − libur individu guru mukim (bila $tpId diberikan).
        $liburSet = HariLibur::tanggalSetDalamRentang($mulai->toDateString(), $selesai->toDateString());
        $liburIndividuSet = $tpId
            ? \App\Models\LiburTendik::tanggalSetUntuk($tpId, $mulai->toDateString(), $selesai->toDateString())
            : [];
        $jamKerja = $jamKerja ?? SettingJamKerja::getDefault();

        $count = 0;
        $c   = $mulai->copy()->startOfDay();
        $end = $selesai->copy()->startOfDay();
        while ($c->lte($end)) {
            $tglStr        = $c->toDateString();
            $liburTanggal  = isset($liburSet[$tglStr]) || isset($liburIndividuSet[$tglStr]);
            $liburMingguan = $jamKerja ? $jamKerja->isHariLibur(TimezoneHelper::namaHariDB($c)) : false;
            if (!$liburTanggal && !$liburMingguan) $count++;
            $c->addDay();
        }
        return $count;
    }

    /** Jumlah minggu (ISO) yang tersentuh rentang — untuk target tugas mingguan. */
    private function hitungJumlahMinggu(Carbon $mulai, Carbon $selesai): int
    {
        $weeks = [];
        $c = $mulai->copy();
        while ($c->lte($selesai)) {
            $weeks[$c->isoFormat('GGGG-WW')] = true;
            $c->addDay();
        }
        return max(1, count($weeks));
    }

    /** Target occurrence tugas dalam 1 bulan berdasarkan frekuensi. */
    private function targetOccurrence(?string $frekuensi, int $hariKerja, int $jmlMinggu): int
    {
        return match ($frekuensi) {
            'harian'     => $hariKerja,
            'mingguan'   => $jmlMinggu,
            'bulanan'    => 1,
            'insidental' => 0,
            default      => 1,
        };
    }

    // Backward compat KinerjaJabatanService
    public function verifikasiLog(\App\Models\LogKerjaHarian $log, ?string $catatan = null): \App\Models\LogKerjaHarian
    {
        $log->update(['status' => 'diverifikasi', 'catatan_verifikasi' => $catatan,
            'diverifikasi_oleh' => auth()->id(), 'verified_at' => now()]);
        return $log;
    }

    public function tolakLog(\App\Models\LogKerjaHarian $log, string $catatan): \App\Models\LogKerjaHarian
    {
        $log->update(['status' => 'ditolak', 'catatan_verifikasi' => $catatan,
            'diverifikasi_oleh' => auth()->id(), 'verified_at' => now()]);
        return $log;
    }

    public function getRingkasanBulanIni(int $bulan, int $tahun): array
    {
        $rekaps  = RekapKinerjaBulanan::where('bulan', $bulan)->where('tahun', $tahun)->get();
        $setting = SettingKinerja::getDefault();
        return [
            'total_guru'             => TenagaPendidik::aktif()->count(),
            'sudah_ada_rekap'        => $rekaps->count(),
            'rata_skor_total'        => round($rekaps->avg('skor_total') ?? 0, 1),
            'rata_skor_absensi'      => round($rekaps->avg('skor_absensi') ?? 0, 1),
            'rata_skor_tugas'        => round($rekaps->avg('skor_tugas') ?? 0, 1),
            'rata_skor_administrasi' => round($rekaps->avg('skor_administrasi') ?? 0, 1),
            'guru_grade_a'           => $rekaps->filter(fn($r) => $r->skor_total >= $setting->grade_a)->count(),
            'guru_grade_b'           => $rekaps->filter(fn($r) => $r->skor_total >= $setting->grade_b && $r->skor_total < $setting->grade_a)->count(),
            'guru_perlu_perhatian'   => $rekaps->filter(fn($r) => $r->skor_total < $setting->grade_c)->count(),
            'log_pending'            => \App\Models\LogKerjaHarian::where('status', 'submitted')
                ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->count(),
        ];
    }
}