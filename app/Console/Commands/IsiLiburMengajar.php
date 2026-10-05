<?php

namespace App\Console\Commands;

use App\Services\LiburMengajarService;
use App\Services\TimezoneHelper;
use Illuminate\Console\Command;

/**
 * Tandai absensi mengajar (reguler/tahfidz/tahsin) sebagai 'libur' otomatis pada hari libur.
 * Dijadwalkan harian (lihat routes/console.php) agar tidak bergantung guru membuka aplikasi.
 */
class IsiLiburMengajar extends Command
{
    protected $signature = 'mengajar:isi-libur {--date= : Tanggal YYYY-MM-DD (default hari ini)}';
    protected $description = 'Auto-isi absensi mengajar menjadi "libur" pada hari libur (nasional/pesantren/darurat)';

    public function handle(LiburMengajarService $svc): int
    {
        $tgl = $this->option('date') ?: TimezoneHelper::today()->toDateString();

        $n = $svc->isiLiburTanggal($tgl);
        $this->info("Auto-libur mengajar {$tgl}: {$n} sesi ditandai 'libur'.");

        // Libur PEMBELAJARAN (kegiatan) yang tanggalnya tiba hari ini — saat
        // kegiatan dibuat, tanggal masa depan sengaja belum ditulis.
        $p = $svc->isiPembelajaranTanggal($tgl);
        if ($p['kegiatan'] > 0) {
            $this->info("Libur pembelajaran {$tgl}: {$p['kegiatan']} kegiatan, "
                . "{$p['sesi']} sesi, {$p['roster']} absensi santri.");
        }

        return self::SUCCESS;
    }
}
