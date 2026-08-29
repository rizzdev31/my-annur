<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SettingVakasi;
use App\Models\User;

class SettingVakasiSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::where('role', 'super_admin')->first();

        $vakasi = [
            [
                'nama'                => 'Vakasi Hadir Harian',
                'tipe_aktivitas'      => 'absen_harian',
                'satuan'              => 'per_hari',
                'nominal'             => 15000,
                'berlaku_untuk_semua' => true,
                'jabatan_ids'         => null,
            ],
            [
                'nama'                => 'Vakasi Mengajar per JP',
                'tipe_aktivitas'      => 'absen_mengajar',
                'satuan'              => 'per_jp',
                'nominal'             => 20000,
                'berlaku_untuk_semua' => true,
                'jabatan_ids'         => null,
            ],
            [
                'nama'                => 'Vakasi Tugas Jabatan Struktural',
                'tipe_aktivitas'      => 'tugas_jabatan',
                'satuan'              => 'per_tugas',
                'nominal'             => 25000,
                'berlaku_untuk_semua' => false,
                'jabatan_ids'         => json_encode([1, 2, 3, 4, 7, 8]),
            ],
            [
                'nama'                => 'Vakasi Tugas Tambahan Umum',
                'tipe_aktivitas'      => 'tugas_tambahan',
                'satuan'              => 'per_tugas',
                'nominal'             => 50000,
                'berlaku_untuk_semua' => true,
                'jabatan_ids'         => null,
            ],
            [
                'nama'                => 'Vakasi Guru Piket',
                'tipe_aktivitas'      => 'piket',
                'satuan'              => 'per_hari',
                'nominal'             => 20000, // sesuaikan di Setting Vakasi
                'berlaku_untuk_semua' => true,
                'jabatan_ids'         => null,
            ],
        ];

        foreach ($vakasi as $v) {
            SettingVakasi::firstOrCreate(
                ['nama' => $v['nama']],
                array_merge($v, [
                    'berlaku_mulai' => '2025-01-01',
                    'berlaku_selesai' => null,
                    'is_aktif'      => true,
                    'dibuat_oleh'   => $superAdmin->id,
                ])
            );
        }

        $this->command->info('SettingVakasiSeeder: ' . count($vakasi) . ' vakasi berhasil di-seed.');
    }
}