<?php

namespace App\Console\Commands;

use App\Models\Santri;
use App\Models\TenagaPendidik;
use App\Services\RamahAnakClient;
use Illuminate\Console\Command;

/**
 * Rekonsiliasi RamahAnak: kirim daftar NISN & NIP yang AKTIF di Smart → RA
 * menonaktifkan santri/guru hasil-sync yang sudah tidak ada/aktif di Smart.
 * Panggilan langsung (bukan outbox) — operasi manual/periodik, idempoten & aman.
 */
class RamahAnakRekonsiliasi extends Command
{
    protected $signature = 'ramahanak:rekonsiliasi {--dry : Tampilkan jumlah tanpa mengirim}';
    protected $description = 'Nonaktifkan santri/guru di RamahAnak yang sudah tak aktif di Smart (by NISN/NIP)';

    public function handle(RamahAnakClient $client): int
    {
        $nisnAktif = Santri::where('is_aktif', true)->whereNotNull('nip')->pluck('nip')
            ->map(fn($v) => (string) $v)->values()->all();
        $nipAktif  = TenagaPendidik::where('is_aktif', true)->whereNotNull('nip')->pluck('nip')
            ->map(fn($v) => (string) $v)->values()->all();

        $this->info('Santri aktif: ' . count($nisnAktif) . ' · Guru aktif: ' . count($nipAktif));

        if ($this->option('dry')) {
            $this->line('Mode --dry: tidak mengirim. RA akan menonaktifkan record hasil-sync di luar daftar ini.');
            return self::SUCCESS;
        }

        if (!config('ramahanak.enabled')) {
            $this->warn('RAMAHANAK_ENABLED=false. Nyalakan dulu di .env untuk rekonsiliasi.');
            return self::FAILURE;
        }

        $resp = $client->rekonsiliasi([
            'nisn_aktif' => $nisnAktif,
            'nip_aktif'  => $nipAktif,
            'app'        => config('ramahanak.app'),
        ]);

        if ($resp->successful() && ($resp->json('status') === 'ok')) {
            $b = $resp->json();
            $this->info("Rekonsiliasi OK. Santri dinonaktifkan: {$b['santri_dinonaktifkan']}"
                . ($b['santri_dilewati'] ? ' (dilewati: daftar kosong)' : '')
                . " · Guru dinonaktifkan: {$b['guru_dinonaktifkan']}"
                . ($b['guru_dilewati'] ? ' (dilewati: daftar kosong)' : ''));
            return self::SUCCESS;
        }

        $this->error('Rekonsiliasi gagal: HTTP ' . $resp->status() . ' ' . $resp->body());
        return self::FAILURE;
    }
}
