<?php
// ══════════════════════════════════════════════════════════════════════════════
// database/seeders/SettingGajiSeeder.php
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Jabatan;
use App\Models\SettingGajiPokok;
use App\Models\SettingPotongan;
use App\Models\User;

class SettingGajiSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::where('role', 'super_admin')->first();

        // Gaji pokok per jabatan (contoh nominal, disesuaikan pesantren)
        $gajiPokok = [
            'KPS'   => 3_000_000,
            'WAKA'  => 2_500_000,
            'BEND'  => 2_000_000,
            'SEKR'  => 1_800_000,
            'GURU'  => 1_500_000,
            'WKLS'  => 1_700_000,
            'KBKUR' => 2_200_000,
            'KBSIS' => 2_200_000,
            'STAF'  => 1_300_000,
            'KMNK'  => 1_200_000,
        ];

        foreach ($gajiPokok as $kode => $nominal) {
            $jabatan = Jabatan::where('kode_jabatan', $kode)->first();
            if (!$jabatan) continue;

            SettingGajiPokok::firstOrCreate([
                'jabatan_id' => $jabatan->id,
                'is_aktif'   => true,
            ], [
                'nominal'       => $nominal,
                'berlaku_mulai' => '2025-01-01',
                'is_aktif'      => true,
                'dibuat_oleh'   => $superAdmin->id,
            ]);
        }

        // Potongan BPJS Kesehatan (1% dari gaji pokok — tanggungan karyawan)
        SettingPotongan::firstOrCreate(['kode' => 'BPJS_KESEHATAN'], [
            'nama'         => 'BPJS Kesehatan',
            'kategori'     => 'wajib',
            'tipe_pemicu'  => 'persen_gaji',
            'tipe_nominal' => 'persen',
            'nominal'      => 1,
            'lingkup'      => 'semua',
            'is_aktif'     => true,
        ]);

        // Potongan BPJS Ketenagakerjaan (2% dari gaji pokok)
        SettingPotongan::firstOrCreate(['kode' => 'BPJS_TK'], [
            'nama'         => 'BPJS Ketenagakerjaan',
            'kategori'     => 'wajib',
            'tipe_pemicu'  => 'persen_gaji',
            'tipe_nominal' => 'persen',
            'nominal'      => 2,
            'lingkup'      => 'semua',
            'is_aktif'     => true,
        ]);
    }
}