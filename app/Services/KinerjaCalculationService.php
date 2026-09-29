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
 * PENYESUAIAN = kedisiplinan kegiatan wajib (band persentase, ±maks_adj_kegiatan)
 *             + catatan/apresiasi guru piket (±1 per kejadian, ±maks_adj_piket),
 *               keduanya dibatasi lagi oleh ±maks_adj_total.
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
 *     → Sesi dilaporkan: kelas pelajaran = materi terisi; tahfidz/tahsin =
 *       absensi santri terisi (atau ada setoran/penilaian pada sesi itu)
 *     → skor = sesi_dilaporkan / sesi_jadwal_aktif × 100
 *
 *   Skor_Log (Log Kerja Harian):
 *     → skor = log_submitted / (hari_kerja × target_log) × 100
 *
 * ═══════════════════════════════════════════════════════════════════
 */
class KinerjaCalculationService
{
    /**
     * Batas hitung: hari kerja yang SUDAH BERJALAN (s/d kemarin), tidak pernah
     * melewati akhir bulan. Bulan lampau otomatis = sebulan penuh.
     * Dipakai bersama hitungRekap() dan preview() supaya angka di aplikasi guru
     * tidak pernah berbeda dengan angka di admin.
     */
    private function batasHitung(Carbon $mulai, Carbon $selesai): Carbon
    {
        $batas = Carbon::now()->copy()->subDay()->endOfDay();
        return $batas->gt($selesai) ? $selesai->copy() : $batas;
    }

    /**
     * SATU SUMBER perhitungan skor kinerja.
     *
     * Dulu hitungRekap() (admin) dan preview() (aplikasi guru) menghitung sendiri
     * dengan dua rumus berbeda: hitungRekap menormalisasi ke jumlah bobot inti,
     * preview membagi 100. Karena bobot inti tidak harus berjumlah 100 (mis. 85),
     * skor di aplikasi guru selalu tampak lebih rendah. Sekarang keduanya memakai
     * metode ini.
     */
    /**
     * Rentang tanggal yang dinilai untuk label bulan/tahun tertentu.
     *
     * Mengikuti JENDELA PERIODE PENGGAJIAN (mis. 25 Agustus–25 September untuk
     * label "September 2026"), bukan bulan kalender, supaya skor kinerja dan
     * slip gaji menilai rentang hari yang sama persis — termasuk punishment
     * kinerja yang memotong gaji periode tersebut. Bila periodenya belum dibuat,
     * jatuh kembali ke bulan kalender.
     *
     * @return array{0: Carbon, 1: Carbon, 2: bool} [mulai, selesai, ikutPeriode]
     */
    public function rentangPenilaian(int $bulan, int $tahun): array
    {
        $periode = \App\Models\PeriodePenggajian::untukLabel($bulan, $tahun);

        if ($periode?->tanggal_mulai && $periode->tanggal_selesai) {
            return [
                Carbon::parse($periode->tanggal_mulai)->startOfDay(),
                Carbon::parse($periode->tanggal_selesai)->endOfDay(),
                true,
            ];
        }

        $mulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        return [$mulai, $mulai->copy()->endOfMonth(), false];
    }

    private function susunKomponen(TenagaPendidik $guru, int $bulan, int $tahun, SettingKinerja $setting): array
    {
        [$mulai, $selesai] = $this->rentangPenilaian($bulan, $tahun);
        $batas   = $this->batasHitung($mulai, $selesai);

        // Awal bulan (belum ada hari berjalan) → 0 hari kerja → komponen netral.
        $hariKerja = $batas->lt($mulai)
            ? 0
            : $this->hitungHariKerja($mulai, $batas, $guru->jamKerjaAktif(), $guru->id);

        $k1 = $this->komponenAbsensi($guru, $mulai, $selesai, $hariKerja, $setting);
        $k2 = $this->komponenTugas($guru, $mulai, $selesai, $setting, $batas);
        $k3 = $this->komponenAdministrasi($guru, $hariKerja, $mulai, $selesai, $setting);
        $kp = $this->komponenPiket($guru, $mulai, $selesai, $setting);

        // Skor DASAR = rata-rata TERBOBOT 3 komponen inti, DINORMALISASI ke jumlah
        // bobotnya sendiri → guru sempurna selalu 100 walau bobot inti ≠ 100.
        $bobotInti = (float) ($setting->bobot_absensi + $setting->bobot_tugas + $setting->bobot_administrasi);
        $bobotInti = $bobotInti > 0 ? $bobotInti : 1;
        $skorDasar = (
              ($k1['skor'] * $setting->bobot_absensi)
            + ($k2['skor'] * $setting->bobot_tugas)
            + ($k3['skor'] * $setting->bobot_administrasi)
        ) / $bobotInti;

        // PIKET = penyesuaian (+/−) di atas skor dasar → total dibatasi [0..100].
        $skorTotal = round(max(0, min(100, $skorDasar + $kp['penyesuaian'])), 2);

        return [
            'k1' => $k1, 'k2' => $k2, 'k3' => $k3, 'kp' => $kp,
            'hari_kerja' => $hariKerja,
            'bobot_inti' => $bobotInti,
            'skor_dasar' => round($skorDasar, 2),
            'skor_total' => $skorTotal,
            'batas'      => $batas,
            'mulai'      => $mulai,
            'selesai'    => $selesai,
        ];
    }

    public function hitungRekap(TenagaPendidik $guru, int $bulan, int $tahun): RekapKinerjaBulanan
    {
        $rekap = RekapKinerjaBulanan::firstOrNew([
            'tenaga_pendidik_id' => $guru->id,
            'bulan'              => $bulan,
            'tahun'              => $tahun,
        ]);

        if ($rekap->exists && $rekap->sudah_dikunci) return $rekap;

        // Periode gaji bulan itu sudah dikunci → skor kinerjanya ikut beku.
        // Tanpa pagar ini, skor bulan lampau berubah hanya karena halaman dibuka.
        if ($rekap->exists && self::periodeTerkunci($bulan, $tahun)) return $rekap;

        $setting = SettingKinerja::getDefault();
        if (!$setting) {
            throw new \RuntimeException(
                'Setting kinerja belum dikonfigurasi. Hubungi administrator untuk membuat setting kinerja terlebih dahulu.'
            );
        }

        $h  = $this->susunKomponen($guru, $bulan, $tahun, $setting);
        $k1 = $h['k1']; $k2 = $h['k2']; $k3 = $h['k3']; $kp = $h['kp'];

        $rekap->fill([
            // Skor komponen
            'skor_absensi'              => $k1['skor'],
            'skor_tugas'                => $k2['skor'],
            'skor_administrasi'         => $k3['skor'],
            'skor_piket'                => $kp['penyesuaian'], // penyesuaian signed (+/−)
            'skor_total'                => $h['skor_total'],
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
            'total_hari_kerja'          => $h['hari_kerja'],
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
            // Penyebab skor tidak 100 — disimpan agar bulan lampau tetap bisa
            // dijelaskan ke guru, termasuk di riwayat perubahan.
            'faktor_penurunan'          => $this->faktorPenurunan($h, $setting),
            'dihitung_pada'             => now(),
            // Jendela yang dinilai — penting karena bisa mengikuti periode gaji
            // (mis. 25 Ags–25 Sep) alih-alih bulan kalender.
            'dinilai_dari'              => $h['mulai']->toDateString(),
            'dinilai_sampai'            => $h['selesai']->toDateString(),
        ]);

        $rekap->save();
        return $rekap;
    }

    /**
     * Bekukan kinerja satu periode saat penggajiannya difinalisasi.
     *
     * Dihitung sekali lagi sebagai angka final, lalu baris rekapnya dikunci
     * supaya tidak berubah lagi mengikuti data yang masuk belakangan. Nilai
     * lamanya tersimpan di riwayat dengan sebab 'finalisasi'. Admin masih bisa
     * membuka lewat reset beralasan bila memang perlu.
     *
     * @return int jumlah guru yang dibekukan
     */
    public function finalisasiPeriode(\App\Models\PeriodePenggajian $periode, ?int $aktor = null): int
    {
        $bulan = (int) $periode->bulan;
        $tahun = (int) $periode->tahun;
        $alasan = 'Finalisasi penggajian ' . $periode->nama_bulan;

        // Guru yang punya slip pada periode ini; bila belum ada, semua guru aktif.
        $guruIds = \App\Models\Penggajian::where('periode_penggajian_id', $periode->id)
            ->pluck('tenaga_pendidik_id')->unique();
        $guru = $guruIds->isNotEmpty()
            ? TenagaPendidik::whereIn('id', $guruIds)->get()
            : TenagaPendidik::aktif()->get();

        $n = 0;
        foreach ($guru as $g) {
            RekapKinerjaBulanan::tandaiPerubahan('finalisasi', $alasan, $aktor);
            $rekap = $this->hitungRekap($g, $bulan, $tahun);   // angka final

            if (!$rekap->exists || $rekap->sudah_dikunci) { if ($rekap->sudah_dikunci) $n++; continue; }

            RekapKinerjaBulanan::tandaiPerubahan('finalisasi', $alasan, $aktor);
            $rekap->update(['sudah_dikunci' => true]);
            $n++;
        }

        return $n;
    }

    /** True bila periode penggajian bulan/tahun itu sudah dikunci bendahara. */
    public static function periodeTerkunci(int $bulan, int $tahun): bool
    {
        return \App\Models\PeriodePenggajian::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereNotNull('dikunci_pada')
            ->exists();
    }

    /**
     * @param callable|null $sebelumTiapGuru Dipanggil sebelum tiap guru dihitung —
     *        dipakai pemanggil untuk menandai sebab perubahan (mis. reset massal),
     *        karena penanda di model bersifat sekali pakai per penyimpanan.
     */
    public function hitungRekapSemua(int $bulan, int $tahun, ?callable $sebelumTiapGuru = null): int
    {
        $setting = \App\Models\SettingKinerja::getDefault();
        $ambang  = (float) ($setting->grade_c ?? 0);
        $guru    = TenagaPendidik::aktif()->with('user')->get();

        foreach ($guru as $g) {
            if ($sebelumTiapGuru) $sebelumTiapGuru($g);
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
        TenagaPendidik $guru, Carbon $mulai, Carbon $selesai,
        int $hariKerja, SettingKinerja $s
    ): array {
        // Rentang tanggal (bukan bulan kalender) — mengikuti jendela periode gaji.
        $absensi = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
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
        // Aturan sesi yang dinilai ada di SesiMengajarService (satu sumber dengan
        // komponen administrasi): libur & izin netral, sesi yang dialihkan menjadi
        // tanggung jawab pengganti, dan inval yang tidak datang dihitung ke pengganti.
        $dinilai = app(\App\Services\SesiMengajarService::class)->sesiDinilaiKinerja(
            $guru->id, $mulai->toDateString(), $selesai->toDateString(),
        );

        $sesiJadwalBulan = $dinilai->count();
        $sesiTerlaksana  = $dinilai->where('terlaksana', true)->count();
        $jpJadwal        = (int) $dinilai->sum('jp_jadwal');
        $jpTerlaksana    = (int) $dinilai->where('terlaksana', true)
            ->sum(fn ($x) => (int) $x['absensi']->jp_terlaksana);

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
            'hari_dinilai'  => $hariDinilai,
            'sesi_jadwal'   => $sesiJadwalBulan,
            'sesi_terlaksana' => $sesiTerlaksana,
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
        SettingKinerja $s,
        ?Carbon $batas = null
    ): array {
        // ── Sub 2a: Penugasan Tambahan ──────────────────────────────────────
        $penugasan = PenugasanTambahan::where('tenaga_pendidik_id', $guru->id)
            ->whereHas('tugasTambahan', fn($q) =>
                $q->where('tanggal_mulai', '<=', $selesai)
                  ->where(fn($q2) => $q2->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $mulai))
            )->get();

        // Tugas yang BELUM jatuh tempo tidak boleh menghukum: dulu tugas dengan
        // tenggat hari ini pun sudah memotong skor sejak hari pertama periode.
        // Yang dinilai hanya tugas yang sudah selesai (apa pun tenggatnya) atau
        // yang tenggatnya sudah lewat / diputuskan tidak terlaksana.
        // Acuan "sudah jatuh tempo": hari kerja yang sudah berjalan (batas hitung).
        // Untuk periode lampau nilainya = akhir jendela, jadi maknanya tetap;
        // untuk periode berjalan = kemarin, sehingga tugas yang tenggatnya masih
        // di depan (bahkan hari ini) belum dihukum.
        $acuanTenggat = ($batas && $batas->lt($selesai) ? $batas : $selesai)->toDateString();

        $dinilai = $penugasan->filter(function ($p) use ($acuanTenggat) {
            if ($p->status_pengerjaan === 'selesai') return true;
            if ($p->tidakTerlaksana()) return true;
            return $p->lewatTenggat($acuanTenggat);
        });

        $penugasanTotal   = $dinilai->count();
        $penugasanSelesai = $dinilai
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

        // Target dihitung sampai hari yang SUDAH BERJALAN — di tengah bulan guru
        // tidak dituntut memenuhi target hari yang belum datang.
        $batasTarget = $batas && $batas->lt($selesai) ? $batas : $selesai;
        $hariKerja = $batasTarget->lt($mulai)
            ? 0
            : $this->hitungHariKerja($mulai, $batasTarget, $guru->jamKerjaAktif(), $guru->id);
        $jmlMinggu = $batasTarget->lt($mulai) ? 0 : $this->hitungJumlahMinggu($mulai, $batasTarget);

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
            // Belum jatuh tempo → tidak dinilai (netral), hanya keterangan.
            'penugasan_ditunda'  => $penugasan->count() - $penugasanTotal,
            'jabatan_total'      => $realisasiTotal,
            'jabatan_disetujui'  => $realisasiDisetujui,
            // untuk penjelasan penyebab ke guru
            'jabatan_target'     => $targetTotal,
            'jabatan_terpenuhi'  => $terpenuhiTotal,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 3: ADMINISTRASI
    // ═══════════════════════════════════════════════════════════════════════

    private function komponenAdministrasi(
        TenagaPendidik $guru,
        int $hariKerja, Carbon $mulai, Carbon $selesai,
        SettingKinerja $s
    ): array {
        // ── Sub 3a: Laporan Mengajar ─────────────────────────────────────────
        // Sesi dianggap DILAPORKAN bila pertanggungjawabannya ada — dan bentuk
        // pertanggungjawaban itu BERBEDA per jenis pembelajaran:
        //
        //   sekolah/mapel : materi (jurnal) terisi
        //   tahfidz/tahsin: roster absensi santri terisi, atau ada catatan
        //                   setoran/penilaian pada sesi tersebut
        //
        // Sebelumnya semua sesi dituntut memiliki `materi`, padahal alur tahfidz
        // & tahsin tidak pernah mengisi kolom itu (guru mencatat setoran per
        // santri). Akibatnya 100% sesi tahfidz/tahsin dianggap tidak dilaporkan
        // dan skor administrasi pengampunya jatuh ke 0 meski rosternya lengkap.
        $dinilai = app(\App\Services\SesiMengajarService::class)->sesiDinilaiKinerja(
            $guru->id, $mulai->toDateString(), $selesai->toDateString()
        );

        $sesiJadwal     = $dinilai->count();
        $terlaksana     = $dinilai->where('terlaksana', true);
        $sesiTerlaksana = $terlaksana->count();

        $bukti = $this->buktiLaporanSesi($terlaksana->pluck('absensi'));

        $sesiDilaporkan = $terlaksana->filter(fn ($x) => $bukti['dilaporkan']->has($x['absensi']->id))->count();

        // Skor laporan: sesi terlaksana yang buktinya ada, dibanding seluruh
        // sesi yang dinilai. Tanpa jadwal = sempurna.
        $skorLaporan = $sesiJadwal > 0
            ? min(100, round($sesiDilaporkan / $sesiJadwal * 100, 2))
            : 100;

        // ── Sub 3b: Log Kerja Harian ─────────────────────────────────────────
        $logs = LogKerjaHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
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
            'target_log'       => $logs->count() > 0 ? $targetLog : 0,
            // Rincian bukti yang kurang, dipisah per jenis pembelajaran supaya
            // penjelasan ke guru menyebut hal yang benar-benar harus diisi.
            'belum_materi'     => $bukti['belum_materi'],
            'belum_roster'     => $bukti['belum_roster'],
            'sesi_quran'       => $bukti['sesi_quran'],
            'sesi_mapel'       => $bukti['sesi_mapel'],
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
        $h  = $this->susunKomponen($guru, $bulan, $tahun, $setting);
        $k1 = $h['k1']; $k2 = $h['k2']; $k3 = $h['k3']; $kp = $h['kp'];
        $skorTotal = $h['skor_total'];

        // Kontribusi = skor komponen × bobotnya, dinormalisasi ke jumlah bobot inti
        // (sama seperti perhitungan skor dasar) supaya jumlah kontribusi = skor dasar.
        $kontribusi = fn (float $skor, float $bobot) => round($skor * $bobot / $h['bobot_inti'], 2);

        return [
            'skor_total'   => $skorTotal,
            'skor_dasar'   => $h['skor_dasar'],
            'grade'        => $setting->getGrade($skorTotal),
            'label_grade'  => $setting->getLabelGrade($skorTotal),
            'badge_grade'  => $setting->getBadgeGrade($skorTotal),
            // Penjelasan "kenapa skor saya tidak 100"
            'faktor'       => $this->faktorPenurunan($h, $setting),
            'komponen' => [
                'absensi' => [
                    'skor'       => $k1['skor'],
                    'bobot'      => $setting->bobot_absensi,
                    'kontribusi' => $kontribusi((float) $k1['skor'], (float) $setting->bobot_absensi),
                    'detail' => [
                        'skor_harian'   => $k1['skor_harian'],
                        'skor_mengajar' => $k1['skor_mengajar'],
                        'hadir'         => $k1['hadir'],
                        'terlambat'     => $k1['terlambat'],
                        'izin'          => $k1['izin'],
                        'sakit'         => $k1['sakit'],
                        'alfa'          => $k1['alfa'],
                        'dinas_luar'    => $k1['dinas_luar'],
                        'hari_kerja'    => $h['hari_kerja'],
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
                    'kontribusi' => $kontribusi((float) $k2['skor'], (float) $setting->bobot_tugas),
                    'detail' => [
                        'skor_penugasan'    => $k2['skor_penugasan'],
                        'skor_jabatan'      => $k2['skor_jabatan'],
                        'penugasan_total'   => $k2['penugasan_total'],
                        'penugasan_selesai' => $k2['penugasan_selesai'],
                        'penugasan_ditunda' => $k2['penugasan_ditunda'] ?? 0,
                        'jabatan_total'     => $k2['jabatan_total'],
                        'jabatan_disetujui' => $k2['jabatan_disetujui'],
                        'jabatan_target'    => $k2['jabatan_target'] ?? 0,
                        'jabatan_terpenuhi' => $k2['jabatan_terpenuhi'] ?? 0,
                    ],
                ],
                'administrasi' => [
                    'skor'       => $k3['skor'],
                    'bobot'      => $setting->bobot_administrasi,
                    'kontribusi' => $kontribusi((float) $k3['skor'], (float) $setting->bobot_administrasi),
                    'detail' => [
                        'skor_laporan'    => $k3['skor_laporan'],
                        'skor_log'        => $k3['skor_log'],
                        'sesi_jadwal'     => $k3['sesi_jadwal'],
                        'sesi_terlaksana' => $k3['sesi_terlaksana'],
                        'sesi_dilaporkan' => $k3['sesi_dilaporkan'],
                        'sesi_quran'      => $k3['sesi_quran'] ?? 0,
                        'sesi_mapel'      => $k3['sesi_mapel'] ?? 0,
                        'belum_materi'    => $k3['belum_materi'] ?? 0,
                        'belum_roster'    => $k3['belum_roster'] ?? 0,
                        'log_submitted'   => $k3['log_submitted'],
                        'target_log'      => $k3['target_log'] ?? 0,
                    ],
                ],
                // PIKET sebagai penyesuaian (+/−), bukan komponen berbobot.
                'piket' => [
                    'penyesuaian'    => $kp['penyesuaian'],   // signed, langsung ditambahkan ke skor
                    // Rincian dua sumber + batas yang berlaku (transparansi)
                    'adj_kegiatan'   => $kp['adj_kegiatan'],
                    'adj_piket'      => $kp['adj_piket'],
                    'kegiatan_hadir' => $kp['kegiatan_hadir'],
                    'kegiatan_total' => $kp['kegiatan_total'],
                    'kegiatan_izin'  => $kp['kegiatan_izin'],
                    'kegiatan_persen'=> $kp['kegiatan_persen'],
                    'kegiatan_band'  => $kp['kegiatan_band'],
                    'maks_kegiatan'  => $kp['maks_kegiatan'],
                    'maks_piket'     => $kp['maks_piket'],
                    'maks_total'     => $kp['maks_total'],
                    'min_kesempatan' => $kp['min_kesempatan'],
                    'poin_apresiasi' => $kp['poin_apresiasi'],
                    'poin_catatan'   => $kp['poin_catatan'],
                    'apresiasi'      => $kp['apresiasi'],
                    'catatan'        => $kp['catatan'],
                ],
            ],
        ];
    }

    /**
     * Tentukan sesi mana yang sudah "dilaporkan", sesuai jenis pembelajarannya.
     *
     * Bukti yang diterima:
     *   - kelas sekolah/mapel : kolom `materi` terisi
     *   - kelas tahfidz/tahsin: roster absensi santri terisi, ATAU ada catatan
     *     setoran tahfidz / penilaian tahsin pada sesi itu (materi tetap
     *     diterima bila kebetulan diisi)
     *
     * @param  \Illuminate\Support\Collection $absensiList koleksi AbsensiMengajar
     * @return array{dilaporkan: \Illuminate\Support\Collection, belum_materi: int,
     *               belum_roster: int, sesi_quran: int, sesi_mapel: int}
     */
    private function buktiLaporanSesi($absensiList): array
    {
        $kosong = [
            'dilaporkan'   => collect(),
            'belum_materi' => 0, 'belum_roster' => 0,
            'sesi_quran'   => 0, 'sesi_mapel'   => 0,
        ];
        if ($absensiList->isEmpty()) return $kosong;

        $ids = $absensiList->pluck('id')->all();

        // Jenis kelas per sesi (satu query, bukan per baris).
        $jenisPerSesi = \Illuminate\Support\Facades\DB::table('absensi_mengajar as am')
            ->join('jadwal_mengajar as jm', 'jm.id', '=', 'am.jadwal_mengajar_id')
            ->join('kelas as k', 'k.id', '=', 'jm.kelas_id')
            ->whereIn('am.id', $ids)
            ->pluck('k.jenis', 'am.id');

        // Bukti khas pembelajaran Qur'an: roster santri & catatan setoran/nilai.
        $adaRoster  = \Illuminate\Support\Facades\DB::table('absensi_santri')
            ->whereIn('absensi_mengajar_id', $ids)->distinct()->pluck('absensi_mengajar_id')->flip();
        $adaSetoran = \Illuminate\Support\Facades\DB::table('setoran_tahfidz')
            ->whereIn('absensi_mengajar_id', $ids)->distinct()->pluck('absensi_mengajar_id')->flip();
        $adaNilai   = \Illuminate\Support\Facades\DB::table('tahsin_penilaian')
            ->whereIn('absensi_mengajar_id', $ids)->distinct()->pluck('absensi_mengajar_id')->flip();

        $hasil = $kosong;
        foreach ($absensiList as $a) {
            $jenis     = $jenisPerSesi[$a->id] ?? null;
            $adaMateri = trim((string) ($a->materi ?? '')) !== '';

            if (in_array($jenis, ['tahfidz', 'tahsin'], true)) {
                $hasil['sesi_quran']++;
                $ok = $adaRoster->has($a->id) || $adaSetoran->has($a->id) || $adaNilai->has($a->id) || $adaMateri;
                if ($ok) $hasil['dilaporkan'][$a->id] = true;
                else     $hasil['belum_roster']++;
                continue;
            }

            $hasil['sesi_mapel']++;
            if ($adaMateri) $hasil['dilaporkan'][$a->id] = true;
            else            $hasil['belum_materi']++;
        }

        return $hasil;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PENYEBAB SKOR TIDAK 100 — dijelaskan apa adanya ke guru
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Daftar penyebab konkret beserta dampaknya dalam POIN SKOR TOTAL, supaya
     * guru tidak hanya melihat angka turun tapi tahu sebabnya dan apa yang
     * bisa diperbaiki. Dampak dihitung dari kekurangan tiap sub-komponen
     * × bobot sub × bobot komponen ÷ jumlah bobot inti, jadi jumlah seluruh
     * dampak ≈ (100 − skor dasar).
     */
    public function faktorPenurunan(array $h, SettingKinerja $s): array
    {
        $k1 = $h['k1']; $k2 = $h['k2']; $k3 = $h['k3']; $kp = $h['kp'];
        $bi = (float) $h['bobot_inti'];
        $f  = [];

        // dampak sub-komponen terhadap skor total
        $dampak = fn (float $skorSub, float $bobotSub, float $bobotKomponen) =>
            round((100 - min(100, $skorSub)) * ($bobotSub / 100) * ($bobotKomponen / $bi), 2);

        // ── Absensi harian ────────────────────────────────────────────────
        $d = $dampak((float) $k1['skor_harian'], (float) $s->bobot_absensi_harian, (float) $s->bobot_absensi);
        if ($d > 0) {
            $rincian = [];
            if ($k1['alfa'] > 0)      $rincian[] = "{$k1['alfa']}× alfa (dinilai {$s->nilai_alfa})";
            if ($k1['terlambat'] > 0) $rincian[] = "{$k1['terlambat']}× terlambat (dinilai {$s->nilai_terlambat})";
            if ($k1['izin'] > 0)      $rincian[] = "{$k1['izin']}× izin (dinilai {$s->nilai_izin})";
            if ($k1['sakit'] > 0)     $rincian[] = "{$k1['sakit']}× sakit (dinilai {$s->nilai_sakit})";
            $f[] = [
                'komponen' => 'Absensi harian',
                'sebab'    => $rincian ? implode(', ', $rincian) : 'ada hari kerja yang tidak bernilai penuh',
                'angka'    => "hadir {$k1['hadir']} dari {$k1['hari_dinilai']} hari tercatat",
                'saran'    => 'Hadir tepat waktu; izin/sakit tetap dinilai di bawah hadir.',
                'dampak'   => $d,
            ];
        }

        // ── Absensi mengajar (sesi terlaksana) ────────────────────────────
        $d = $dampak((float) $k1['skor_mengajar'], (float) $s->bobot_absensi_mengajar, (float) $s->bobot_absensi);
        if ($d > 0) {
            $tidak = max(0, (int) $k1['sesi_jadwal'] - (int) $k1['sesi_terlaksana']);
            $f[] = [
                'komponen' => 'Sesi mengajar',
                'sebab'    => "{$tidak} sesi tidak terlaksana",
                'angka'    => "terlaksana {$k1['sesi_terlaksana']} dari {$k1['sesi_jadwal']} sesi yang dinilai",
                'saran'    => 'Isi absen mengajar tiap sesi; sesi yang dialihkan ke pengganti tidak dihitung ke Anda.',
                'dampak'   => $d,
            ];
        }

        // ── Tugas tambahan ────────────────────────────────────────────────
        $d = $dampak((float) $k2['skor_penugasan'], (float) $s->bobot_tugas_tambahan, (float) $s->bobot_tugas);
        if ($d > 0) {
            $sisa = max(0, (int) $k2['penugasan_total'] - (int) $k2['penugasan_selesai']);
            $f[] = [
                'komponen' => 'Tugas tambahan',
                'sebab'    => $k2['penugasan_total'] > 0
                    ? "{$sisa} tugas belum selesai atau belum disetujui admin"
                    : 'belum ada tugas tambahan yang tercatat',
                'angka'    => "selesai {$k2['penugasan_selesai']} dari {$k2['penugasan_total']} tugas yang sudah jatuh tempo"
                    . (($k2['penugasan_ditunda'] ?? 0) > 0
                        ? " ({$k2['penugasan_ditunda']} tugas belum jatuh tempo, belum dihitung)" : ''),
                'saran'    => 'Selesaikan tugas lalu laporkan agar bisa disetujui admin.',
                'dampak'   => $d,
            ];
        }

        // ── Tugas jabatan (frekuensi) ─────────────────────────────────────
        $d = $dampak((float) $k2['skor_jabatan'], (float) $s->bobot_tugas_jabatan, (float) $s->bobot_tugas);
        if ($d > 0) {
            $target    = (int) ($k2['jabatan_target'] ?? 0);
            $terpenuhi = (int) ($k2['jabatan_terpenuhi'] ?? 0);
            $f[] = [
                'komponen' => 'Tugas jabatan',
                'sebab'    => $target > 0
                    ? (($target - $terpenuhi) . ' target tugas jabatan belum terpenuhi')
                    : 'realisasi tugas jabatan belum tercatat',
                'angka'    => "terpenuhi {$terpenuhi} dari {$target} target sampai hari ini",
                'saran'    => 'Catat realisasi tugas jabatan sesuai frekuensinya (harian/mingguan/bulanan).',
                'dampak'   => $d,
            ];
        }

        // ── Laporan mengajar (materi/jurnal) ──────────────────────────────
        $d = $dampak((float) $k3['skor_laporan'], (float) $s->bobot_laporan_mengajar, (float) $s->bobot_administrasi);
        if ($d > 0) {
            $belum        = max(0, (int) $k3['sesi_jadwal'] - (int) $k3['sesi_dilaporkan']);
            $belumMateri  = (int) ($k3['belum_materi'] ?? 0);
            $belumRoster  = (int) ($k3['belum_roster'] ?? 0);

            // Bentuk bukti berbeda per jenis pembelajaran, jadi sebabnya ditulis
            // sesuai yang benar-benar harus diisi guru bersangkutan.
            $rincian = [];
            if ($belumMateri > 0) $rincian[] = "{$belumMateri} sesi pelajaran belum ada materi/jurnal";
            if ($belumRoster > 0) $rincian[] = "{$belumRoster} sesi tahfidz/tahsin belum ada absensi santrinya";
            $sisa = $belum - $belumMateri - $belumRoster;
            if ($sisa > 0) $rincian[] = "{$sisa} sesi belum terlaksana/dilaporkan";

            $saran = $belumRoster > 0 && $belumMateri === 0
                ? 'Isi absensi santri tiap pertemuan tahfidz/tahsin — itu yang dihitung, bukan materi.'
                : ($belumRoster > 0
                    ? 'Kelas pelajaran: isi materi/jurnal. Tahfidz & tahsin: cukup isi absensi santri (atau catatan setoran).'
                    : 'Isi materi/jurnal setiap selesai mengajar — absen saja belum dihitung.');

            $f[] = [
                'komponen' => 'Laporan mengajar',
                'sebab'    => $rincian ? implode(' · ', $rincian) : "{$belum} sesi belum dilaporkan",
                'angka'    => "dilaporkan {$k3['sesi_dilaporkan']} dari {$k3['sesi_jadwal']} sesi"
                    . (($k3['sesi_quran'] ?? 0) > 0
                        ? " ({$k3['sesi_quran']} sesi tahfidz/tahsin dinilai dari absensi santri)" : ''),
                'saran'    => $saran,
                'dampak'   => $d,
            ];
        }

        // ── Log kerja harian ──────────────────────────────────────────────
        $d = $dampak((float) $k3['skor_log'], (float) $s->bobot_log_kerja, (float) $s->bobot_administrasi);
        if ($d > 0) {
            $target = (int) ($k3['target_log'] ?? 0);
            $f[] = [
                'komponen' => 'Log kerja harian',
                'sebab'    => $target > 0
                    ? (max(0, $target - (int) $k3['log_submitted']) . ' log kerja belum diisi')
                    : 'log kerja belum diisi',
                'angka'    => "terisi {$k3['log_submitted']}" . ($target > 0 ? " dari target {$target}" : ''),
                'saran'    => 'Isi log kerja harian; target ' . $s->target_log_per_hari . ' log per hari kerja.',
                'dampak'   => $d,
            ];
        }

        // ── Kedisiplinan kegiatan wajib (penyesuaian berbatas) ────────────
        if ((float) ($kp['adj_kegiatan'] ?? 0) < 0) {
            $tidakHadir = max(0, (int) $kp['kegiatan_total'] - (int) $kp['kegiatan_hadir']);
            $f[] = [
                'komponen' => 'Kedisiplinan kegiatan wajib',
                'sebab'    => "kehadiran {$kp['kegiatan_persen']}% tergolong {$kp['kegiatan_band']}"
                    . " ({$tidakHadir} kali tidak hadir)",
                'angka'    => "hadir {$kp['kegiatan_hadir']} dari {$kp['kegiatan_total']} kegiatan"
                    . (($kp['kegiatan_izin'] ?? 0) > 0 ? " ({$kp['kegiatan_izin']} izin tidak dihitung)" : '')
                    . " · batas penyesuaian ±{$kp['maks_kegiatan']}",
                'saran'    => 'Ikuti kegiatan wajib (sholat berjamaah dll); penilaian memakai persentase, bukan jumlah kejadian.',
                'dampak'   => round(abs((float) $kp['adj_kegiatan']), 2),
            ];
        }

        // ── Catatan guru piket (penyesuaian berbatas) ─────────────────────
        if ((float) ($kp['adj_piket'] ?? 0) < 0) {
            $f[] = [
                'komponen' => 'Catatan guru piket',
                // Poin catatan datang dari DUA sumber: penilaian guru piket dan
                // ketidakhadiran di kegiatan wajib. Jadi jumlah catatan piket bisa 0
                // sementara poinnya tetap besar — sebabnya ditulis apa adanya.
                'sebab'    => "{$kp['catatan']} catatan dari guru piket",
                'angka'    => "{$kp['apresiasi']} apresiasi · {$kp['catatan']} catatan · batas penyesuaian ±{$kp['maks_piket']}",
                'saran'    => 'Catatan piket bisa disanggah bila tidak sesuai; sanggahan yang diterima membatalkan catatan.',
                'dampak'   => round(abs((float) $kp['adj_piket']), 2),
            ];
        }

        usort($f, fn ($a, $b) => $b['dampak'] <=> $a['dampak']);
        return $f;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // KOMPONEN 4: PIKET
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Skor penilaian piket: mulai baseline 100, lalu ± poin dari penilaian piket
     * (apresiasi +, catatan −), di-clamp [0,100]. Poin disimpan positif; jenis menentukan tanda.
     */
    private function komponenPiket(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai, SettingKinerja $s): array
    {
        $dari   = $mulai->toDateString();
        $sampai = $selesai->toDateString();

        // ── A. Kedisiplinan kegiatan wajib (sholat berjamaah dll) ────────────
        // Dinilai dari PERSENTASE kehadiran bulan itu, bukan jumlah kejadian,
        // supaya guru dengan 10 kesempatan dan 42 kesempatan sebanding.
        $kegiatan = \App\Models\AbsensiKegiatanPenting::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get(['status']);

        // IZIN bersifat NETRAL: dikeluarkan dari penyebut, jadi tidak menolong
        // dan tidak menghukum (keputusan kebijakan 28 Sep 2026).
        $izinKegiatan = $kegiatan->where('status', 'izin')->count();
        $kesempatan   = $kegiatan->whereIn('status', ['hadir', 'tidak_hadir'])->count();
        $hadir        = $kegiatan->where('status', 'hadir')->count();
        $rasio      = $kesempatan > 0 ? $hadir / $kesempatan : null;
        $maksKeg    = (float) ($s->maks_adj_kegiatan ?? 6);
        $minSampel  = (int) ($s->min_kesempatan_kegiatan ?? 5);

        // Sampel terlalu kecil → jangan menilai. Satu-dua catatan tidak cukup
        // untuk menyimpulkan kedisiplinan sebulan.
        $adjKegiatan = 0.0;
        $bandKegiatan = 'belum dinilai';
        if ($rasio !== null && $kesempatan >= $minSampel) {
            $persen = $rasio * 100;
            [$adjKegiatan, $bandKegiatan] = match (true) {
                $persen >= (int) ($s->ambang_kegiatan_baik   ?? 90) => [ $maksKeg * 0.5,  'sangat baik'],
                $persen >= (int) ($s->ambang_kegiatan_cukup  ?? 75) => [ $maksKeg * 0.25, 'baik'],
                $persen >= (int) ($s->ambang_kegiatan_netral ?? 60) => [ 0.0,             'cukup'],
                $persen >= (int) ($s->ambang_kegiatan_kurang ?? 40) => [-$maksKeg * 0.5,  'kurang'],
                default                                             => [-$maksKeg,        'sangat kurang'],
            };
            $adjKegiatan = round($adjKegiatan, 2);
        }

        // ── B. Catatan / apresiasi guru piket ────────────────────────────────
        // Satu kejadian = 1 poin (bukan nilai rubrik), lalu dibatasi. Besar-kecil
        // pengaruhnya diatur lewat maks_adj_piket, bukan lewat poin per kejadian,
        // agar satu catatan tidak langsung memakan seluruh batas.
        $penilaian = PiketPenilaian::where('guru_dinilai_id', $guru->id)
            ->where('status_sanggah', '!=', 'diterima') // sanggahan diterima = dibatalkan
            ->whereHas('jadwal', fn ($q) => $q->whereBetween('tanggal', [$dari, $sampai]))
            ->get(['jenis', 'poin', 'status_sanggah']);

        $apresiasi = $penilaian->where('jenis', 'apresiasi')->count();
        $catatan   = $penilaian->where('jenis', 'catatan')->count();
        $maksPiket = (float) ($s->maks_adj_piket ?? 4);
        $adjPiket  = round(max(-$maksPiket, min($maksPiket, $apresiasi - $catatan)), 2);

        // ── C. Gabungan, tetap berbatas ──────────────────────────────────────
        $maksTotal   = (float) ($s->maks_adj_total ?? 10);
        $penyesuaian = round(max(-$maksTotal, min($maksTotal, $adjKegiatan + $adjPiket)), 2);

        return [
            'penyesuaian'     => $penyesuaian,
            // Rincian per sumber — ditampilkan terpisah ke guru & admin supaya
            // jelas mana soal kehadiran kegiatan dan mana penilaian guru piket.
            'adj_kegiatan'    => $adjKegiatan,
            'adj_piket'       => $adjPiket,
            'kegiatan_hadir'  => $hadir,
            'kegiatan_total'  => $kesempatan,
            'kegiatan_izin'   => $izinKegiatan,
            'kegiatan_persen' => $rasio !== null ? round($rasio * 100, 1) : null,
            'kegiatan_band'   => $bandKegiatan,
            'apresiasi'       => $apresiasi,
            'catatan'         => $catatan,
            'maks_kegiatan'   => $maksKeg,
            'maks_piket'      => $maksPiket,
            'maks_total'      => $maksTotal,
            'min_kesempatan'  => $minSampel,
            // Warisan agar pemakai lama tidak pecah (dulu berisi jumlah poin).
            'poin_apresiasi'  => $apresiasi,
            'poin_catatan'    => $catatan,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function hitungHariKerja(Carbon $mulai, Carbon $selesai, ?SettingJamKerja $jamKerja = null, ?int $tpId = null): int
    {
        // Hari kerja = hari kalender − HariLibur (nasional/pesantren/darurat) − libur
        // mingguan − libur individu guru mukim (bila $tpId diberikan).
        // Dibebaskan absen harian → tidak punya hari kerja harian; target tugas
        // berfrekuensi harian & target log ikut nol, skor absensi harian netral.
        if ($tpId && !(TenagaPendidik::find($tpId)?->wajibAbsenHarian() ?? true)) return 0;

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