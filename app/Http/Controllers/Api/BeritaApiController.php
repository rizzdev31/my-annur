<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Berita pesantren untuk banner Beranda guru. Proxy server-side ke CMS
 * (WordPress wp-json) + cache. Gagal/off → data kosong (banner sembunyi mulus).
 */
class BeritaApiController extends Controller
{
    /**
     * INSTAN: hanya baca cache — TIDAK pernah fetch eksternal di jalur request
     * (cegah Beranda PWA menunggu situs CMS). Cache diisi command `berita:sync`
     * (terjadwal). Cache kosong → data [] (banner sembunyi mulus).
     */
    public function index(): JsonResponse
    {
        $data = config('berita.enabled') ? (Cache::get('berita_pesantren') ?? []) : [];
        return response()->json(['success' => true, 'data' => $data, 'sumber' => config('berita.url')]);
    }

    /** Tarik dari CMS lalu simpan ke cache. Dipanggil command terjadwal (bukan request PWA). */
    public function sync(): int
    {
        if (!config('berita.enabled')) {
            Cache::put('berita_pesantren', [], now()->addDay());
            return 0;
        }
        $data = $this->tarikWordpress();
        // Sukses simpan lama (6 jam); gagal simpan sebentar (30 mnt) agar cepat coba lagi saat pulih.
        Cache::put('berita_pesantren', $data, now()->addMinutes($data ? 360 : 30));
        return count($data);
    }

    /**
     * Ambil dari CMS An-Nur (annur-cms) endpoint publik `GET /api/berita`.
     * Respons sudah berbentuk {success, data:[{judul,ringkasan,tanggal,link,gambar}]}.
     * Kembalikan [] bila gagal. (Sebelumnya WordPress wp-json — CMS sebenarnya Laravel.)
     */
    private function tarikWordpress(): array
    {
        try {
            $base = rtrim((string) config('berita.url'), '/');
            $resp = Http::connectTimeout(3)->timeout(4)->acceptJson()
                ->get("{$base}/api/berita", ['limit' => (int) config('berita.limit', 6)]);

            if (!$resp->successful()) return [];

            $items = data_get($resp->json(), 'data', []);
            if (!is_array($items)) return [];

            return collect($items)->map(fn ($p) => [
                'judul'     => trim((string) data_get($p, 'judul', '')),
                'ringkasan' => Str::limit(trim((string) data_get($p, 'ringkasan', '')), 120),
                'tanggal'   => $this->tanggal(data_get($p, 'tanggal')),
                'link'      => data_get($p, 'link'),
                'gambar'    => data_get($p, 'gambar'),
            ])->filter(fn ($b) => $b['judul'] !== '')->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function tanggal($iso): ?string
    {
        if (!$iso) return null;
        try {
            return \Carbon\Carbon::parse($iso)->locale('id')->isoFormat('D MMM Y');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
