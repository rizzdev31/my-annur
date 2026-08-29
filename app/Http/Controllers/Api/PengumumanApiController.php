<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\JsonResponse;

class PengumumanApiController extends Controller
{
    /**
     * GET /pengumuman/aktif — pamflet aktif untuk pop-up aplikasi (satu saja).
     * Mengembalikan data:null bila tidak ada pengumuman aktif.
     */
    public function aktif(): JsonResponse
    {
        $p = Pengumuman::aktif()->latest('updated_at')->first();

        return response()->json([
            'success' => true,
            'data'    => $p ? [
                'id'    => $p->id,
                'judul' => $p->judul,
                // PENTING: bangun URL dari host REQUEST (mis. 10.0.2.2:8000), bukan
                // APP_URL (localhost) — agar gambar terjangkau dari perangkat/emulator.
                'gambar_url' => $p->gambar ? url('storage/' . $p->gambar) : null,
                'link_url'   => $p->link_url,
                // dipakai klien sebagai kunci "jangan tampilkan lagi" per versi pamflet
                'versi'      => $p->updated_at?->timestamp,
            ] : null,
        ]);
    }
}
