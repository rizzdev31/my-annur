<?php
// ══════════════════════════════════════════════════════════════════════════════
// database/seeders/SettingJamKerjaSeeder.php
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SettingJamKerja;
use App\Models\SettingAbsenMengajar;

class SettingJamKerjaSeeder extends Seeder
{
    public function run(): void
    {
        SettingJamKerja::firstOrCreate(['is_default' => true], [
            'nama'                 => 'Jam Kerja Harian An Nur',
            'jam_masuk'            => '07:00:00',
            'jam_pulang'           => '14:00:00',
            'toleransi_terlambat'  => 15,
            'hari_kerja'           => json_encode(['senin','selasa','rabu','kamis','jumat','sabtu']),
            'total_jam_kerja_sehari' => 420, // 7 jam = 420 menit
            'is_default'           => true,
            'is_aktif'             => true,
        ]);

        SettingAbsenMengajar::firstOrCreate(['is_default' => true], [
            'nama'                    => 'Setting JP Standar',
            'durasi_jp_menit'         => 45,
            'toleransi_checkin_menit' => 10,
            'is_default'              => true,
        ]);
    }
}