<?php

namespace App\Console\Commands;

use App\Services\RosterSatpamService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Jaga agar roster satpam selalu terisi beberapa blok ke depan.
 *
 * Dijadwalkan harian. Alasannya pahit dan sudah pernah terjadi di proyek ini:
 * libur paten guru asrama dulu digenerate MANUAL per bulan, lalu terlewat —
 * 1 Nov 2026 baris terakhirnya 30 Okt, dan empat guru nyaris dialfa pada hari
 * libur mereka sendiri. Roster satpam menjaga pos 24 jam; kalau ia habis
 * diam-diam, yang hilang bukan sekadar angka di slip.
 *
 * Idempoten: menulis ulang blok yang sudah ada dengan isi yang sama, dan
 * tidak pernah menyentuh tanggal sebelum jangkar.
 */
class GenerateRosterSatpam extends Command
{
    protected $signature = 'satpam:roster-generate
                            {--blok=8 : Berapa blok (2 pekan) dijaga terisi ke depan}
                            {--simulasi : Tampilkan saja, jangan tulis}';

    protected $description = 'Terbitkan/segarkan roster shift satpam yang berputar tiap 2 pekan';

    public function handle(RosterSatpamService $svc): int
    {
        $jangkar = Carbon::parse(RosterSatpamService::JANGKAR)->startOfDay();
        $hariIni = now()->startOfDay();

        // Mulai dari blok yang sedang berjalan, bukan dari jangkar — kalau
        // tidak, tiap hari ia menulis ulang seluruh sejarah roster.
        $blokBerjalan = $hariIni->lt($jangkar)
            ? 0
            : intdiv((int) $jangkar->diffInWeeks($hariIni), RosterSatpamService::PEKAN_PER_BLOK);

        $mulai = $jangkar->copy()->addWeeks($blokBerjalan * RosterSatpamService::PEKAN_PER_BLOK);
        $jumlah = max(1, (int) $this->option('blok'));

        $this->line(sprintf(
            'Jangkar %s · blok berjalan ke-%d (mulai %s) · menjaga %d blok ke depan',
            $jangkar->toDateString(), $blokBerjalan + 1, $mulai->toDateString(), $jumlah
        ));

        try {
            $hasil = $svc->terbitkan(
                $svc->susunanAwal($blokBerjalan),
                $mulai,
                $jumlah,
                (bool) $this->option('simulasi'),
            );
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($hasil['blok'] as $b) {
            $this->line(sprintf('  blok %-2d %s s/d %s   %s',
                $b['ke'], $b['dari'], $b['sampai'],
                collect($b['anggota'])->map(fn ($a) => $a['nama'] . '=' . $a['peran'])->implode(', ')
            ));
        }

        $this->info($this->option('simulasi')
            ? 'Simulasi — tidak ada yang ditulis.'
            : sprintf('Selesai. %d setting dibuat, %d baris shift ditulis.',
                $hasil['setting_dibuat'], $hasil['shift_ditulis']));

        return self::SUCCESS;
    }
}
