<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BeritaApiController;
use Illuminate\Console\Command;

/**
 * Sinkron berita CMS pesantren ke cache (di luar jalur request) agar endpoint
 * /berita — dipakai Beranda PWA — selalu instan tanpa menunggu situs eksternal.
 */
class BeritaSync extends Command
{
    protected $signature = 'berita:sync';
    protected $description = 'Ambil berita dari CMS pesantren dan simpan ke cache';

    public function handle(): int
    {
        $n = app(BeritaApiController::class)->sync();
        $this->info("Berita disinkron ke cache: {$n} item.");
        return self::SUCCESS;
    }
}
