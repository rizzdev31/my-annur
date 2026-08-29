<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalApiController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // SEMUA JADWAL (mingguan, dikelompokkan per hari)
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        $jadwalList = \App\Models\JadwalMengajar::with(['mataPelajaran', 'tahunAjaran'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderByRaw("FIELD(hari,'senin','selasa','rabu','kamis','jumat','sabtu','ahad')")
            ->orderBy('jam_mulai')
            ->get();

        // Hari ini sebagai penanda
        $today    = TimezoneHelper::today();
        $namaHari = TimezoneHelper::namaHariDB($today);

        // Kelompokkan per hari
        $grouped = $jadwalList
            ->groupBy('hari')
            ->map(fn($items, $hari) => [
                'hari'        => ucfirst($hari),
                'hari_key'    => $hari,
                'is_hari_ini' => $hari === $namaHari,
                'jadwal'      => $items->map(fn($j) => $this->formatJadwal($j))->values(),
                'total'       => $items->count(),
                'total_jp'    => (int) $items->sum('jumlah_jp'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'hari_ini'        => $namaHari,
                'tanggal'         => $today->toDateString(),
                'jadwal_per_hari' => $grouped,
                'total_jadwal'    => $jadwalList->count(),
                'total_jp'        => (int) $jadwalList->sum('jumlah_jp'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // JADWAL HARI INI
    // ══════════════════════════════════════════════════════════════════════════

    public function hariIni(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        $today    = TimezoneHelper::today();
        $namaHari = TimezoneHelper::namaHariDB($today);

        $jadwalList = \App\Models\JadwalMengajar::with(['mataPelajaran', 'tahunAjaran'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('hari', $namaHari)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderBy('jam_mulai')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'tanggal'  => $today->toDateString(),
                'hari'     => ucfirst($today->locale('id')->isoFormat('dddd')),
                'hari_key' => $namaHari,
                'jadwal'   => $jadwalList->map(fn($j) => $this->formatJadwal($j))->values(),
                'total'    => $jadwalList->count(),
                'total_jp' => (int) $jadwalList->sum('jumlah_jp'),
                'ada_jadwal' => $jadwalList->isNotEmpty(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // JADWAL MINGGU INI (semua hari, dikelompokkan)
    // ══════════════════════════════════════════════════════════════════════════

    public function mingguIni(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        $today    = TimezoneHelper::today();
        $namaHari = TimezoneHelper::namaHariDB($today);

        $jadwalList = \App\Models\JadwalMengajar::with(['mataPelajaran', 'tahunAjaran'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderByRaw("FIELD(hari,'senin','selasa','rabu','kamis','jumat','sabtu','ahad')")
            ->orderBy('jam_mulai')
            ->get();

        // Semua hari aktif yang punya jadwal
        $hariUrutan = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];
        $grouped    = collect($hariUrutan)
            ->map(function ($hari) use ($jadwalList, $namaHari) {
                $items = $jadwalList->where('hari', $hari);
                if ($items->isEmpty()) return null;
                return [
                    'hari'        => ucfirst($hari),
                    'hari_key'    => $hari,
                    'is_hari_ini' => $hari === $namaHari,
                    'jadwal'      => $items->map(fn($j) => $this->formatJadwal($j))->values(),
                    'total'       => $items->count(),
                    'total_jp'    => (int) $items->sum('jumlah_jp'),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'hari_ini'       => $namaHari,
                'tanggal'        => $today->toDateString(),
                'jadwal_minggu'  => $grouped,
                'total_jadwal'   => $jadwalList->count(),
                'total_jp'       => (int) $jadwalList->sum('jumlah_jp'),
                'hari_mengajar'  => $grouped->count(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════════════════

    private function formatJadwal(\App\Models\JadwalMengajar $j): array
    {
        return [
            'jadwal_id'      => $j->id,
            'mata_pelajaran' => $j->mataPelajaran?->nama ?? '—',
            'kelas'          => $j->kelas,
            'ruangan'        => $j->ruangan ?? '—',
            'hari'           => $j->hari,
            'jam_mulai'      => $j->jam_mulai,
            'jam_selesai'    => $j->jam_selesai,
            'jumlah_jp'      => $j->jumlah_jp,
        ];
    }
}
