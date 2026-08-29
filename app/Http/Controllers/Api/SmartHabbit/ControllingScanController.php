<?php

namespace App\Http\Controllers\Api\SmartHabbit;

use App\Http\Controllers\Controller;
use App\Models\ControllingPeriode;
use App\Models\ControllingJadwal;
use App\Services\ControllingService;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint scan Smart Controlling — DEVICE-AGNOSTIC (web kiosk / ESP32-CAM).
 * Proteksi via middleware device.token (header X-Device-Token). Bukan auth user.
 */
class ControllingScanController extends Controller
{
    /** GET /controlling/aktif — kegiatan yang window-nya aktif sekarang (untuk UI kiosk). */
    public function aktif(): JsonResponse
    {
        $periode = ControllingPeriode::aktif();
        if (!$periode) return response()->json(['success' => true, 'data' => ['periode' => null, 'kegiatan' => []]]);

        $now = TimezoneHelper::now();
        $today = $now->toDateString();
        $hari = TimezoneHelper::namaHariDB($now);

        $data = ControllingJadwal::with('kegiatan')
            ->where('periode_id', $periode->id)->where('hari', $hari)->where('is_aktif', true)
            ->get()
            // Window absen = [jam_mulai .. jam_selesai + ambang_telat] — HARUS sama
            // dengan ControllingService::scan() agar fase telat (toleransi) tidak
            // hilang dari layar sebelum waktunya.
            ->map(function ($j) use ($now, $today) {
                $start = Carbon::parse("$today {$j->jam_mulai}", TimezoneHelper::TZ);
                $end   = Carbon::parse("$today {$j->jam_selesai}", TimezoneHelper::TZ);
                $close = $end->copy()->addMinutes((int) $j->ambang_telat_menit);
                return ['j' => $j, 'start' => $start, 'end' => $end, 'close' => $close];
            })
            ->filter(fn($c) => $now->betweenIncluded($c['start'], $c['close']))
            ->map(fn($c) => [
                'jadwal_id'   => $c['j']->id,
                'kegiatan_id' => $c['j']->kegiatan_id,
                'nama'        => $c['j']->kegiatan?->nama ?? '—',
                'jenis'       => $c['j']->kegiatan?->jenis,
                'jam_mulai'   => substr($c['j']->jam_mulai, 0, 5),
                'jam_selesai' => substr($c['j']->jam_selesai, 0, 5),
                // Fase saat ini: 'hadir' (masih window utama) / 'telat' (masa tenggang).
                'fase'        => $now->lte($c['end']) ? 'hadir' : 'telat',
                'jam_tutup'   => $c['close']->format('H:i'),
                'ambang_menit'=> (int) $c['j']->ambang_telat_menit,
            ])->values();

        return response()->json(['success' => true, 'data' => [
            'periode'  => $periode->nama,
            'tanggal'  => $today,
            'kegiatan' => $data,
        ]]);
    }

    /** POST /controlling/scan — { nip, kegiatan_id?, petugas? } — device kiosk/ESP32. */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'nip'         => 'required|string|max:50',
            'kegiatan_id' => 'nullable|integer',
            'petugas'     => 'nullable|string|max:100',
        ]);

        return $this->proses(
            trim($request->nip),
            $request->kegiatan_id ? (int) $request->kegiatan_id : null,
            $request->petugas ?: 'device-scan'
        );
    }

    /**
     * POST /smart-habbit/controlling/scan — fallback via HP guru (auth Bearer).
     * Petugas diturunkan dari user login (anti-spoof), bukan dari body.
     */
    public function scanGuru(Request $request): JsonResponse
    {
        $request->validate([
            'nip'         => 'required|string|max:50',
            'kegiatan_id' => 'nullable|integer',
        ]);

        $tp   = $request->user()->tenagaPendidik;
        $nama = $request->user()->name ?? 'Guru';
        $petugas = $tp ? "{$nama} (NIP {$tp->nip})" : $nama;

        return $this->proses(
            trim($request->nip),
            $request->kegiatan_id ? (int) $request->kegiatan_id : null,
            $petugas
        );
    }

    /** Jalankan scan via service + bentuk respons seragam. */
    private function proses(string $nip, ?int $kegiatanId, string $petugas): JsonResponse
    {
        try {
            $hasil = app(ControllingService::class)->scan($nip, $kegiatanId, $petugas);
        } catch (\DomainException $e) {
            $code = in_array($e->getCode(), [404, 422], true) ? $e->getCode() : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
        }

        $label = ['hadir' => 'HADIR', 'telat' => 'TELAT', 'alpha' => 'ALPHA'][$hasil['status']] ?? $hasil['status'];
        $msg = $hasil['sudah_absen']
            ? "{$hasil['santri']['nama']} sudah absen ({$label}) untuk {$hasil['kegiatan']}."
            : "{$hasil['santri']['nama']} — {$label} · {$hasil['kegiatan']}.";

        return response()->json(['success' => true, 'message' => $msg, 'data' => $hasil]);
    }
}
