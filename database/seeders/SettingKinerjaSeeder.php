<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SettingKinerja;
use App\Models\User;

/**
 * Setting kinerja default dengan komponen Piket aktif:
 *   Absensi 35 / Tugas 30 / Administrasi 20 / Piket 15 (total 100).
 * Idempotent: bila sudah ada setting default, hanya bobot 4 komponen yang
 * disinkronkan (tidak menimpa tuning lain). Bila belum ada, buat baru.
 */
class SettingKinerjaSeeder extends Seeder
{
    public function run(): void
    {
        $row = SettingKinerja::where('is_default', true)->first()
            ?? SettingKinerja::first();

        if ($row) {
            $row->update([
                'bobot_absensi'      => 35,
                'bobot_tugas'        => 30,
                'bobot_administrasi' => 20,
                'bobot_piket'        => 15,
            ]);
            $this->command->info('SettingKinerjaSeeder: bobot setting default disinkron ke 35/30/20/15.');
            return;
        }

        SettingKinerja::create([
            'nama'                     => 'Standar Kinerja',
            'is_aktif'                 => true,
            'is_default'               => true,
            // Absensi
            'bobot_absensi'            => 35,
            'bobot_absensi_harian'     => 70,
            'bobot_absensi_mengajar'   => 30,
            'nilai_hadir'              => 100, 'nilai_terlambat' => 75, 'nilai_izin' => 70,
            'nilai_sakit'              => 80,  'nilai_dinas_luar' => 100, 'nilai_alfa' => 0,
            'hitung_penalty_terlambat' => false, 'toleransi_terlambat_menit' => 0,
            'penalty_per_terlambat'    => 5, 'max_penalty_terlambat' => 20,
            // Tugas
            'bobot_tugas'              => 30, 'bobot_tugas_tambahan' => 60, 'bobot_tugas_jabatan' => 40,
            'jika_tidak_ada_tugas'     => 'sempurna',
            // Administrasi
            'bobot_administrasi'       => 20, 'bobot_laporan_mengajar' => 60, 'bobot_log_kerja' => 40,
            'target_log_per_hari'      => 1,
            // Piket
            'bobot_piket'              => 15,
            // Grade
            'grade_a' => 90, 'grade_b' => 75, 'grade_c' => 60, 'grade_d' => 40,
            'dibuat_oleh' => User::where('role', 'super_admin')->value('id'),
        ]);

        $this->command->info('SettingKinerjaSeeder: setting default baru dibuat (35/30/20/15).');
    }
}
