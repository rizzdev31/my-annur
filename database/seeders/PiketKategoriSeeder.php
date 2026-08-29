<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PiketKategori;

/**
 * Rubrik kategori penilaian Guru Piket (default, bisa diubah admin di UI).
 * Poin disimpan POSITIF; jenis menentukan tanda (apresiasi +, catatan −).
 * Idempotent: firstOrCreate by nama.
 */
class PiketKategoriSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = [
            // ── Apresiasi (+) ─────────────────────────────────────────────
            ['nama' => 'Hadir & mengajar tepat waktu',   'jenis' => 'apresiasi', 'dimensi' => 'disiplin',     'poin' => 5],
            ['nama' => 'Mengisi jurnal/laporan lengkap',  'jenis' => 'apresiasi', 'dimensi' => 'administrasi', 'poin' => 5],
            ['nama' => 'Inisiatif membantu tugas',        'jenis' => 'apresiasi', 'dimensi' => 'tugas',        'poin' => 5],
            // ── Catatan (−) ───────────────────────────────────────────────
            ['nama' => 'Terlambat masuk kelas',           'jenis' => 'catatan',   'dimensi' => 'disiplin',     'poin' => 5],
            ['nama' => 'Tidak mengajar tanpa kabar',      'jenis' => 'catatan',   'dimensi' => 'disiplin',     'poin' => 10],
            ['nama' => 'Jurnal/administrasi tidak diisi',  'jenis' => 'catatan',   'dimensi' => 'administrasi', 'poin' => 5],
            ['nama' => 'Tugas piket tidak dikerjakan',    'jenis' => 'catatan',   'dimensi' => 'tugas',        'poin' => 5],
        ];

        foreach ($kategori as $k) {
            PiketKategori::firstOrCreate(
                ['nama' => $k['nama']],
                array_merge($k, ['is_aktif' => true]),
            );
        }

        $this->command->info('PiketKategoriSeeder: ' . count($kategori) . ' kategori rubrik di-seed.');
    }
}
