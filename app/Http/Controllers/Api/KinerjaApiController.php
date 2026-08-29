<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RekapKinerjaBulanan;
use App\Models\SettingKinerja;
use App\Services\KinerjaCalculationService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class KinerjaApiController extends Controller
{
    public function __construct(
        private readonly KinerjaCalculationService $service
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // GET /kinerja/bulan-ini
    // Preview kinerja bulan berjalan (kalkulasi real-time, tidak simpan).
    // Sumber data: AbsensiHarian, AbsensiMengajar, Tugas, LogKerja bulan ini.
    // ══════════════════════════════════════════════════════════════════════════

    public function bulanIni(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $today  = TimezoneHelper::today();
        $bulan  = (int) $today->format('m');
        $tahun  = (int) $today->format('Y');

        $setting = SettingKinerja::getDefault();

        try {
            $preview = $this->service->preview($tp, $bulan, $tahun, $setting);
        } catch (\Throwable $e) {
            \Log::error('[KINERJA_API] preview error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Belum ada setting kinerja. Hubungi administrator.',
            ], 503);
        }

        // Cek apakah sudah ada rekap tersimpan (hitung otomatis oleh admin)
        $rekap = RekapKinerjaBulanan::where('tenaga_pendidik_id', $tp->id)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        // Ambil data mentah absensi bulan ini (untuk konteks UI)
        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'             => $bulan,
                'tahun'             => $tahun,
                'nama_bulan'        => $today->locale('id')->isoFormat('MMMM YYYY'),
                'sudah_dikunci'     => $rekap?->sudah_dikunci ?? false,
                'is_preview'        => $rekap === null,

                // ── Skor utama ────────────────────────────────────────────
                'skor_total'        => $preview['skor_total'],
                'skor_dasar'        => $preview['skor_dasar'] ?? $preview['skor_total'],
                'grade'             => $preview['grade'],
                'label_grade'       => $preview['label_grade'],

                // ── Penyesuaian PIKET (penunjang +/−, bukan komponen berbobot) ──
                'piket' => [
                    'penyesuaian'    => $preview['komponen']['piket']['penyesuaian'] ?? 0,
                    'poin_apresiasi' => $preview['komponen']['piket']['poin_apresiasi'] ?? 0,
                    'poin_catatan'   => $preview['komponen']['piket']['poin_catatan'] ?? 0,
                    'apresiasi'      => $preview['komponen']['piket']['apresiasi'] ?? 0,
                    'catatan'        => $preview['komponen']['piket']['catatan'] ?? 0,
                ],

                // ── Bobot setting ─────────────────────────────────────────
                'bobot' => [
                    'absensi'      => $setting->bobot_absensi,
                    'tugas'        => $setting->bobot_tugas,
                    'administrasi' => $setting->bobot_administrasi,
                ],

                // ── Komponen 1: Absensi ──────────────────────────────────
                'absensi' => [
                    'skor'       => $preview['komponen']['absensi']['skor'],
                    'bobot'      => $preview['komponen']['absensi']['bobot'],
                    'kontribusi' => $preview['komponen']['absensi']['kontribusi'],
                    'detail'     => [
                        'skor_harian'   => $preview['komponen']['absensi']['detail']['skor_harian'],
                        'skor_mengajar' => $preview['komponen']['absensi']['detail']['skor_mengajar'],
                        // Rekap kehadiran harian
                        'hadir'         => $preview['komponen']['absensi']['detail']['hadir'],
                        'terlambat'     => $preview['komponen']['absensi']['detail']['terlambat'],
                        'izin'          => $preview['komponen']['absensi']['detail']['izin'],
                        'sakit'         => $preview['komponen']['absensi']['detail']['sakit'],
                        'alfa'          => $preview['komponen']['absensi']['detail']['alfa'],
                        'dinas_luar'    => $preview['komponen']['absensi']['detail']['dinas_luar'],
                        'hari_kerja'    => $preview['komponen']['absensi']['detail']['hari_kerja'],
                        // Nilai per status (info untuk UI)
                        'nilai_hadir'    => $setting->nilai_hadir,
                        'nilai_terlambat'=> $setting->nilai_terlambat,
                        'nilai_izin'     => $setting->nilai_izin,
                        'nilai_sakit'    => $setting->nilai_sakit,
                        'nilai_alfa'     => $setting->nilai_alfa,
                    ],
                ],

                // ── Komponen 2: Tugas ─────────────────────────────────────
                'tugas' => [
                    'skor'       => $preview['komponen']['tugas']['skor'],
                    'bobot'      => $preview['komponen']['tugas']['bobot'],
                    'kontribusi' => $preview['komponen']['tugas']['kontribusi'],
                    'detail'     => [
                        'skor_penugasan'    => $preview['komponen']['tugas']['detail']['skor_penugasan'],
                        'skor_jabatan'      => $preview['komponen']['tugas']['detail']['skor_jabatan'],
                        'penugasan_total'   => $preview['komponen']['tugas']['detail']['penugasan_total'],
                        'penugasan_selesai' => $preview['komponen']['tugas']['detail']['penugasan_selesai'],
                        'jabatan_total'     => $preview['komponen']['tugas']['detail']['jabatan_total'],
                        'jabatan_disetujui' => $preview['komponen']['tugas']['detail']['jabatan_disetujui'],
                    ],
                ],

                // ── Komponen 3: Administrasi ──────────────────────────────
                'administrasi' => [
                    'skor'       => $preview['komponen']['administrasi']['skor'],
                    'bobot'      => $preview['komponen']['administrasi']['bobot'],
                    'kontribusi' => $preview['komponen']['administrasi']['kontribusi'],
                    'detail'     => [
                        'skor_laporan'    => $preview['komponen']['administrasi']['detail']['skor_laporan'],
                        'skor_log'        => $preview['komponen']['administrasi']['detail']['skor_log'],
                        'sesi_jadwal'     => $preview['komponen']['administrasi']['detail']['sesi_jadwal'],
                        'sesi_terlaksana' => $preview['komponen']['administrasi']['detail']['sesi_terlaksana'],
                        'sesi_dilaporkan' => $preview['komponen']['administrasi']['detail']['sesi_dilaporkan'],
                        'log_submitted'   => $preview['komponen']['administrasi']['detail']['log_submitted'],
                    ],
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /kinerja/riwayat
    // Riwayat rekap kinerja 6 bulan terakhir (dari tabel tersimpan).
    // Jika bulan saat ini belum dihitung → pakai preview real-time.
    // ══════════════════════════════════════════════════════════════════════════

    public function riwayat(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $today   = TimezoneHelper::today();
        $setting = SettingKinerja::getDefault();

        // Ambil 12 bulan terakhir tersimpan
        $rekaps = RekapKinerjaBulanan::where('tenaga_pendidik_id', $tp->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->limit(12)
            ->get();

        $data = $rekaps->map(fn($r) => [
            'bulan'      => $r->bulan,
            'tahun'      => $r->tahun,
            'nama_bulan' => Carbon::create($r->tahun, $r->bulan, 1)
                ->locale('id')->isoFormat('MMM YYYY'),
            'skor_total'        => (float) $r->skor_total,
            'skor_absensi'      => (float) $r->skor_absensi,
            'skor_tugas'        => (float) $r->skor_tugas,
            'skor_administrasi' => (float) $r->skor_administrasi,
            'grade'             => $setting->getGrade((float) $r->skor_total),
            'label_grade'       => $setting->getLabelGrade((float) $r->skor_total),
            'sudah_dikunci'     => (bool) $r->sudah_dikunci,
            // Ringkasan absensi
            'hadir'       => $r->total_hadir     ?? 0,
            'terlambat'   => $r->total_terlambat  ?? 0,
            'alfa'        => $r->total_alfa        ?? 0,
            'hari_kerja'  => $r->total_hari_kerja  ?? 0,
            // Ringkasan mengajar
            'jp_terlaksana' => $r->total_jp_terlaksana ?? 0,
            'jp_jadwal'     => $r->total_jp_jadwal     ?? 0,
        ])->values();

        // Statistik ringkas
        $avgTotal     = $rekaps->avg('skor_total');
        $bestMonth    = $rekaps->sortByDesc('skor_total')->first();
        $worstMonth   = $rekaps->sortBy('skor_total')->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'riwayat'          => $data,
                'total_rekap'      => $rekaps->count(),
                'rata_skor'        => round($avgTotal ?? 0, 1),
                'bulan_terbaik'    => $bestMonth ? [
                    'bulan'      => $bestMonth->bulan,
                    'tahun'      => $bestMonth->tahun,
                    'nama_bulan' => Carbon::create($bestMonth->tahun, $bestMonth->bulan, 1)
                        ->locale('id')->isoFormat('MMM YYYY'),
                    'skor_total' => (float) $bestMonth->skor_total,
                ] : null,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /kinerja/nilai-status
    // Tabel nilai per status absensi (untuk guru memahami bobot penilaian).
    // ══════════════════════════════════════════════════════════════════════════

    public function nilaiStatus(Request $request): JsonResponse
    {
        $setting = SettingKinerja::getDefault();

        return response()->json([
            'success' => true,
            'data'    => [
                'nilai_per_status' => [
                    ['status' => 'hadir',      'label' => 'Hadir Tepat Waktu', 'nilai' => $setting->nilai_hadir,      'icon' => 'check_circle'],
                    ['status' => 'dinas_luar', 'label' => 'Dinas Luar',       'nilai' => $setting->nilai_dinas_luar, 'icon' => 'directions_car'],
                    ['status' => 'sakit',      'label' => 'Sakit',            'nilai' => $setting->nilai_sakit,      'icon' => 'medical_services'],
                    ['status' => 'izin',       'label' => 'Izin',             'nilai' => $setting->nilai_izin,       'icon' => 'event_available'],
                    ['status' => 'terlambat',  'label' => 'Terlambat',        'nilai' => $setting->nilai_terlambat,  'icon' => 'schedule'],
                    ['status' => 'alfa',       'label' => 'Alfa',             'nilai' => $setting->nilai_alfa,       'icon' => 'cancel'],
                ],
                'bobot_komponen' => [
                    'absensi'      => $setting->bobot_absensi,
                    'tugas'        => $setting->bobot_tugas,
                    'administrasi' => $setting->bobot_administrasi,
                ],
                'grade_batas' => [
                    'A' => ['min' => $setting->grade_a, 'label' => 'Sangat Baik'],
                    'B' => ['min' => $setting->grade_b, 'label' => 'Baik'],
                    'C' => ['min' => $setting->grade_c, 'label' => 'Cukup'],
                    'D' => ['min' => $setting->grade_d, 'label' => 'Perlu Perhatian'],
                    'E' => ['min' => 0,                 'label' => 'Rendah'],
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /kinerja/punishment
    // Daftar punishment (evaluasi/peringatan/potongan/pencopotan) milik guru.
    // ══════════════════════════════════════════════════════════════════════════

    public function punishment(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $setting = SettingKinerja::getDefault();

        $rows = \App\Models\PunishmentKinerja::with('jabatan')
            ->where('tenaga_pendidik_id', $tp->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')->orderByDesc('id')
            ->limit(60)->get()
            ->map(function ($p) use ($setting) {
                $skor  = $p->skor_kinerja;
                $grade = ($skor !== null && $setting) ? $setting->getGrade($skor) : null;
                return [
                    'id'           => $p->id,
                    'jenis'        => $p->jenis,
                    'jenis_label'  => $p->jenis_label,
                    'nominal'      => (float) ($p->nominal ?? 0),
                    'jabatan'      => $p->jabatan?->nama_jabatan,
                    'catatan'      => $p->catatan,
                    'bulan'        => $p->bulan,
                    'tahun'        => $p->tahun,
                    'periode'      => Carbon::create($p->tahun, $p->bulan)->locale('id')->isoFormat('MMMM YYYY'),
                    'skor_kinerja' => $skor !== null ? (float) $skor : null,
                    'grade'        => $grade,
                    'label_grade'  => ($skor !== null && $setting) ? $setting->getLabelGrade($skor) : null,
                    'tanggal'      => $p->created_at?->toIso8601String(),
                ];
            });

        // Skor kinerja terbaru (rekap tersimpan terkini) untuk header ringkasan
        $rekapTerbaru = RekapKinerjaBulanan::where('tenaga_pendidik_id', $tp->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')->first();

        return response()->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'total'             => $rows->count(),
                'total_potongan'    => (float) $rows->sum('nominal'),
                'skor_terbaru'      => $rekapTerbaru ? (float) $rekapTerbaru->skor_total : null,
                'grade_terbaru'     => ($rekapTerbaru && $setting) ? $setting->getGrade($rekapTerbaru->skor_total) : null,
                'label_grade_terbaru' => ($rekapTerbaru && $setting) ? $setting->getLabelGrade($rekapTerbaru->skor_total) : null,
            ],
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }
}
