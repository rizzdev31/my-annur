<?php

namespace App\Console\Commands;

use App\Services\AbsensiAutoAlfaService;
use Illuminate\Console\Command;

/**
 * Tandai ALFA tendik aktif yang tidak absen sampai jam kerja berakhir.
 * Dijadwalkan berkala (lihat routes/console.php). Overnight-aware & idempotent.
 */
class AbsensiAutoAlfa extends Command
{
    protected $signature = 'absensi:auto-alfa {--date= : Tanggal kerja YYYY-MM-DD (default: kemarin & hari ini)}';
    protected $description = 'Auto-Alfa absensi harian: tandai tendik yang tak absen sampai shift berakhir (overnight-aware)';

    public function handle(AbsensiAutoAlfaService $svc): int
    {
        $n = $svc->tandai($this->option('date'));
        $this->info("Auto-Alfa absensi harian selesai: {$n} baris alfa dibuat.");
        return self::SUCCESS;
    }
}
