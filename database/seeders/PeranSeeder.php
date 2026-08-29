<?php

namespace Database\Seeders;

use App\Models\Peran;
use App\Models\PeranModul;
use Illuminate\Database\Seeder;

/**
 * Seed 4 peran bawaan RBAC web admin. Idempotent (firstOrCreate).
 * Superadmin bebas menambah peran lain lewat UI.
 */
class PeranSeeder extends Seeder
{
    public function run(): void
    {
        $bawaan = [
            'administrasi' => [
                'nama'  => 'Administrasi',
                'desk'  => 'Kepegawaian, absensi, kinerja, tugas, pengajuan izin guru, penggajian, WhatsApp.',
                'modul' => ['absensi', 'kinerja', 'tugas', 'pengajuan_izin', 'penggajian', 'whatsapp'],
            ],
            'kurikulum' => [
                'nama'  => 'Kurikulum',
                'desk'  => 'Smart Education (santri, kelas, jurnal, tahfidz, tahsin, laporan) & Guru Piket.',
                'modul' => ['smart_education', 'piket'],
            ],
            'kesiswaan' => [
                'nama'  => 'Kesiswaan',
                'desk'  => 'Smart Controlling & Eksekusi, Perizinan Santri, Smart Health.',
                'modul' => ['smart_habbit', 'perizinan_santri', 'smart_health'],
            ],
            'sarana' => [
                'nama'  => 'Sarana',
                'desk'  => 'Inventaris & peminjaman.',
                'modul' => ['inventaris'],
            ],
        ];

        foreach ($bawaan as $kode => $d) {
            $peran = Peran::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $d['nama'], 'deskripsi' => $d['desk'], 'is_bawaan' => true, 'is_aktif' => true]
            );
            foreach ($d['modul'] as $m) {
                PeranModul::firstOrCreate(['peran_id' => $peran->id, 'modul' => $m]);
            }
        }
    }
}
