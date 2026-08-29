<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\WaInbox;
use App\Services\FonnteClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Webhook incoming Fonnte — menampung balasan WA dari wali ke wa_inbox.
 * Fondasi fitur dua-arah. Diproteksi WA_WEBHOOK_SECRET (query ?secret= atau header).
 * Set URL webhook ini di dashboard Fonnte: {APP_URL}/api/webhook/fonnte/incoming?secret=XXX
 */
class FonnteWebhookController extends Controller
{
    public function incoming(Request $request): JsonResponse
    {
        $secret = (string) config('fonnte.webhook_secret');
        if ($secret !== '') {
            $kirim = $request->input('secret') ?? $request->header('X-Webhook-Secret');
            if (!hash_equals($secret, (string) $kirim)) {
                return response()->json(['status' => false, 'message' => 'unauthorized'], 403);
            }
        }

        // Fonnte mengirim form fields; nama field bisa bervariasi → ambil fleksibel.
        $pengirim = $request->input('sender') ?? $request->input('pengirim');
        $pesan    = $request->input('message') ?? $request->input('text');
        $nama     = $request->input('name') ?? $request->input('pushname');
        $device   = $request->input('device');
        $media    = $request->input('url');

        // Bukan event pesan (mis. status/ack) → balas ok tanpa menyimpan.
        if (!$pengirim) {
            return response()->json(['status' => true]);
        }

        // Cocokkan ke santri via nomor (normalisasi 08xx ↔ 62xx).
        $norm = FonnteClient::normalisasiNomor($pengirim);
        $santriId = $norm ? $this->cocokkanSantri($norm) : null;

        WaInbox::create([
            'device'        => $device,
            'pengirim'      => $norm ?? $pengirim,
            'nama'          => $nama,
            'santri_id'     => $santriId,
            'pesan'         => $pesan,
            'media_url'     => $media,
            'raw'           => $request->all(),
            'dibaca'        => false,
            'diterima_pada' => now(),
        ]);

        return response()->json(['status' => true]);
    }

    /** Cari santri yang nomor WA-nya (dinormalisasi) sama dengan pengirim. */
    private function cocokkanSantri(string $norm): ?int
    {
        $kandidat = Santri::whereNotNull('no_whatsapp')->where('no_whatsapp', '!=', '')
            ->get(['id', 'no_whatsapp']);
        foreach ($kandidat as $s) {
            if (FonnteClient::normalisasiNomor($s->no_whatsapp) === $norm) {
                return $s->id;
            }
        }
        return null;
    }
}
