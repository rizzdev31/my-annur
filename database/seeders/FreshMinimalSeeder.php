<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seed MINIMAL untuk reset bersih (start-from-Smart):
 *   - Super Admin (tanpa demo guru/santri)
 *   - Master & setting dasar agar aplikasi berfungsi & bisa menambah guru/santri
 *
 * Guru & santri BARU diisi dari UI (NIP/NISN baru) → lalu:
 *   php artisan ramahanak:sync-guru && php artisan ramahanak:sync-santri && php artisan queue:work
 *
 * Pakai: php artisan migrate:fresh  &&  php artisan db:seed --class=FreshMinimalSeeder
 * (JANGAN pakai DatabaseSeeder default — itu berisi demo guru/santri.)
 */
class FreshMinimalSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SuperadminSeeder::class,          // superadmin dulu (dibutuhkan setting gaji/vakasi)
            JabatanSeeder::class,             // FK jabatan untuk tambah guru
            TahunAjaranSeeder::class,
            SettingJamKerjaSeeder::class,
            SettingGajiSeeder::class,
            SettingVakasiSeeder::class,
            SettingKinerjaSeeder::class,
            PiketKategoriSeeder::class,
            MataPelajaranSeeder::class,
            SettingJenisPengajuanSeeder::class,
        ]);
    }
}
