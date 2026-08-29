<?php

namespace App\Console\Commands;

use App\Services\GuruSyncService;
use Illuminate\Console\Command;

/**
 * Backfill sinkron guru (tenaga_pendidik) Smart → RamahAnak (upsert by NIP).
 * Worker `queue:work` / `ramahanak:flush` yang mengirim.
 */
class RamahAnakSyncGuru extends Command
{
    protected $signature = 'ramahanak:sync-guru
        {--all : Termasuk guru nonaktif (default hanya aktif)}';

    protected $description = 'Antrekan sinkronisasi seluruh guru ke RamahAnak (backfill by NIP)';

    public function handle(GuruSyncService $sync): int
    {
        $hanyaAktif = !$this->option('all');
        $n = $sync->syncSemua($hanyaAktif);

        $this->info("Diantre {$n} guru" . ($hanyaAktif ? ' (aktif)' : ' (semua)') . " untuk sinkron ke RamahAnak.");
        if (!config('ramahanak.enabled')) {
            $this->warn('RAMAHANAK_ENABLED=false → masuk outbox tapi belum dikirim. Nyalakan + `ramahanak:flush`.');
        } else {
            $this->line('Pastikan `php artisan queue:work` berjalan agar terkirim.');
        }
        return self::SUCCESS;
    }
}
