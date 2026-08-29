<?php

namespace App\Console\Commands;

use App\Services\PeminjamanInventarisService;
use Illuminate\Console\Command;

/**
 * Tandai 'selesai' peminjaman inventaris yang jam pemakaiannya sudah lewat.
 * Dijadwalkan berkala (lihat routes/console.php).
 */
class InventarisAutoSelesai extends Command
{
    protected $signature = 'inventaris:auto-selesai';
    protected $description = 'Auto-tandai selesai peminjaman inventaris yang waktunya sudah lewat';

    public function handle(PeminjamanInventarisService $svc): int
    {
        $n = $svc->autoSelesai();
        $this->info("Auto-selesai inventaris: {$n} peminjaman ditandai selesai.");
        return self::SUCCESS;
    }
}
