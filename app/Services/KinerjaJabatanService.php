<?php

namespace App\Services;

use App\Models\TenagaPendidik;
use App\Models\LogKerjaHarian;
use App\Models\RekapKinerjaBulanan;
use App\Models\PenugasanTambahan;
use App\Models\AbsensiHarian;
use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KinerjaJabatanService
{
    /**
     * Hitung & simpan rekap kinerja satu guru untuk bulan tertentu.
     */
    public function hitungRekap(TenagaPendidik $guru, int $bulan, int $tahun): RekapKinerjaBulanan
    {
        $rekap = RekapKinerjaBulanan::firstOrNew([
            'tenaga_pendidik_id' => $guru->id,
            'bulan'  => $bulan,
            'tahun'  => $tahun,
        ]);

        if ($rekap->sudah_dikunci) {
            return $rekap; // tidak recalculate jika sudah dikunci
        }

        // ── Log kerja ─────────────────────────────────────────────────────────
        $logs = LogKerjaHarian::where('tenaga_pendidik_id', $guru->id)
            ->byBulan($bulan, $tahun)
            ->get();

        $totalSubmitted   = $logs->whereIn('status', ['submitted', 'diverifikasi'])->count();
        $totalDiverifikasi = $logs->where('status', 'diverifikasi')->count();
        $totalDurasi      = $logs->sum('durasi_menit');

        // ── Penugasan tambahan ────────────────────────────────────────────────
        $penugasan = PenugasanTambahan::where('tenaga_pendidik_id', $guru->id)
            ->whereHas('tugasTambahan', function ($q) use ($bulan, $tahun) {
                $q->whereMonth('tanggal_mulai', $bulan)
                  ->whereYear('tanggal_mulai', $tahun)
                  ->where('status', 'aktif');
            })
            ->get();

        $totalPenugasanDiterima = $penugasan->count();
        $totalPenugasanSelesai  = $penugasan->where('status_pengerjaan', 'selesai')->count();

        // ── Hitung hari kerja aktif bulan ini ─────────────────────────────────
        $hariKerjaAktif = $this->hitungHariKerjaAktif($guru, $bulan, $tahun);

        // ── Skor Keaktifan: log submitted per hari kerja ──────────────────────
        // Skema: guru yang aktif tiap hari kerja = 100
        $skorKeaktifan = $hariKerjaAktif > 0
            ? min(100, round(($totalSubmitted / $hariKerjaAktif) * 100, 2))
            : 0;

        // ── Skor Penugasan: penugasan selesai / diterima ──────────────────────
        $skorPenugasan = $totalPenugasanDiterima > 0
            ? round(($totalPenugasanSelesai / $totalPenugasanDiterima) * 100, 2)
            : 100; // jika tidak ada penugasan, skor penuh (tidak dihukum)

        // ── Skor Total: 60% keaktifan + 40% penugasan ─────────────────────────
        $skorTotal = round(($skorKeaktifan * 0.60) + ($skorPenugasan * 0.40), 2);

        $rekap->fill([
            'total_log_submitted'      => $totalSubmitted,
            'total_log_diverifikasi'   => $totalDiverifikasi,
            'total_durasi_menit'       => $totalDurasi,
            'total_penugasan_diterima' => $totalPenugasanDiterima,
            'total_penugasan_selesai'  => $totalPenugasanSelesai,
            'skor_keaktifan'           => $skorKeaktifan,
            'skor_penugasan'           => $skorPenugasan,
            'skor_total'               => $skorTotal,
        ]);

        $rekap->save();

        return $rekap;
    }

    /**
     * Hitung rekap untuk semua guru aktif bulan tertentu.
     */
    public function hitungRekapSemua(int $bulan, int $tahun): int
    {
        $guru = TenagaPendidik::aktif()->get();
        foreach ($guru as $g) {
            $this->hitungRekap($g, $bulan, $tahun);
        }
        return $guru->count();
    }

    /**
     * Verifikasi log kerja (disetujui).
     */
    public function verifikasiLog(LogKerjaHarian $log, ?string $catatan = null): LogKerjaHarian
    {
        $log->update([
            'status'              => 'diverifikasi',
            'catatan_verifikasi'  => $catatan,
            'diverifikasi_oleh'   => auth()->id(),
            'verified_at'         => now(),
        ]);

        return $log;
    }

    /**
     * Tolak log kerja.
     */
    public function tolakLog(LogKerjaHarian $log, string $catatan): LogKerjaHarian
    {
        $log->update([
            'status'             => 'ditolak',
            'catatan_verifikasi' => $catatan,
            'diverifikasi_oleh'  => auth()->id(),
            'verified_at'        => now(),
        ]);

        return $log;
    }

    /**
     * Ambil ringkasan kinerja untuk ditampilkan di dashboard.
     */
    public function getRingkasanBulanIni(int $bulan, int $tahun): array
    {
        $rekaps = RekapKinerjaBulanan::with('tenagaPendidik.user')
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->get();

        $totalGuru = TenagaPendidik::aktif()->count();

        return [
            'total_guru'         => $totalGuru,
            'sudah_ada_rekap'    => $rekaps->count(),
            'rata_skor_total'    => $rekaps->avg('skor_total') ?? 0,
            'rata_skor_keaktifan'=> $rekaps->avg('skor_keaktifan') ?? 0,
            'guru_sangat_baik'   => $rekaps->filter(fn($r) => $r->skor_total >= 90)->count(),
            'guru_perlu_perhatian'=> $rekaps->filter(fn($r) => $r->skor_total < 60)->count(),
            'total_log_bulan_ini'=> LogKerjaHarian::byBulan($bulan, $tahun)
                ->whereIn('status', ['submitted', 'diverifikasi'])->count(),
            'log_pending_verifikasi' => LogKerjaHarian::where('status', 'submitted')
                ->byBulan($bulan, $tahun)->count(),
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function hitungHariKerjaAktif(TenagaPendidik $guru, int $bulan, int $tahun): int
    {
        return AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->byBulan($bulan, $tahun)
            ->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])
            ->count();
    }
}