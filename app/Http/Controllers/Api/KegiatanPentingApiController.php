<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiKegiatanPenting;
use App\Models\KegiatanPenting;
use App\Models\PiketJadwal;
use App\Services\KegiatanPentingService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KegiatanPentingApiController extends Controller
{
    public function __construct(private KegiatanPentingService $service) {}

    /** Daftar kegiatan penting hari ini + ringkasan (untuk guru piket). */
    public function hariIni(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        $today   = TimezoneHelper::now()->toDateString();
        $isPiket = PiketJadwal::whereDate('tanggal', $today)->where('tenaga_pendidik_id', $tp->id)->exists();

        $list = KegiatanPenting::where('is_aktif', true)->orderBy('jam')->get()->map(function ($keg) use ($today) {
            $rec = AbsensiKegiatanPenting::where('kegiatan_penting_id', $keg->id)->whereDate('tanggal', $today);
            return [
                'id'         => $keg->id,
                'nama'       => $keg->nama,
                'jam'        => substr((string) $keg->jam, 0, 5),
                'sasaran'    => $keg->sasaran,
                'sudah_hadir'=> (clone $rec)->where('status', 'hadir')->count(),
                'sudah_catat'=> (clone $rec)->count(),
            ];
        });

        return response()->json([
            'success'  => true,
            'is_piket' => $isPiket,
            'tanggal'  => $today,
            'data'     => $list,
        ]);
    }

    /** Daftar peserta yang diharapkan untuk 1 kegiatan hari ini. */
    public function peserta(Request $request, KegiatanPenting $kegiatan): JsonResponse
    {
        $today = TimezoneHelper::now()->toDateString();
        return response()->json([
            'success'  => true,
            'kegiatan' => ['id' => $kegiatan->id, 'nama' => $kegiatan->nama, 'jam' => substr((string) $kegiatan->jam, 0, 5)],
            'data'     => $this->service->pesertaHariIni($kegiatan, $today)->values(),
        ]);
    }

    /** Simpan kehadiran (hanya guru piket bertugas hari ini). */
    public function simpan(Request $request, KegiatanPenting $kegiatan): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        $today = TimezoneHelper::now()->toDateString();
        $isPiket = PiketJadwal::whereDate('tanggal', $today)->where('tenaga_pendidik_id', $tp->id)->exists();
        if (!$isPiket) {
            return response()->json(['success' => false, 'message' => 'Hanya guru piket hari ini yang boleh mencatat.'], 403);
        }

        $data = $request->validate([
            'items'                     => 'required|array|min:1',
            'items.*.tenaga_pendidik_id'=> 'required|integer',
            'items.*.status'            => 'required|in:hadir,tidak_hadir',
        ]);

        $n = $this->service->simpanBanyak($kegiatan, $today, $data['items'], $request->user()->id);

        return response()->json(['success' => true, 'message' => "Kehadiran {$kegiatan->nama} tersimpan ({$n})."]);
    }
}
