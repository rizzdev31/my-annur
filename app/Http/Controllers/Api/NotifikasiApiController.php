<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi in-app untuk PWA guru (lonceng header + halaman Notifikasi).
 * Data sama dengan lonceng admin; `link` diarahkan ke rute vue-router guru.
 */
class NotifikasiApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifikasi()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($n) => [
                'id'           => $n->id,
                'judul'        => $n->judul,
                'pesan'        => $n->pesan,
                'tipe'         => $n->tipe,
                'sudah_dibaca' => (bool) $n->sudah_dibaca,
                'waktu'        => $n->created_at?->diffForHumans(),
                'link'         => $this->link($n->data),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'unread'  => $user->notifikasiBelumDibaca()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['count' => $request->user()->notifikasiBelumDibaca()->count()],
        ]);
    }

    public function markAsRead(Request $request, $notifikasi): JsonResponse
    {
        $request->user()->notifikasi()
            ->whereKey($notifikasi)
            ->update(['sudah_dibaca' => true, 'dibaca_pada' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->notifikasiBelumDibaca()
            ->update(['sudah_dibaca' => true, 'dibaca_pada' => now()]);

        return response()->json(['success' => true]);
    }

    /** Rute tujuan (path vue-router guru) saat notifikasi diklik. */
    private function link($data): ?string
    {
        // Route eksplisit di payload diprioritaskan (mis. reminder absensi → /absensi).
        if (is_array($data) && !empty($data['route'])) {
            return $data['route'];
        }

        $type = is_array($data) ? ($data['type'] ?? null) : null;

        return match ($type) {
            'pengajuan_izin' => '/izin',
            'kegiatan', 'tugas' => '/tugas',
            'tasmi'          => '/tasmi',
            'tasnif'         => '/tahsin',
            'penggajian'     => '/slip-gaji',
            'kinerja'        => '/kinerja',
            default          => null,
        };
    }
}
