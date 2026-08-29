<?php

namespace App\Console\Commands;

use App\Models\RekapKinerjaBulanan;
use App\Models\PunishmentKinerja;
use Illuminate\Console\Command;

/**
 * Reset riwayat kinerja ke kondisi awal (sebelum deployment).
 * Menghapus rekap bulanan tersimpan + punishment, sehingga setiap guru mulai bersih.
 * Skor dihitung ulang per periode; komponen piket mulai dari baseline 100.
 * TIDAK menyentuh data sumber (absensi, jadwal, dll).
 */
class KinerjaReset extends Command
{
    protected $signature = 'kinerja:reset {--force : Jalankan tanpa konfirmasi}';
    protected $description = 'Reset riwayat kinerja (rekap bulanan + punishment) ke kondisi awal';

    public function handle(): int
    {
        $rekap = RekapKinerjaBulanan::count();
        $pun   = PunishmentKinerja::count();

        if ($rekap === 0 && $pun === 0) {
            $this->info('Tidak ada riwayat kinerja untuk direset.');
            return self::SUCCESS;
        }

        if (!$this->option('force')
            && !$this->confirm("Hapus {$rekap} rekap bulanan & {$pun} punishment kinerja? Tindakan ini tidak bisa dibatalkan.")) {
            $this->warn('Dibatalkan.');
            return self::FAILURE;
        }

        RekapKinerjaBulanan::query()->delete();
        PunishmentKinerja::query()->delete();

        $this->info("Reset selesai. Dihapus: {$rekap} rekap bulanan, {$pun} punishment. "
            . 'Semua guru mulai dari kinerja awal (komponen piket baseline 100).');
        return self::SUCCESS;
    }
}
