<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Buat pasangan kunci VAPID untuk Web Push (sekali saja, lalu simpan di .env). */
class WebpushVapid extends Command
{
    protected $signature   = 'webpush:vapid';
    protected $description = 'Buat kunci VAPID untuk notifikasi Web Push';

    public function handle(): int
    {
        if (config('webpush.public_key')) {
            $this->warn('VAPID sudah terpasang di .env.');
            $this->line('Membuat kunci baru akan MEMBATALKAN semua langganan guru yang ada.');
            if (!$this->confirm('Tetap buat kunci baru?', false)) return self::SUCCESS;
        }

        $k = VAPID::createVapidKeys();

        $this->info('Salin dua baris ini ke .env server, lalu jalankan config:cache:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY=' . $k['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $k['privateKey']);
        $this->newLine();

        return self::SUCCESS;
    }
}
