<?php

namespace App\Console\Commands;

use App\Models\AbsensiHarian;
use App\Models\HariLibur;
use App\Services\AbsensiKalkulasiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Selaraskan catatan absensi harian dengan aturan yang berlaku sekarang.
 *
 * Dua pekerjaan, keduanya idempoten & melewati baris yang dikoreksi manual:
 *  1. HITUNG ULANG status & menit terlambat memakai jam kerja/shift guru PADA
 *     TANGGAL ITU + izin datang terlambat yang disetujui. Dipakai setelah shift,
 *     jam kerja, atau izin berubah — dan untuk membereskan baris lama yang dulu
 *     ditimpa halaman admin memakai jam kerja default.
 *  2. TANDAI LIBUR: hari libur yang ditetapkan belakangan meninggalkan baris
 *     'alfa' yang terlanjur dibuat auto-alfa. Baris tanpa jam masuk diubah jadi
 *     'libur' (yang terlanjur check-in dibiarkan — mereka memang bekerja).
 */
class AbsensiSinkronStatus extends Command
{
    protected $signature = 'absensi:sinkron-status
        {--tanggal= : Satu tanggal (Y-m-d)}
        {--dari= : Tanggal awal rentang (Y-m-d)}
        {--sampai= : Tanggal akhir rentang (Y-m-d), default hari ini}
        {--simulasi : Tampilkan saja, tidak menyimpan}';

    protected $description = 'Selaraskan status absensi harian dengan jam kerja/shift, izin, dan hari libur';

    public function handle(): int
    {
        $simulasi = (bool) $this->option('simulasi');

        if ($this->option('tanggal')) {
            $dari = $sampai = Carbon::parse($this->option('tanggal'));
        } else {
            $dari   = Carbon::parse($this->option('dari') ?: now()->startOfMonth()->toDateString());
            $sampai = Carbon::parse($this->option('sampai') ?: now()->toDateString());
        }

        $libur = $this->tanggalLibur($dari, $sampai);
        $ubahStatus = 0; $ubahLibur = 0;

        for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
            $tgl = $t->toDateString();

            // 1. Hitung ulang status kehadiran.
            if ($simulasi) {
                foreach (AbsensiHarian::with('tenagaPendidik.user:id,name')->whereDate('tanggal', $tgl)
                    ->whereIn('status', ['hadir', 'terlambat'])->whereNotNull('jam_masuk')
                    ->where('is_koreksi', false)->get() as $a) {
                    $h = AbsensiKalkulasiService::hitungStatus($a->jam_masuk, $tgl, $a->tenagaPendidik);
                    if ($h['status'] === $a->status && (int) $h['menit_terlambat'] === (int) $a->menit_terlambat) continue;
                    $ubahStatus++;
                    $this->line(sprintf('  %s %-32s %s → %s/%dm (dari %s/%dm)', $tgl,
                        substr((string) $a->tenagaPendidik?->user?->name, 0, 32), substr((string) $a->jam_masuk, 0, 5),
                        $h['status'], $h['menit_terlambat'], $a->status, (int) $a->menit_terlambat));
                }
            } else {
                $ubahStatus += AbsensiKalkulasiService::rekalkuasiHarian($tgl);
            }

            // 2. Hari libur yang ditetapkan belakangan.
            if (!isset($libur[$tgl])) continue;

            $q = AbsensiHarian::whereDate('tanggal', $tgl)->where('status', 'alfa')
                ->whereNull('jam_masuk')->where('is_koreksi', false);
            $n = $simulasi ? $q->count() : $q->update([
                'status'     => 'libur',
                'keterangan' => 'Otomatis: hari libur ' . $libur[$tgl] . '.',
                'updated_at' => now(),
            ]);
            if ($n) {
                $ubahLibur += $n;
                $this->line("  {$tgl} libur ({$libur[$tgl]}): {$n} baris alfa → libur");
            }
        }

        $this->info(($simulasi ? '[SIMULASI] ' : '')
            . "Selesai {$dari->toDateString()} s/d {$sampai->toDateString()}: "
            . "{$ubahStatus} status disesuaikan, {$ubahLibur} baris ditandai libur.");

        return self::SUCCESS;
    }

    /** Peta tanggal → nama libur (mendukung libur beberapa hari). */
    private function tanggalLibur(Carbon $dari, Carbon $sampai): array
    {
        $peta = [];
        foreach (HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')->get() as $h) {
            $s = Carbon::parse($h->tanggal);
            $e = Carbon::parse($h->tanggal_selesai ?? $h->tanggal);
            while ($s->lte($e)) {
                if ($s->betweenIncluded($dari, $sampai)) $peta[$s->toDateString()] = $h->nama;
                $s->addDay();
            }
        }
        return $peta;
    }
}
