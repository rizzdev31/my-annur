<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * Klien HTTP ke API RamahAnak (penerima). Kontrak: PRD-04/05.
 * Token & URL via config('ramahanak.*') (BUKAN env() langsung — aman saat config:cache).
 */
class RamahAnakClient
{
    private function http()
    {
        return Http::baseUrl(rtrim((string) config('ramahanak.url'), '/'))
            ->withToken((string) config('ramahanak.token'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 1000, throw: false); // retry ringan untuk gangguan jaringan
    }

    public function ping(): Response                     { return $this->http()->get('/ping'); }
    public function kirimPelanggaran(array $p): Response { return $this->http()->post('/eksekusi/pelanggaran', $p); }
    public function kirimApresiasi(array $p): Response   { return $this->http()->post('/eksekusi/apresiasi', $p); }
    public function kirimKonselor(array $p): Response    { return $this->http()->post('/eksekusi/konselor', $p); }
    public function kirimTelat(array $p): Response        { return $this->http()->post('/absensi/telat', $p); }

    public function tarikVariabel(string $jenis): Response { return $this->http()->get("/variabel/{$jenis}"); }
    public function cekSantri(string $nisn): Response      { return $this->http()->get("/santri/{$nisn}"); }

    /** Sinkron identitas & kelas santri (upsert by NISN). Lihat PRD-Sync-Santri-RamahAnak. */
    public function syncSantri(array $p): Response         { return $this->http()->post('/santri/sync', $p); }

    /** Sinkron identitas guru/tenaga pendidik (upsert by NIP). */
    public function syncGuru(array $p): Response           { return $this->http()->post('/guru/sync', $p); }

    /** Rekonsiliasi: kirim daftar NISN/NIP aktif → RA nonaktifkan sisanya (hasil-sync). */
    public function rekonsiliasi(array $p): Response        { return $this->http()->post('/sync/rekonsiliasi', $p); }
}
