<?php

namespace App\Console\Commands;

use App\Services\ControllingService;
use Illuminate\Console\Command;

/**
 * Tandai ALPHA santri yang tak hadir pada kegiatan controlling yang window-nya sudah tutup.
 * Dijadwalkan berkala (lihat routes/console.php). Lewati hari libur.
 */
class ControllingAutoAlpha extends Command
{
    protected $signature = 'controlling:auto-alpha {--date= : Tanggal YYYY-MM-DD (default hari ini)}';
    protected $description = 'Auto-Alpha absensi Smart Controlling untuk kegiatan yang window-nya sudah tutup';

    public function handle(ControllingService $svc): int
    {
        $n = $svc->autoAlpha($this->option('date'));
        $this->info("Auto-Alpha selesai: {$n} baris alpha dibuat.");
        return self::SUCCESS;
    }
}
