<?php

namespace App\Jobs;

use App\Models\KodeVariabelCache;
use App\Services\RamahAnakClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tarik daftar kode variabel (pelanggaran/apresiasi/konselor) dari RamahAnak → upsert ke cache.
 * Dijadwalkan berkala (lihat routes/console.php). Aman bila RamahAnak tak aktif (skip).
 */
class SyncVariabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RamahAnakClient $client): void
    {
        if (!config('ramahanak.enabled')) return;

        foreach (['pelanggaran', 'apresiasi', 'konselor'] as $jenis) {
            $resp = $client->tarikVariabel($jenis);
            if (!$resp->successful()) continue;

            foreach ($resp->json('data', []) as $v) {
                if (empty($v['kode'])) continue;
                KodeVariabelCache::updateOrCreate(
                    ['jenis' => $jenis, 'kode' => $v['kode']],
                    [
                        'kategori' => $v['kategori'] ?? null,
                        'poin'     => $v['poin'] ?? null,
                        'label'    => $v['label'] ?? null,
                    ]
                );
            }
        }
    }
}
