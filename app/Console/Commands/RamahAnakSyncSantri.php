<?php

namespace App\Console\Commands;

use App\Services\SantriSyncService;
use Illuminate\Console\Command;

/**
 * Backfill sinkron santri Smart → RamahAnak (upsert by NISN).
 * Mengantre outbox 'santri_sync' untuk seluruh santri (default: aktif saja).
 * Worker `queue:work` yang benar-benar mengirim; atau jalankan `ramahanak:flush`.
 */
class RamahAnakSyncSantri extends Command
{
    protected $signature = 'ramahanak:sync-santri
        {--all : Termasuk santri nonaktif (default hanya aktif)}';

    protected $description = 'Antrekan sinkronisasi seluruh santri ke RamahAnak (backfill by NISN)';

    public function handle(SantriSyncService $sync): int
    {
        $hanyaAktif = !$this->option('all');
        $n = $sync->syncSemua($hanyaAktif);

        $this->info("Diantre {$n} santri" . ($hanyaAktif ? ' (aktif)' : ' (semua)') . " untuk sinkron ke RamahAnak.");
        if (!config('ramahanak.enabled')) {
            $this->warn('RAMAHANAK_ENABLED=false → baris masuk outbox tapi belum dikirim. Nyalakan + `ramahanak:flush` saat siap.');
        } else {
            $this->line('Pastikan `php artisan queue:work` berjalan agar terkirim.');
        }
        return self::SUCCESS;
    }
}
