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
        $semua = Pengumuman::aktif()->terurut()->get()
            ->filter(fn ($x) => $x->bisaDitampilkan())->values();
        $p = $semua->first();

        return response()->json([
            'success' => true,
            // `daftar` = SEMUA pengumuman aktif (bisa lebih dari satu sejak 26 Sep 2026).
            // `data` tetap berisi satu pengumuman pertama agar aplikasi versi lama
            // yang membaca objek tunggal tidak rusak.
            'daftar'  => $semua->map(fn ($x) => $this->bentuk($x))->all(),
            'total'   => $semua->count(),
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

    /** Bentuk satu pengumuman untuk klien (gambar ATAU berkas PDF). */
    private function bentuk(Pengumuman $p): array
    {
        return [
            'id'         => $p->id,
            'judul'      => $p->judul,
            'tipe'       => $p->tipe ?? 'gambar',
            'isi'        => $p->isi,
            'gambar_url' => $p->gambar ? url('storage/' . $p->gambar) : null,
            'file_url'   => $p->file ? url('storage/' . $p->file) : null,
            'nama_file'  => $p->nama_file,
            'link_url'   => $p->link_url,
            'sumber_tipe'=> $p->sumber_tipe,
            'versi'      => $p->updated_at?->timestamp,
        ];
    }
}
