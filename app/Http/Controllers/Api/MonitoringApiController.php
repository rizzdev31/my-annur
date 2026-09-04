<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Services\PengawasService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MONITORING PIMPINAN (PWA) — pengawas memantau guru yang menjadi tanggung
 * jawabnya tanpa akun admin kedua.
 *
 * KEAMANAN: setiap endpoint WAJIB (1) cek modul lewat PengawasService::boleh(),
 * (2) membatasi data hanya pada idGuruDiawasi(). ID guru dari klien tidak dipercaya.
 */
class MonitoringApiController extends Controller
{
    public function __construct(private PengawasService $pengawas) {}

    /** GET /monitoring/status — hak monitoring saya (untuk menampilkan menu). */
    public function status(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        return response()->json(['success' => true, 'data' => $this->pengawas->ringkasan($tp->id)]);
    }

    /** GET /monitoring/absen-harian?tanggal= — status absen guru yang diawasi. */
    public function absenHarian(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'absen_harian')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring absensi.'], 403);
        }

        $tanggal = $request->filled('tanggal')
            ? \Carbon\Carbon::parse($request->tanggal)->toDateString()
            : TimezoneHelper::today()->toDateString();

        $guru = $this->pengawas->guruDiawasi($tp->id);
        if ($guru->isEmpty()) {
            return response()->json(['success' => true, 'data' => [
                'tanggal' => $tanggal, 'ringkasan' => [], 'guru' => [],
            ]]);
        }

        $absen = AbsensiHarian::whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->get()->keyBy('tenaga_pendidik_id');

        $rows = $guru->map(function ($g) use ($absen) {
            $a = $absen->get($g->id);
            return [
                'tenaga_pendidik_id' => $g->id,
                'nama'            => $g->user?->name ?? '—',
                'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                'status'          => $a?->status ?? 'belum',
                'jam_masuk'       => $a?->jam_masuk ? substr((string) $a->jam_masuk, 0, 5) : null,
                'jam_pulang'      => $a?->jam_pulang ? substr((string) $a->jam_pulang, 0, 5) : null,
                'menit_terlambat' => (int) ($a?->menit_terlambat ?? 0),
                'keterangan'      => $a?->keterangan,
            ];
        })->values();

        $ringkas = [];
        foreach ($rows as $r) {
            $ringkas[$r['status']] = ($ringkas[$r['status']] ?? 0) + 1;
        }

        return response()->json(['success' => true, 'data' => [
            'tanggal'   => $tanggal,
            'total'     => $rows->count(),
            'ringkasan' => $ringkas,
            'guru'      => $rows,
        ]]);
    }
}
