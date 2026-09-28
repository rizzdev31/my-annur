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
        // Label periode yang BERJALAN mengikuti periode penggajian (26→25),
        // bukan bulan kalender. Tanpa ini, tanggal 26–31 masih dianggap bulan
        // lama yang skornya sudah dibekukan, dan periode baru tak pernah dihitung.
        $berjalan = \App\Models\PeriodePenggajian::untukTanggal(now()->toDateString());
        $bulan = (int) ($this->option('bulan') ?: ($berjalan->bulan ?? now()->month));
        $tahun = (int) ($this->option('tahun') ?: ($berjalan->tahun ?? now()->year));

        $periode = [[$bulan, $tahun]];
        if ($this->option('dengan-bulan-lalu')) {
            // Periode sebelumnya = periode gaji terakhir sebelum yang berjalan;
            // kalau belum ada periodenya, jatuh ke bulan kalender sebelumnya.
            $sebelum = \App\Models\PeriodePenggajian::where('tahun', '<', $tahun)
                ->orWhere(fn ($q) => $q->where('tahun', $tahun)->where('bulan', '<', $bulan))
                ->orderByDesc('tahun')->orderByDesc('bulan')->first();

            if ($sebelum) {
                $periode[] = [(int) $sebelum->bulan, (int) $sebelum->tahun];
            } else {
                $lalu = Carbon::create($tahun, $bulan, 1)->subMonth();
                $periode[] = [(int) $lalu->month, (int) $lalu->year];
            }
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
            [$dari, $sampai, $ikut] = app(KinerjaCalculationService::class)->rentangPenilaian($b, $t);
            $this->info("Kinerja {$t}-{$b}: {$jumlah} guru dihitung"
                . " (jendela {$dari->toDateString()} → {$sampai->toDateString()}"
                . ($ikut ? ', ikut periode gaji' : ', bulan kalender') . ').');
        }

        return self::SUCCESS;
    }
}
