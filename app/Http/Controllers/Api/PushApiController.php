<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pendaftaran perangkat untuk notifikasi push (PWA guru). */
class PushApiController extends Controller
{
    /** GET /push/kunci — kunci publik VAPID + status kesiapan server. */
    public function kunci(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'siap'       => WebPushService::siap(),
            'public_key' => WebPushService::publicKey(),
            'terdaftar'  => PushSubscription::where('user_id', $request->user()->id)->count(),
        ]]);
    }

    /** POST /push/langganan — daftarkan (atau perbarui) perangkat ini. */
    public function daftar(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:1000',
            'p256dh'   => 'required|string|max:255',
            'auth'     => 'required|string|max:255',
        ]);

        // updateOrCreate berdasar hash endpoint: memasang ulang di HP yang sama
        // memperbarui baris lama, bukan menumpuk duplikat.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($request->endpoint)],
            [
                'user_id'          => $request->user()->id,
                'endpoint'         => $request->endpoint,
                'p256dh'           => $request->p256dh,
                'auth'             => $request->auth,
                'perangkat'        => substr((string) $request->userAgent(), 0, 255),
                'terakhir_dipakai' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi HP diaktifkan untuk perangkat ini.',
        ]);
    }

    /** DELETE /push/langganan — matikan di perangkat ini. */
    public function batal(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string|max:1000']);

        PushSubscription::where('endpoint_hash', PushSubscription::hash($request->endpoint))
            ->where('user_id', $request->user()->id)   // hanya milik sendiri
            ->delete();

        return response()->json(['success' => true, 'message' => 'Notifikasi HP dimatikan.']);
    }

    /** POST /push/uji — kirim notifikasi percobaan ke perangkat sendiri. */
    public function uji(Request $request, WebPushService $push): JsonResponse
    {
        $n = $push->kirim([$request->user()->id], 'Notifikasi Aktif',
            'Bagus! Pengingat An-Nur Smart akan muncul seperti ini di HP Anda.',
            ['route' => '/notifikasi', 'tag' => 'uji']);

        return response()->json([
            'success' => $n > 0,
            'message' => $n > 0
                ? "Terkirim ke {$n} perangkat. Cek layar HP Anda."
                : 'Belum ada perangkat aktif. Aktifkan notifikasi dulu.',
        ]);
    }
}
