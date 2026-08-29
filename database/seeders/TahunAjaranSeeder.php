<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TahunAjaran;

class TahunAjaranSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama'           => '2023/2024',
                'semester'       => 'ganjil',
                'tanggal_mulai'  => '2023-07-17',
                'tanggal_selesai'=> '2023-12-22',
                'is_aktif'       => false,
            ],
            [
                'nama'           => '2023/2024',
                'semester'       => 'genap',
                'tanggal_mulai'  => '2024-01-08',
                'tanggal_selesai'=> '2024-06-28',
                'is_aktif'       => false,
            ],
            [
                'nama'           => '2024/2025',
                'semester'       => 'ganjil',
                'tanggal_mulai'  => '2024-07-15',
                'tanggal_selesai'=> '2024-12-20',
                'is_aktif'       => false,
            ],
            [
                'nama'           => '2024/2025',
                'semester'       => 'genap',
                'tanggal_mulai'  => '2025-01-06',
                'tanggal_selesai'=> '2025-06-27',
                'is_aktif'       => true, // ← semester aktif saat ini
            ],
        ];

        // Pastikan hanya 1 yang aktif
        TahunAjaran::query()->update(['is_aktif' => false]);

        foreach ($data as $item) {
            TahunAjaran::firstOrCreate(
                [
                    'nama'     => $item['nama'],
                    'semester' => $item['semester'],
                ],
                $item
            );
        }

        $this->command->info('TahunAjaranSeeder: ' . count($data) . ' data berhasil di-seed. Aktif: 2024/2025 Genap.');
    }
}
