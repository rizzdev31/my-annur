<?php
// ══════════════════════════════════════════════════════════════════════════════
// database/seeders/JabatanSeeder.php
// ══════════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Jabatan;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        $jabatan = [
            ['nama_jabatan' => 'Kepala Pesantren',      'kode_jabatan' => 'KPS',  'tipe' => 'struktural',  'deskripsi' => 'Pimpinan tertinggi pesantren'],
            ['nama_jabatan' => 'Wakil Kepala',          'kode_jabatan' => 'WAKA', 'tipe' => 'struktural',  'deskripsi' => 'Wakil kepala pesantren'],
            ['nama_jabatan' => 'Bendahara',             'kode_jabatan' => 'BEND', 'tipe' => 'struktural',  'deskripsi' => 'Pengelola keuangan'],
            ['nama_jabatan' => 'Sekretaris',            'kode_jabatan' => 'SEKR', 'tipe' => 'struktural',  'deskripsi' => 'Administrasi umum'],
            ['nama_jabatan' => 'Guru / Ustadz',         'kode_jabatan' => 'GURU', 'tipe' => 'mengajar',    'deskripsi' => 'Tenaga pengajar'],
            ['nama_jabatan' => 'Wali Kelas',            'kode_jabatan' => 'WKLS', 'tipe' => 'mengajar',    'deskripsi' => 'Guru dengan tugas wali kelas'],
            ['nama_jabatan' => 'Kepala Bidang Kurikulum', 'kode_jabatan' => 'KBKUR', 'tipe' => 'struktural', 'deskripsi' => 'Koordinator kurikulum'],
            ['nama_jabatan' => 'Kepala Bidang Kesiswaan', 'kode_jabatan' => 'KBSIS', 'tipe' => 'struktural', 'deskripsi' => 'Koordinator kesiswaan & santri'],
            ['nama_jabatan' => 'Staff Administrasi',   'kode_jabatan' => 'STAF', 'tipe' => 'fungsional',  'deskripsi' => 'Tenaga administrasi'],
            ['nama_jabatan' => 'Petugas Keamanan',     'kode_jabatan' => 'KMNK', 'tipe' => 'fungsional',  'deskripsi' => 'Keamanan pesantren'],
        ];

        foreach ($jabatan as $j) {
            Jabatan::firstOrCreate(['kode_jabatan' => $j['kode_jabatan']], array_merge($j, ['is_aktif' => true]));
        }
    }
}