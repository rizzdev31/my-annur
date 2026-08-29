<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SettingJenisPengajuan;

class SettingJenisPengajuanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'nama'                          => 'Sakit',
                'kode'                          => 'SAKIT',
                'kategori'                      => 'sakit',
                'deskripsi'                     => 'Izin tidak masuk karena sakit',
                'max_hari_per_pengajuan'        => 14,
                'kuota_per_tahun'               => null,
                'min_hari_pengajuan_sebelumnya' => 0,
                'butuh_dokumen'                 => false,
                'keterangan_dokumen'            => 'Surat dokter (jika lebih dari 2 hari)',
                'auto_approve'                  => true,
                'pengaruh_gaji'                 => 'potong_absensi',
                'update_status_kepegawaian'     => true,
                'status_kepegawaian_tujuan'     => 'cuti_sakit',
                'min_hari_untuk_update_status'  => 3,
            ],
            [
                'nama'                          => 'Izin',
                'kode'                          => 'IZIN',
                'kategori'                      => 'izin',
                'deskripsi'                     => 'Izin keperluan pribadi atau keluarga',
                'max_hari_per_pengajuan'        => 3,
                'kuota_per_tahun'               => 12,
                'min_hari_pengajuan_sebelumnya' => 1,
                'butuh_dokumen'                 => false,
                'keterangan_dokumen'            => null,
                'auto_approve'                  => false,
                'pengaruh_gaji'                 => 'potong_absensi',
                'update_status_kepegawaian'     => false,
                'status_kepegawaian_tujuan'     => null,
                'min_hari_untuk_update_status'  => 0,
            ],
            [
                'nama'                          => 'Izin Mendadak',
                'kode'                          => 'IZIN_MENDADAK',
                'kategori'                      => 'izin',
                'deskripsi'                     => 'Izin mendadak hari itu juga',
                'max_hari_per_pengajuan'        => 1,
                'kuota_per_tahun'               => 6,
                'min_hari_pengajuan_sebelumnya' => 0,
                'butuh_dokumen'                 => false,
                'keterangan_dokumen'            => null,
                'auto_approve'                  => true,
                'pengaruh_gaji'                 => 'potong_absensi',
                'update_status_kepegawaian'     => false,
                'status_kepegawaian_tujuan'     => null,
                'min_hari_untuk_update_status'  => 0,
            ],
            [
                'nama'                          => 'Cuti Tahunan',
                'kode'                          => 'CUTI_TAHUNAN',
                'kategori'                      => 'cuti',
                'deskripsi'                     => 'Cuti tahunan resmi',
                'max_hari_per_pengajuan'        => 12,
                'kuota_per_tahun'               => 12,
                'min_hari_pengajuan_sebelumnya' => 3,
                'butuh_dokumen'                 => false,
                'keterangan_dokumen'            => null,
                'auto_approve'                  => false,
                'pengaruh_gaji'                 => 'tidak_potong',
                'update_status_kepegawaian'     => true,
                'status_kepegawaian_tujuan'     => 'cuti',
                'min_hari_untuk_update_status'  => 3,
            ],
            [
                'nama'                          => 'Cuti Melahirkan',
                'kode'                          => 'CUTI_MELAHIRKAN',
                'kategori'                      => 'cuti',
                'deskripsi'                     => 'Cuti melahirkan untuk tenaga pendidik perempuan',
                'max_hari_per_pengajuan'        => 90,
                'kuota_per_tahun'               => 90,
                'min_hari_pengajuan_sebelumnya' => 14,
                'butuh_dokumen'                 => true,
                'keterangan_dokumen'            => 'Surat keterangan hamil dari dokter / bidan',
                'auto_approve'                  => false,
                'pengaruh_gaji'                 => 'tidak_potong',
                'update_status_kepegawaian'     => true,
                'status_kepegawaian_tujuan'     => 'cuti',
                'min_hari_untuk_update_status'  => 1,
            ],
            [
                'nama'                          => 'Dinas Luar',
                'kode'                          => 'DINAS_LUAR',
                'kategori'                      => 'dinas',
                'deskripsi'                     => 'Perjalanan dinas atas perintah pesantren',
                'max_hari_per_pengajuan'        => 30,
                'kuota_per_tahun'               => null,
                'min_hari_pengajuan_sebelumnya' => 1,
                'butuh_dokumen'                 => true,
                'keterangan_dokumen'            => 'Surat tugas dari pimpinan',
                'auto_approve'                  => false,
                'pengaruh_gaji'                 => 'tidak_potong',
                'update_status_kepegawaian'     => false,
                'status_kepegawaian_tujuan'     => null,
                'min_hari_untuk_update_status'  => 0,
            ],
        ];

        foreach ($data as $item) {
            SettingJenisPengajuan::firstOrCreate(
                ['kode' => $item['kode']],
                array_merge($item, ['is_aktif' => true])
            );
        }

        $this->command->info('SettingJenisPengajuanSeeder: ' . count($data) . ' jenis pengajuan berhasil di-seed.');
    }
}