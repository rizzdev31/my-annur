<?php

namespace App\Console\Commands;

use App\Models\PeriodePenggajian;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Memastikan selalu ada periode penggajian yang mencakup hari ini.
 *
 * Periode di An-Nur berjalan dari tanggal 26 ke tanggal 25 bulan berikutnya.
 * Tanpa perintah ini, hari-hari setelah periode berakhir tidak punya periode
 * (pernah terjadi: 26–28 September tidak tercakup periode mana pun), sehingga
 * kinerja jatuh kembali ke bulan kalender dan tidak sejalan dengan slip gaji.
 *
 * Periode baru dibuat mengikuti pola periode terakhir: mulai = sehari setelah
 * periode sebelumnya berakhir, selesai = tanggal yang sama bulan berikutnya,
 * dan labelnya memakai bulan tanggal SELESAI (mis. 26 Sep–25 Okt = "Oktober").
 */
class PeriodePenggajianBerikutnya extends Command
{
    protected $signature = 'periode:pastikan-berikutnya
        {--tanggal= : Anggap hari ini tanggal tertentu (untuk pengujian)}
        {--simulasi : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Buat periode penggajian berikutnya (26→25) bila hari ini belum tercakup';

    public function handle(): int
    {
        $hariIni = Carbon::parse($this->option('tanggal') ?: now()->toDateString());

        if ($ada = PeriodePenggajian::untukTanggal($hariIni->toDateString())) {
            $this->info("Sudah tercakup periode {$ada->nama_bulan} "
                . "({$ada->tanggal_mulai->toDateString()} → {$ada->tanggal_selesai->toDateString()}).");
            return self::SUCCESS;
        }

        $terakhir = PeriodePenggajian::orderByDesc('tanggal_selesai')->first();
        if (!$terakhir) {
            $this->error('Belum ada satu periode pun sebagai acuan pola. Buat periode pertama dari halaman admin.');
            return self::FAILURE;
        }

        // Kejar sampai ada periode yang mencakup hari ini (kalau tertinggal
        // beberapa bulan, dibuat berurutan tanpa celah).
        $dibuat = 0;
        while (!PeriodePenggajian::untukTanggal($hariIni->toDateString())) {
            $terakhir = PeriodePenggajian::orderByDesc('tanggal_selesai')->first();
            $mulai   = Carbon::parse($terakhir->tanggal_selesai)->addDay();
            $selesai = $mulai->copy()->addMonthNoOverflow()->subDay();
            $bulan   = (int) $selesai->month;
            $tahun   = (int) $selesai->year;
            $nama    = 'Penggajian ' . $selesai->locale('id')->isoFormat('MMMM YYYY');

            if (PeriodePenggajian::untukLabel($bulan, $tahun)) {
                $this->warn("Periode berlabel {$bulan}/{$tahun} sudah ada — dihentikan agar tidak ganda.");
                break;
            }

            $this->line("Membuat: {$nama} ({$mulai->toDateString()} → {$selesai->toDateString()})");
            if ($this->option('simulasi')) break;

            PeriodePenggajian::create([
                'nama'            => $nama,
                'bulan'           => $bulan,
                'tahun'           => $tahun,
                'tanggal_mulai'   => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'status'          => 'draft',
                // Kolom ini NOT NULL — periode otomatis dicatat atas nama
                // pembuat periode sebelumnya, atau superadmin pertama.
                'dibuat_oleh'     => $terakhir->dibuat_oleh
                    ?? \App\Models\User::where('role', 'superadmin')->value('id')
                    ?? \App\Models\User::value('id'),
            ]);
            $dibuat++;

            if ($dibuat >= 12) break;   // jaring pengaman
        }

        $this->info($this->option('simulasi') ? 'Simulasi selesai.' : "{$dibuat} periode dibuat.");
        return self::SUCCESS;
    }
}
