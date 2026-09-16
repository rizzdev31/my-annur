<?php

namespace App\Console\Commands;

use App\Models\PiketJadwal;
use App\Services\NotifikasiService;
use App\Services\SesiMengajarService;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Catat sesi mengajar yang lewat batas tanpa absen & jurnal sebagai
 * TIDAK TERLAKSANA, lalu kabari guru piket yang bertugas dan guru yang
 * bersangkutan. Aturannya ada di SesiMengajarService.
 *
 * Dijadwalkan tiap 5 menit agar piket tahu paling lambat 5 menit setelah
 * batas lewat — cukup cepat untuk masih bisa mengisi absensi santri di hari
 * yang sama. Aman diulang: sesi yang sudah tercatat tidak disentuh.
 */
class TandaiSesiTidakTerlaksana extends Command
{
    protected $signature = 'mengajar:tandai-tidak-terlaksana
        {--tanggal= : Proses satu tanggal (Y-m-d). Default: hari ini & kemarin}
        {--dari= : Isi mundur dari tanggal ini s/d kemarin — tanpa notifikasi}
        {--simulasi : Hitung saja, tidak menyimpan apa pun}';

    protected $description = 'Catat sesi mengajar yang tidak diisi sampai batas waktu sebagai tidak terlaksana';

    public function handle(SesiMengajarService $svc): int
    {
        $now       = TimezoneHelper::now();
        $simulasi  = (bool) $this->option('simulasi');

        // ── Isi mundur (eksplisit) ──────────────────────────────────────────
        if ($dari = $this->option('dari')) {
            $total = 0;
            $sampai = $now->copy()->subDay()->startOfDay();
            for ($d = Carbon::parse($dari, TimezoneHelper::TZ); $d->lte($sampai); $d->addDay()) {
                $n = $svc->tandaiLewatBatas($d->toDateString(), null, $now, true, $simulasi)->count();
                if ($n) $this->line("  {$d->toDateString()} : {$n} sesi");
                $total += $n;
            }
            $this->info(($simulasi ? '[SIMULASI] ' : '') . "Isi mundur selesai: {$total} sesi.");
            return self::SUCCESS;
        }

        // ── Rutin ───────────────────────────────────────────────────────────
        // Kemarin ikut diperiksa untuk sesi yang batasnya melewati tengah malam
        // atau bila scheduler sempat mati; tanggal berlaku tetap dihormati.
        $tanggalList = $this->option('tanggal')
            ? [$this->option('tanggal')]
            : [$now->copy()->subDay()->toDateString(), $now->toDateString()];

        $total = 0;
        foreach ($tanggalList as $tgl) {
            $dibuat = $svc->tandaiLewatBatas($tgl, null, $now, false, $simulasi);
            $total += $dibuat->count();

            if (!$simulasi && $tgl === $now->toDateString()) {
                $this->kabari($svc, $tgl, $dibuat->where('status', 'tidak_terlaksana')->values());
            }
        }

        $this->info(($simulasi ? '[SIMULASI] ' : '') . "mengajar:tandai-tidak-terlaksana → {$total} sesi dicatat.");
        return self::SUCCESS;
    }

    /**
     * Kabari piket (satu ringkasan per putaran) dan guru (per sesi).
     *
     * Piket dikabari karena merekalah yang masih bisa bertindak hari itu:
     * mengecek kelas dan mengisi absensi santri. Tanpa kabar ini piket harus
     * rajin membuka aplikasi untuk tahu ada guru yang lupa — dan itu yang
     * selama ini tidak terjadi.
     */
    private function kabari(SesiMengajarService $svc, string $tanggal, Collection $sesi): void
    {
        if ($sesi->isEmpty()) return;

        $baris = $sesi->map(function ($am) {
            $j = $am->jadwalMengajar;
            return ($j->mataPelajaran?->nama ?? 'KBM') . ' ' . ($j->kelasRel?->nama ?? $j->kelas)
                . ' — ' . ($j->tenagaPendidik?->user?->name ?? 'Guru')
                . ' (' . substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5) . ')';
        });

        $piket = PiketJadwal::whereDate('tanggal', $tanggal)->with('tenagaPendidik.user')->get()
            ->map(fn ($p) => $p->tenagaPendidik?->user)->filter()->unique('id')->values();

        if ($piket->isNotEmpty()) {
            NotifikasiService::event('mengajar.tidak_terlaksana', [
                'judul'     => $sesi->count() . ' sesi tidak terlaksana',
                'pesan'     => 'Guru tidak mengisi absen & jurnal sampai batas waktu: '
                    . $baris->take(4)->implode('; ')
                    . ($sesi->count() > 4 ? '; +' . ($sesi->count() - 4) . ' lainnya' : '')
                    . '. Cek kelas & isi absensi santri di menu Piket.',
                'tipe'      => 'tugas_update',
                'prioritas' => 'tinggi',
                'data'      => ['route' => '/piket'],
                'dedup'     => 'tt-piket-' . $tanggal . '-' . $sesi->pluck('id')->sort()->implode('.'),
            ], $piket->all());
        }

        foreach ($sesi as $am) {
            $j = $am->jadwalMengajar;
            $user = $j->tenagaPendidik?->user;
            if (!$user) continue;

            NotifikasiService::event('mengajar.tidak_terlaksana', [
                'judul' => 'Sesi tercatat tidak terlaksana',
                'pesan' => ($j->mataPelajaran?->nama ?? 'Sesi') . ' ' . ($j->kelasRel?->nama ?? $j->kelas)
                    . ' tidak diabsen sampai ' . $svc->batasJam($tanggal, (string) $j->jam_selesai)
                    . '. JP sesi ini tidak diberikan dan tercatat di kinerja. Absensi santri masih bisa diisi.',
                'tipe'  => 'tugas_update',
                'data'  => ['route' => '/mengajar'],
                'dedup' => 'tt-guru-' . $am->id,
            ], [$user]);
        }
    }
}
