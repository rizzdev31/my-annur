<?php
// ══════════════════════════════════════════════════════════════════════════════
// database/seeders/DatabaseSeeder.php
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Master data jabatan dulu (FK untuk tenaga pendidik)
            JabatanSeeder::class,

            // 2. User + Tenaga Pendidik (butuh jabatan)
            UserSeeder::class,
            TenagaPendidikSeeder::class,

            // 3. Tahun ajaran
            TahunAjaranSeeder::class,

            // 4. Setting sistem
            SettingJamKerjaSeeder::class,
            SettingGajiSeeder::class,       // butuh jabatan
            SettingVakasiSeeder::class,     // butuh user (superadmin) — termasuk vakasi piket
            SettingKinerjaSeeder::class,    // bobot 35/30/20/15 (piket aktif)
            PiketKategoriSeeder::class,     // rubrik penilaian guru piket

            // 5. Konten akademik
            MataPelajaranSeeder::class,

            SettingJenisPengajuanSeeder::class,
        ]);
    }
}