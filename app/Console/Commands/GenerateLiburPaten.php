<?php

namespace App\Console\Commands;

use App\Models\LiburTendik;
use App\Models\TenagaPendidik;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Jaga agar LIBUR PATEN guru asrama tidak pernah lapse.
 *
 * Generator libur rutin di layar admin bersifat MANUAL per bulan: admin memilih
 * guru, bulan, dan harinya. Akibatnya libur yang seharusnya "pasti" berhenti
 * begitu tidak ada yang menjalankannya — terukur 1 Nov 2026 baris terakhirnya
 * 30 Okt, sehingga November kosong dan Jumat 6 Nov keempat guru berlibur paten
 * akan dianggap masuk lalu dialfa.
 *
 * Command ini mengisi ke DEPAN, idempoten, dan sengaja konservatif:
 *   • hanya guru yang `hari_libur_paten`-nya terisi,
 *   • hanya tanggal yang BELUM punya baris libur apa pun (jadi tukar/pindah
 *     yang dibuat admin tidak ditimpa, dan libur pengajuan tidak diganggu),
 *   • tidak pernah menyentuh masa lalu — libur yang tidak tercatat pada bulan
 *     yang sudah lewat adalah fakta sejarah, bukan untuk ditambal belakangan
 *     karena gaji dan kinerja bulan itu sudah dihitung dari angka lama.
 */
class GenerateLiburPaten extends Command
{
    protected $signature = 'libur:paten-generate
        {--pekan=8 : Berapa pekan ke depan yang dijaga terisi}
        {--simulasi : Tampilkan rencananya tanpa menulis}';

    protected $description = 'Terbitkan libur paten guru asrama untuk beberapa pekan ke depan';

    public function handle(): int
    {
        $pekan    = max(1, (int) $this->option('pekan'));
        $simulasi = (bool) $this->option('simulasi');

        $hariIni = TimezoneHelper::today()->startOfDay();
        $sampai  = $hariIni->copy()->addWeeks($pekan);

        $guru = TenagaPendidik::aktif()
            ->whereNotNull('hari_libur_paten')
            ->with('user')
            ->get();

        if ($guru->isEmpty()) {
            $this->info('Tidak ada guru berlibur paten.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%s: %d guru · %s s/d %s',
            $simulasi ? 'SIMULASI' : 'Menerbitkan', $guru->count(),
            $hariIni->toDateString(), $sampai->toDateString()));

        $dibuat = 0;
        $dilewati = 0;

        foreach ($guru as $g) {
            $hari = (int) $g->hari_libur_paten;
            $tgl  = $hariIni->copy();

            // Maju ke kemunculan pertama hari itu (termasuk hari ini).
            while ($tgl->dayOfWeekIso !== $hari) $tgl->addDay();

            $milikDia = [];
            while ($tgl->lte($sampai)) {
                if (LiburTendik::isLibur($g->id, $tgl->toDateString())) {
                    $dilewati++;
                } else {
                    if (! $simulasi) {
                        LiburTendik::create([
                            'tenaga_pendidik_id' => $g->id,
                            'tanggal'            => $tgl->toDateString(),
                            'tipe'               => 'rutin',
                            'alasan'             => 'Libur paten pekanan',
                        ]);
                    }
                    $milikDia[] = $tgl->toDateString();
                    $dibuat++;
                }
                $tgl->addWeek();
            }

            $this->line(sprintf('  %-30s %s: %s',
                mb_strimwidth($g->user->name ?? ('Guru #' . $g->id), 0, 28, '..'),
                Carbon::now()->startOfWeek()->addDays($hari - 1)->locale('id')->isoFormat('dddd'),
                $milikDia ? implode(', ', $milikDia) : '(sudah lengkap)'));
        }

        $this->info(sprintf('%s %d baris, %d tanggal dilewati (sudah libur).',
            $simulasi ? 'Akan dibuat' : 'Dibuat', $dibuat, $dilewati));

        return self::SUCCESS;
    }
}
