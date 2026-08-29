<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MataPelajaran;

class MataPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $data = [

            // ── Mata Pelajaran Agama ─────────────────────────────────────────
            [
                'nama'     => 'Al-Quran & Tajwid',
                'kode'     => 'AGM-001',
                'kategori' => 'agama',
                'tingkat'  => null, // berlaku semua tingkat
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Fiqih',
                'kode'     => 'AGM-002',
                'kategori' => 'agama',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Aqidah Akhlak',
                'kode'     => 'AGM-003',
                'kategori' => 'agama',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Sejarah Kebudayaan Islam (SKI)',
                'kode'     => 'AGM-004',
                'kategori' => 'agama',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Bahasa Arab',
                'kode'     => 'AGM-005',
                'kategori' => 'agama',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Tafsir Al-Quran',
                'kode'     => 'AGM-006',
                'kategori' => 'agama',
                'tingkat'  => 'Wustho',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Hadits',
                'kode'     => 'AGM-007',
                'kategori' => 'agama',
                'tingkat'  => null,
                'is_aktif' => true,
            ],

            // ── Mata Pelajaran Pesantren (Kitab Kuning) ──────────────────────
            [
                'nama'     => 'Nahwu',
                'kode'     => 'PST-001',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ula',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Sharaf',
                'kode'     => 'PST-002',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ula',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Fiqih Kitab (Fathul Qarib)',
                'kode'     => 'PST-003',
                'kategori' => 'pesantren',
                'tingkat'  => 'Wustho',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Tauhid (Aqidatul Awam)',
                'kode'     => 'PST-004',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ula',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Akhlak (Ta\'lim Muta\'allim)',
                'kode'     => 'PST-005',
                'kategori' => 'pesantren',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Tarikh Islam',
                'kode'     => 'PST-006',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ula',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Balaghah',
                'kode'     => 'PST-007',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ulya',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Ushul Fiqih',
                'kode'     => 'PST-008',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ulya',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Alfiyah Ibnu Malik',
                'kode'     => 'PST-009',
                'kategori' => 'pesantren',
                'tingkat'  => 'Ulya',
                'is_aktif' => true,
            ],

            // ── Mata Pelajaran Umum ──────────────────────────────────────────
            [
                'nama'     => 'Matematika',
                'kode'     => 'UMM-001',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Bahasa Indonesia',
                'kode'     => 'UMM-002',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Bahasa Inggris',
                'kode'     => 'UMM-003',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Ilmu Pengetahuan Alam (IPA)',
                'kode'     => 'UMM-004',
                'kategori' => 'umum',
                'tingkat'  => 'VII',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Ilmu Pengetahuan Sosial (IPS)',
                'kode'     => 'UMM-005',
                'kategori' => 'umum',
                'tingkat'  => 'VII',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Pendidikan Pancasila',
                'kode'     => 'UMM-006',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Seni Budaya',
                'kode'     => 'UMM-007',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Pendidikan Jasmani (PJOK)',
                'kode'     => 'UMM-008',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Informatika',
                'kode'     => 'UMM-009',
                'kategori' => 'umum',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Fisika',
                'kode'     => 'UMM-010',
                'kategori' => 'umum',
                'tingkat'  => 'X',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Kimia',
                'kode'     => 'UMM-011',
                'kategori' => 'umum',
                'tingkat'  => 'X',
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Biologi',
                'kode'     => 'UMM-012',
                'kategori' => 'umum',
                'tingkat'  => 'X',
                'is_aktif' => true,
            ],

            // ── Ekstrakurikuler ──────────────────────────────────────────────
            [
                'nama'     => 'Tahfidz Al-Quran',
                'kode'     => 'EKS-001',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Kaligrafi',
                'kode'     => 'EKS-002',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Muhadharah (Latihan Pidato)',
                'kode'     => 'EKS-003',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Pramuka',
                'kode'     => 'EKS-004',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Rebana / Hadroh',
                'kode'     => 'EKS-005',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'Bahasa Arab Percakapan',
                'kode'     => 'EKS-006',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
            [
                'nama'     => 'English Club',
                'kode'     => 'EKS-007',
                'kategori' => 'ekstrakurikuler',
                'tingkat'  => null,
                'is_aktif' => true,
            ],
        ];

        foreach ($data as $item) {
            MataPelajaran::firstOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }

        $this->command->info('MataPelajaranSeeder: ' . count($data) . ' mata pelajaran berhasil di-seed.');
    }
}
