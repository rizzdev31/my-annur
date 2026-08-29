<?php

namespace App\Console\Commands;

use App\Models\OutboxLaporan;
use App\Jobs\KirimLaporanJob;
use Illuminate\Console\Command;

/**
 * Kirim ulang (dispatch) semua baris outbox yang belum terkirim ke RamahAnak.
 *
 * Berguna saat go-live (membersihkan backlog yang dibuat selagi RAMAHANAK_ENABLED=false)
 * atau setelah RamahAnak/jaringan pulih dari gangguan. Pengiriman idempotent (200 duplicate
 * = sukses) sehingga aman dari dobel. Worker `queue:work` yang benar-benar mengirim.
 */
class RamahAnakFlush extends Command
{
    protected $signature = 'ramahanak:flush
        {--status=pending,failed : Status yang diproses, dipisah koma (pending,failed)}
        {--limit= : Batasi jumlah baris (default semua)}
        {--force : Tetap jalan walau RAMAHANAK_ENABLED=false}';

    protected $description = 'Dispatch ulang outbox laporan (pending/failed) ke RamahAnak';

    public function handle(): int
    {
        if (!config('ramahanak.enabled') && !$this->option('force')) {
            $this->warn('RAMAHANAK_ENABLED=false. Nyalakan dulu di .env, atau pakai --force untuk tetap kirim.');
            return self::FAILURE;
        }

        $statuses = collect(explode(',', (string) $this->option('status')))
            ->map(fn($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        // Jangan pernah sentuh yang sudah sukses.
        $statuses = array_values(array_diff($statuses, ['sent', 'duplicate']));
        if (empty($statuses)) {
            $this->error('Tidak ada status valid untuk diproses (sent/duplicate dilewati).');
            return self::FAILURE;
        }

        $query = OutboxLaporan::whereIn('status', $statuses)->orderBy('id');
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada baris outbox untuk dikirim ulang.');
            return self::SUCCESS;
        }

        $n = 0;
        foreach ($rows as $row) {
            // Reset ke pending agar bersih sebelum dikirim ulang.
            $row->update(['status' => 'pending', 'error' => null]);
            KirimLaporanJob::dispatch($row->id);
            $n++;
        }

        $this->info("Dispatch {$n} baris outbox (" . implode(', ', $statuses) . "). "
            . "Pastikan `php artisan queue:work` berjalan agar terkirim.");
        return self::SUCCESS;
    }
}
