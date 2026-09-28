<?php

namespace App\Console\Commands;

use App\Models\RekapKinerjaBulanan;
use App\Services\KinerjaCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Menghitung rekap kinerja secara terjadwal.
 *
 * Sebelumnya kinerja hanya dihitung saat ada orang membuka halaman admin,
 * sehingga angka tersimpan bergantung siapa yang terakhir membuka — bulan yang
 * tidak pernah dibuka di hari terakhirnya tidak pernah punya angka final.
 * Sekarang: bulan berjalan dihitung tiap hari, dan awal bulan baru bulan lalu
 * dihitung sekali lagi sebagai angka final (sebelum periode gajinya dikunci).
 */
class KinerjaHitungHarian extends Command
{
    protected $signature = 'kinerja:hitung
        {--bulan= : Bulan tertentu (default: bulan berjalan)}
        {--tahun= : Tahun tertentu}
        {--dengan-bulan-lalu : Hitung juga bulan sebelumnya (finalisasi)}
        {--alasan= : Alasan yang dicatat di riwayat perubahan skor}';

    protected $description = 'Hitung rekap kinerja bulan berjalan (dan opsional finalisasi bulan lalu)';

    public function handle(KinerjaCalculationService $service): int
    {
        $bulan = (int) ($this->option('bulan') ?: now()->month);
        $tahun = (int) ($this->option('tahun') ?: now()->year);

        $periode = [[$bulan, $tahun]];
        if ($this->option('dengan-bulan-lalu')) {
            $lalu = Carbon::create($tahun, $bulan, 1)->subMonth();
            $periode[] = [(int) $lalu->month, (int) $lalu->year];
        }

        foreach ($periode as [$b, $t]) {
            if (KinerjaCalculationService::periodeTerkunci($b, $t)) {
                $this->warn("Periode {$t}-{$b} sudah dikunci — dilewati.");
                continue;
            }

            $alasan = $this->option('alasan') ?: 'Penghitungan terjadwal';
            $jumlah = $service->hitungRekapSemua($b, $t, function () use ($alasan) {
                // Perubahan dari penjadwal: bukan keputusan manusia.
                RekapKinerjaBulanan::tandaiPerubahan('hitung_ulang', $alasan, null);
            });
            $this->info("Kinerja {$t}-{$b}: {$jumlah} guru dihitung.");
        }

        return self::SUCCESS;
    }
}
