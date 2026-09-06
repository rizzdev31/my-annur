<?php

namespace App\Console\Commands;

use App\Services\AbsensiAutoCheckoutService;
use Illuminate\Console\Command;

/**
 * Tutup absensi yang sudah check-in tapi tak pernah check-out, setelah
 * kesempatan check-out manual habis. Dipakai berkala (lihat routes/console.php)
 * dan juga untuk penutupan massal data lama lewat --sejak.
 */
class AbsensiAutoCheckout extends Command
{
    protected $signature = 'absensi:auto-checkout
        {--sejak= : Tanggal kerja paling awal (Y-m-d), default 60 hari lalu}
        {--sampai= : Tanggal kerja paling akhir (Y-m-d), default kemarin}
        {--lintas-hari : Hanya shift malam}
        {--dry-run : Tampilkan saja, jangan menulis}';

    protected $description = 'Auto-checkout absensi harian yang menggantung (jam pulang diisi sesuai jadwal)';

    public function handle(AbsensiAutoCheckoutService $svc): int
    {
        $dry = (bool) $this->option('dry-run');

        $h = $svc->tutup(
            $this->option('sejak'), $this->option('sampai'),
            (bool) $this->option('lintas-hari'), $dry
        );

        if ($dry && $h['rincian']) {
            $this->table(
                ['Tanggal', 'Guru', 'Masuk', 'Pulang (jadwal)', 'Lintas hari'],
                array_map(fn($r) => [
                    $r['tanggal'], $r['guru'], $r['masuk'], $r['pulang'], $r['lintas'] ? 'ya' : '-',
                ], $h['rincian'])
            );
        }

        $this->info(($dry ? '[DRY-RUN] ' : '')
            . "Auto-checkout: {$h['ditutup']} ditutup, {$h['dilewati']} dilewati, dari {$h['diproses']} baris menggantung.");

        return self::SUCCESS;
    }
}
