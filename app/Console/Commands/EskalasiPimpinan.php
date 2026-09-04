<?php

namespace App\Console\Commands;

use App\Services\EskalasiPimpinanService;
use Illuminate\Console\Command;

/**
 * Kirim ringkasan anomali harian ke pengawas/pimpinan.
 * Aman dijalankan berulang (tiap jam) karena tiap jenis di-dedup per hari.
 */
class EskalasiPimpinan extends Command
{
    protected $signature = 'eskalasi:pimpinan {--tanggal= : Y-m-d, default hari ini}';
    protected $description = 'Eskalasi anomali (absen, mengajar, izin, kinerja) ke pengawas/pimpinan';

    public function handle(EskalasiPimpinanService $service): int
    {
        $n = $service->jalankan($this->option('tanggal'));
        $this->info("Eskalasi pimpinan: $n notifikasi terkirim.");
        return self::SUCCESS;
    }
}
