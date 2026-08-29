<?php
// ══════════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Superadmin/KoreksiAbsensiController.php
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\KoreksiAbsensi;
use App\Models\TenagaPendidik;
use App\Models\Penggajian;
use App\Services\ExceptionHandlingService;
use Illuminate\Http\Request;

class KoreksiAbsensiController extends Controller
{
    public function __construct(
        private readonly ExceptionHandlingService $exceptionService
    ) {}

    public function index(Request $request)
    {
        $log = KoreksiAbsensi::with(['tenagaPendidik.user', 'dikoreksiOleh'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.smart-payroll.absensi.koreksi.index', compact('log'));
    }

    /**
     * Koreksi absensi harian (status, jam, dll).
     */
    public function koreksiHarian(AbsensiHarian $absensiHarian, Request $request)
    {
        $request->validate([
            'status'          => 'sometimes|in:hadir,terlambat,izin,sakit,alfa,libur,dinas_luar',
            'jam_masuk'       => 'sometimes|nullable|date_format:H:i',
            'jam_pulang'      => 'sometimes|nullable|date_format:H:i',
            'menit_terlambat' => 'sometimes|integer|min:0',
            'keterangan'      => 'sometimes|nullable|string|max:500',
            'alasan'          => 'required|string|max:500',
        ]);

        $koreksi = $this->exceptionService->koreksiAbsensiHarian(
            $absensiHarian,
            $request->except('alasan'),
            $request->alasan
        );

        // Jika ada penggajian yang sudah di-generate untuk bulan ini,
        // otomatis flag perlu recalculate
        $bulan      = $absensiHarian->tanggal->month;
        $tahun      = $absensiHarian->tanggal->year;
        $penggajian = Penggajian::where('tenaga_pendidik_id', $absensiHarian->tenaga_pendidik_id)
            ->whereHas('periodePenggajian', fn($q) => $q->where('bulan', $bulan)->where('tahun', $tahun))
            ->whereIn('status', ['draft', 'final'])
            ->first();

        $needsRecalc = $penggajian ? true : false;

        return response()->json([
            'success'       => true,
            'message'       => 'Koreksi absensi berhasil disimpan.',
            'koreksi'       => $koreksi,
            'needs_recalc'  => $needsRecalc,
            'penggajian_id' => $penggajian?->id,
        ]);
    }

    /**
     * Insert absensi manual (guru tidak ada record sama sekali).
     */
    public function insertManual(Request $request)
    {
        $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'required|date',
            'status'             => 'required|in:hadir,terlambat,izin,sakit,alfa,libur,dinas_luar',
            'jam_masuk'          => 'nullable|date_format:H:i',
            'jam_pulang'         => 'nullable|date_format:H:i',
            'keterangan'         => 'nullable|string|max:500',
            'alasan'             => 'required|string|max:500',
        ]);

        $guru    = TenagaPendidik::findOrFail($request->tenaga_pendidik_id);
        $absensi = $this->exceptionService->insertAbsensiManual(
            $guru,
            $request->except('alasan'),
            $request->alasan
        );

        return response()->json([
            'success' => true,
            'message' => 'Absensi manual berhasil disimpan.',
            'data'    => $absensi,
        ]);
    }

    public function logKoreksi(Request $request)
    {
        $log = KoreksiAbsensi::with(['tenagaPendidik.user', 'dikoreksiOleh'])
            ->when($request->guru_id, fn($q) => $q->where('tenaga_pendidik_id', $request->guru_id))
            ->when($request->bulan,   fn($q) => $q->whereMonth('tanggal', $request->bulan))
            ->when($request->tahun,   fn($q) => $q->whereYear('tanggal', $request->tahun))
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.smart-payroll.absensi.koreksi.log', compact('log'));
    }
}