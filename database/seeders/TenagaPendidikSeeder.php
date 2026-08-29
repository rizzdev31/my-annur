<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use Illuminate\Support\Facades\Hash;

class TenagaPendidikSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'user' => [
                    'name'     => 'Ust. Ahmad Fauzi',
                    'username' => 'ahmad.fauzi',
                    'email'    => 'ahmad@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'KPS',
                    'nip'                => 'TP-2020-001',
                    'nik'                => '3522010101800001',
                    'tempat_lahir'       => 'Bojonegoro',
                    'tanggal_lahir'      => '1980-01-01',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S2',
                    'jurusan'            => 'Pendidikan Agama Islam',
                    'no_hp'              => '081234560001',
                    'alamat'             => 'Jl. Pesantren No. 1, Bojonegoro',
                    'tanggal_masuk'      => '2020-01-01',
                    'no_rekening'        => '1234567890001',
                    'nama_bank'          => 'BSI',
                    'nama_rekening'      => 'Ahmad Fauzi',
                    'jenis_guru'         => 'mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Muhammad Ridwan',
                    'username' => 'muh.ridwan',
                    'email'    => 'ridwan@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'KBKUR',
                    'nip'                => 'TP-2020-002',
                    'nik'                => '3522010202850002',
                    'tempat_lahir'       => 'Lamongan',
                    'tanggal_lahir'      => '1985-02-02',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Tarbiyah',
                    'no_hp'              => '081234560002',
                    'alamat'             => 'Jl. Masjid No. 5, Lamongan',
                    'tanggal_masuk'      => '2020-03-01',
                    'no_rekening'        => '1234567890002',
                    'nama_bank'          => 'BSI',
                    'nama_rekening'      => 'Muhammad Ridwan',
                    'jenis_guru'         => 'mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Usth. Siti Fatimah',
                    'username' => 'siti.fatimah',
                    'email'    => 'siti@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'WKLS',
                    'nip'                => 'TP-2021-003',
                    'nik'                => '3522010303900003',
                    'tempat_lahir'       => 'Gresik',
                    'tanggal_lahir'      => '1990-03-03',
                    'jenis_kelamin'      => 'P',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Bahasa Arab',
                    'no_hp'              => '081234560003',
                    'alamat'             => 'Jl. Kyai Haji No. 3, Gresik',
                    'tanggal_masuk'      => '2021-01-01',
                    'no_rekening'        => '1234567890003',
                    'nama_bank'          => 'BRI',
                    'nama_rekening'      => 'Siti Fatimah',
                    'jenis_guru'         => 'non_mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Abdul Aziz',
                    'username' => 'abdul.aziz',
                    'email'    => 'aziz@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'GURU',
                    'nip'                => 'TP-2021-004',
                    'nik'                => '3522010404880004',
                    'tempat_lahir'       => 'Tuban',
                    'tanggal_lahir'      => '1988-04-04',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Matematika',
                    'no_hp'              => '081234560004',
                    'alamat'             => 'Jl. Pondok No. 7, Tuban',
                    'tanggal_masuk'      => '2021-07-01',
                    'no_rekening'        => '1234567890004',
                    'nama_bank'          => 'BNI',
                    'nama_rekening'      => 'Abdul Aziz',
                    'jenis_guru'         => 'non_mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Hasan Basri',
                    'username' => 'hasan.basri',
                    'email'    => 'hasan@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'GURU',
                    'nip'                => 'TP-2022-005',
                    'nik'                => '3522010505920005',
                    'tempat_lahir'       => 'Mojokerto',
                    'tanggal_lahir'      => '1992-05-05',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Ilmu Al-Quran dan Tafsir',
                    'no_hp'              => '081234560005',
                    'alamat'             => 'Jl. Santri No. 2, Mojokerto',
                    'tanggal_masuk'      => '2022-01-01',
                    'no_rekening'        => '1234567890005',
                    'nama_bank'          => 'BSI',
                    'nama_rekening'      => 'Hasan Basri',
                    'jenis_guru'         => 'mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Usth. Nur Aini',
                    'username' => 'nur.aini',
                    'email'    => 'nuraini@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'BEND',
                    'nip'                => 'TP-2020-006',
                    'nik'                => '3522010606870006',
                    'tempat_lahir'       => 'Bojonegoro',
                    'tanggal_lahir'      => '1987-06-06',
                    'jenis_kelamin'      => 'P',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Akuntansi',
                    'no_hp'              => '081234560006',
                    'alamat'             => 'Jl. Raya Pesantren No. 10, Bojonegoro',
                    'tanggal_masuk'      => '2020-01-01',
                    'no_rekening'        => '1234567890006',
                    'nama_bank'          => 'BRI',
                    'nama_rekening'      => 'Nur Aini',
                    'jenis_guru'         => 'non_mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Zainal Abidin',
                    'username' => 'zainal.abidin',
                    'email'    => 'zainal@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'KBSIS',
                    'nip'                => 'TP-2020-007',
                    'nik'                => '3522010707830007',
                    'tempat_lahir'       => 'Kediri',
                    'tanggal_lahir'      => '1983-07-07',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Manajemen Pendidikan',
                    'no_hp'              => '081234560007',
                    'alamat'             => 'Jl. Ulama No. 4, Kediri',
                    'tanggal_masuk'      => '2020-01-01',
                    'no_rekening'        => '1234567890007',
                    'nama_bank'          => 'BSI',
                    'nama_rekening'      => 'Zainal Abidin',
                    'jenis_guru'         => 'mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Imam Syafi\'i',
                    'username' => 'imam.syafii',
                    'email'    => 'imam@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'GURU',
                    'nip'                => 'TP-2022-008',
                    'nik'                => '3522010808910008',
                    'tempat_lahir'       => 'Jombang',
                    'tanggal_lahir'      => '1991-08-08',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'Pesantren',
                    'jurusan'            => 'Fiqih & Ushul Fiqih',
                    'no_hp'              => '081234560008',
                    'alamat'             => 'Jl. Tebuireng No. 9, Jombang',
                    'tanggal_masuk'      => '2022-07-01',
                    'no_rekening'        => '1234567890008',
                    'nama_bank'          => 'BSI',
                    'nama_rekening'      => 'Imam Syafi\'i',
                    'jenis_guru'         => 'mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Usth. Aisyah Rahmawati',
                    'username' => 'aisyah.rahmawati',
                    'email'    => 'aisyah@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'WKLS',
                    'nip'                => 'TP-2023-009',
                    'nik'                => '3522010909950009',
                    'tempat_lahir'       => 'Sidoarjo',
                    'tanggal_lahir'      => '1995-09-09',
                    'jenis_kelamin'      => 'P',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Bahasa Inggris',
                    'no_hp'              => '081234560009',
                    'alamat'             => 'Jl. Pondok Indah No. 6, Sidoarjo',
                    'tanggal_masuk'      => '2023-01-01',
                    'no_rekening'        => '1234567890009',
                    'nama_bank'          => 'BNI',
                    'nama_rekening'      => 'Aisyah Rahmawati',
                    'jenis_guru'         => 'non_mukim',
                    'is_aktif'           => true,
                ],
            ],
            [
                'user' => [
                    'name'     => 'Ust. Mahmud Yunus',
                    'username' => 'mahmud.yunus',
                    'email'    => 'mahmud@annur.sch.id',
                    'password' => Hash::make('password'),
                    'role'     => 'tenaga_pendidik',
                    'status'   => 'aktif',
                ],
                'profil' => [
                    'kode_jabatan'       => 'STAF',
                    'nip'                => 'TP-2023-010',
                    'nik'                => '3522011010930010',
                    'tempat_lahir'       => 'Bojonegoro',
                    'tanggal_lahir'      => '1993-10-10',
                    'jenis_kelamin'      => 'L',
                    'pendidikan_terakhir'=> 'S1',
                    'jurusan'            => 'Teknik Informatika',
                    'no_hp'              => '081234560010',
                    'alamat'             => 'Jl. Merdeka No. 11, Bojonegoro',
                    'tanggal_masuk'      => '2023-06-01',
                    'no_rekening'        => '1234567890010',
                    'nama_bank'          => 'BRI',
                    'nama_rekening'      => 'Mahmud Yunus',
                    'jenis_guru'         => 'non_mukim',
                    'is_aktif'           => true,
                ],
            ],
        ];

        foreach ($data as $item) {
            // Upsert user
            $user = User::firstOrCreate(
                ['email' => $item['user']['email']],
                $item['user']
            );

            // Ambil jabatan
            $jabatan = Jabatan::where('kode_jabatan', $item['profil']['kode_jabatan'])->first();

            if (!$jabatan) {
                $this->command->warn("Jabatan {$item['profil']['kode_jabatan']} tidak ditemukan, skip.");
                continue;
            }

            // Upsert tenaga pendidik
            TenagaPendidik::firstOrCreate(
                ['nip' => $item['profil']['nip']],
                array_merge(
                    collect($item['profil'])->except('kode_jabatan')->toArray(),
                    [
                        'user_id'    => $user->id,
                        'jabatan_id' => $jabatan->id,
                    ]
                )
            );
        }

        $this->command->info('TenagaPendidikSeeder: ' . count($data) . ' data berhasil di-seed.');
    }
}
