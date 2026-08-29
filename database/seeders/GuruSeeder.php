<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GuruSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataGuru = [
            [
                'nama_lengkap'  => 'H. MUNIF HASAN, S.Ag., MA',
                'nip'           => '19863748',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '15 MEI 1959',
            ],
            [
                'nama_lengkap'  => 'WIDIYANTI, S.Pd., Gr., MM.',
                'nip'           => '19850312',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '20 DESEMBER 1983',
            ],
            [
                'nama_lengkap'  => 'ACHMAD FACHRUDDIN IBRAHIM, M.Pd.',
                'nip'           => '19920724',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '30 NOPEMBER 1994',
            ],
            [
                'nama_lengkap'  => 'Drs. ANWAR ICHSAN, M.Ag.',
                'nip'           => '19881105',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '4 MEI 1957',
            ],
            [
                'nama_lengkap'  => 'FAIZAH KHILMIYAH, S.Pd.I.',
                'nip'           => '19950419',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => 'Minggu, April 05, 1992',
            ],
            [
                'nama_lengkap'  => 'MABAFASA AL KHULUQIY, S.Kom.',
                'nip'           => '19830928',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '27 JUNI 1995',
            ],
            [
                'nama_lengkap'  => 'BINTA KHUMAIROH, S.Pd.',
                'nip'           => '19910115',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '20 JANUARI 1996',
            ],
            [
                'nama_lengkap'  => 'FEBBIANTI WIDIA SANTOSO, M.Pd.',
                'nip'           => '19870630',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '3 FEBRUARI 1994',
            ],
            [
                'nama_lengkap'  => 'MOH. INFANTRI AGUNG SAPUTRA',
                'nip'           => '19941208',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Minggu, September 05, 1993',
            ],
            [
                'nama_lengkap'  => 'IRFANDI AMIRUDDIN, Lc., M. Pd.',
                'nip'           => '19820521',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2 MARET 1992',
            ],
            [
                'nama_lengkap'  => 'DIANITA WAHYU NINGSIH, S.Pd.,Gr., M.Pd.',
                'nip'           => '19901014',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '16 NOPEMBER 1996',
            ],
            [
                'nama_lengkap'  => 'M. LUTFI SYAIFUDDIN UMAR, S.Pd.',
                'nip'           => '19860803',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '17 MARET 1994',
            ],
            [
                'nama_lengkap'  => 'I’ANATUS SHOLIHAH, S.Pd. Gr.',
                'nip'           => '19930227',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '23 APRIL 1989',
            ],
            [
                'nama_lengkap'  => 'ZAKIYYATUL AINI, S.Pd.',
                'nip'           => '19890711',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '28 SEPTEMBER 1995',
            ],
            [
                'nama_lengkap'  => 'AHMAD KHOBIR, M.Ag.',
                'nip'           => '19960325',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '11 MEI 1958',
            ],
            [
                'nama_lengkap'  => 'CONNY MEGA PRAHASTIWI, S.Pd.',
                'nip'           => '19841118',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '10 JANUARI 1996',
            ],
            [
                'nama_lengkap'  => 'RISKA NURDIYANTI, S.Pd.',
                'nip'           => '19920902',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '17 MARET 1999',
            ],
            [
                'nama_lengkap'  => 'RIA ARDIANTI, S.Pd.',
                'nip'           => '19870416',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '25 JULI 1996',
            ],
            [
                'nama_lengkap'  => 'ASFI MAGHFIRAH, SH., M.Pd.',
                'nip'           => '19951229',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '25 AGUSTUS 1995',
            ],
            [
                'nama_lengkap'  => 'RIZQIYAH ROSANDA, S.Pd.',
                'nip'           => '19810807',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '06 JULI 2000',
            ],
            [
                'nama_lengkap'  => 'IMROATUS SHOLIKHAH, S.Pd.',
                'nip'           => '19900322',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => 'Selasa, September 06, 1988',
            ],
            [
                'nama_lengkap'  => 'FATHIN FURAIDHA',
                'nip'           => '19861009',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '17 JUNI 1998',
            ],
            [
                'nama_lengkap'  => 'ANANG MA\'RUP',
                'nip'           => '19940513',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Rabu, April 03, 2002',
            ],
            [
                'nama_lengkap'  => 'MUHAMMAD NUR RIFQI BAHARIS',
                'nip'           => '19880226',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '31 Juli 2002',
            ],
            [
                'nama_lengkap'  => 'KHOIRUN NISA\'',
                'nip'           => '19970117',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '12 JULI 1997',
            ],
            [
                'nama_lengkap'  => 'MUHAMMAD DAFFA RAMADHANI',
                'nip'           => '19831204',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '21 November 2000',
            ],
            [
                'nama_lengkap'  => 'HANIYYAH AFIFATU THOHIROH',
                'nip'           => '19910831',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '15 Desember 2001',
            ],
            [
                'nama_lengkap'  => 'YUSUF BASWEDAN',
                'nip'           => '19850615',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '24 April 2003',
            ],
            [
                'nama_lengkap'  => 'KUNTUM FARKHA ELSAIF',
                'nip'           => '19931120',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => 'Jumat, November 01, 2002',
            ],
            [
                'nama_lengkap'  => 'AMILAH FAIDATUL AINAINI',
                'nip'           => '19890408',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '24 Pebruari 2003',
            ],
            [
                'nama_lengkap'  => 'DZULFIKAR AKBAR ROMADLON, M.Ud.',
                'nip'           => '19960914',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '19 MARET 1991',
            ],
            [
                'nama_lengkap'  => 'ERNANDA AULIA FAZA, Lc.',
                'nip'           => '19820123',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '30 NOPEMBER 2000',
            ],
            [
                'nama_lengkap'  => 'ARDIANSYAH, Lc.,',
                'nip'           => '19900706',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '12 NOPEMBER 2000',
            ],
            [
                'nama_lengkap'  => 'THORIQ AQSHA RAMADHAN',
                'nip'           => '19871219',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '4 Desember 2002',
            ],
            [
                'nama_lengkap'  => 'NAZWA AMELIA YUFIDAH',
                'nip'           => '19950601',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '1 DESEMBER 2005',
            ],
            [
                'nama_lengkap'  => 'MUHAMMAD HAFIZH FATTAH',
                'nip'           => '19840310',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Jumat, Desember 02, 2005',
            ],
            [
                'nama_lengkap'  => 'M. ABI YASA',
                'nip'           => '19921025',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Jumat, Desember 02, 2005',
            ],
            [
                'nama_lengkap'  => 'MUHAMMAD SADDAM RIZQULLAH',
                'nip'           => '19880814',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Minggu, Desember 04, 2005',
            ],
            [
                'nama_lengkap'  => 'ELSA DARLIANA, SE.',
                'nip'           => '19970402',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '12 SEPTEMBER 1996',
            ],
            [
                'nama_lengkap'  => 'RIZA ARISTA FIRANA, S.Ak.',
                'nip'           => '19830517',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '5 MEI 2000',
            ],
            [
                'nama_lengkap'  => 'ANASTHASYA NABILA UQDI, S.S.T.',
                'nip'           => '19911230',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '24 JANUARI 1996',
            ],
            [
                'nama_lengkap'  => 'ABDUL MUIZ HENGGAR TRI SUSILO',
                'nip'           => '19860209',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '3  MARET 2000',
            ],
            [
                'nama_lengkap'  => 'JAZULI',
                'nip'           => '19940921',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '5 JULI 1970',
            ],
            [
                'nama_lengkap'  => 'IRFAN FAKHRUR RAZI, S.Pd.',
                'nip'           => '19891113',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '26 OKTOBER 1998',
            ],
            [
                'nama_lengkap'  => 'SITI FATIMATUZ ZUHRO',
                'nip'           => '19960704',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '3 JUNI 1978',
            ],
            [
                'nama_lengkap'  => 'YUNUS',
                'nip'           => '19850128',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => 'Kamis, Desember 15, 2005',
            ],
        ];

        DB::transaction(function () use ($dataGuru) {
            // Ambil ID default jabatan "Guru / Ustadz"
            $jabatanId = DB::table('jabatan')
                ->where('nama_jabatan', 'Guru / Ustadz')
                ->value('id');

            if (!$jabatanId) {
                $jabatanId = DB::table('jabatan')
                    ->where('nama_jabatan', 'like', '%Guru%')
                    ->value('id');
            }

            if (!$jabatanId) {
                try {
                    $jabatanId = DB::table('jabatan')->insertGetId([
                        'nama_jabatan' => 'Guru / Ustadz',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                } catch (\Throwable $e) {
                    $jabatanId = 1;
                }
            }

            $defaultTanggalMasuk = now()->toDateString();

            foreach ($dataGuru as $guru) {
                $nama     = trim($guru['nama_lengkap']);
                $nip      = trim((string) $guru['nip']);
                $email    = "{$nip}@guru.annur.local";
                $username = $nip;
                $jk       = $this->normalJK($guru['jenis_kelamin'] ?? null);
                $tglLahir = $this->normalTgl($guru['tanggal_lahir'] ?? null);
                $plainPassword = $this->passwordDariTgl($tglLahir, $nip);

                // 1) Upsert Tabel Users by email
                $existingUser = DB::table('users')->where('email', $email)->first();

                if ($existingUser) {
                    DB::table('users')->where('id', $existingUser->id)->update([
                        'name'       => $nama,
                        'username'   => $username,
                        'password'   => Hash::make($plainPassword),
                        'role'       => 'tenaga_pendidik',
                        'status'     => 'aktif',
                        'updated_at' => now(),
                    ]);
                    $userId = $existingUser->id;
                } else {
                    $userId = DB::table('users')->insertGetId([
                        'name'       => $nama,
                        'email'      => $email,
                        'username'   => $username,
                        'password'   => Hash::make($plainPassword),
                        'role'       => 'tenaga_pendidik',
                        'status'     => 'aktif',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 2) Upsert Tabel Tenaga Pendidik by nip
                $existingPendidik = DB::table('tenaga_pendidik')->where('nip', $nip)->first();

                if ($existingPendidik) {
                    DB::table('tenaga_pendidik')->where('nip', $nip)->update([
                        'user_id'       => $userId,
                        'jenis_kelamin' => $jk,
                        'tanggal_lahir' => $tglLahir,
                        'jabatan_id'    => $existingPendidik->jabatan_id ?? $jabatanId,
                        'tanggal_masuk' => $existingPendidik->tanggal_masuk ?? $defaultTanggalMasuk,
                        'is_aktif'      => true,
                        'updated_at'    => now(),
                    ]);
                } else {
                    DB::table('tenaga_pendidik')->insert([
                        'user_id'       => $userId,
                        'nip'           => $nip,
                        'jenis_kelamin' => $jk,
                        'tanggal_lahir' => $tglLahir,
                        'jabatan_id'    => $jabatanId,
                        'tanggal_masuk' => $defaultTanggalMasuk,
                        'is_aktif'      => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Helper: Generate password string (DDMMYYYY atau fallback ke NIP)
     */
    private function passwordDariTgl(?string $tgl, string|int $nip): string
    {
        if (empty($tgl)) {
            return (string) $nip;
        }

        $norm = $this->normalTgl($tgl);
        if (!$norm) {
            return (string) $nip;
        }

        $parts = explode('-', $norm);
        if (count($parts) === 3) {
            return $parts[2] . $parts[1] . $parts[0]; // DDMMYYYY
        }

        return (string) $nip;
    }

    /**
     * Helper: Normalisasi jenis kelamin menjadi 'L' atau 'P'
     */
    private function normalJK(?string $v): string
    {
        if (empty($v)) {
            return 'L';
        }

        $v = trim(strtoupper($v));
        if (in_array($v, ['P', 'PEREMPUAN', 'WANITA', 'FEMALE', 'F'])) {
            return 'P';
        }

        return 'L';
    }

    /**
     * Helper: Normalisasi tanggal berbagai format ke 'YYYY-MM-DD'
     */
    private function normalTgl(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);

        // Jika sudah YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        // Hapus nama hari (Minggu, Senin, Tuesday, dsb.)
        $raw = preg_replace('/^(minggu|senin|selasa|rabu|kamis|jumat|sabtu|sunday|monday|tuesday|wednesday|thursday|friday|saturday),\s*/i', '', $raw);

        // Pemetaan bulan Indonesia & Inggris
        $months = [
            'januari' => '01', 'january' => '01', 'jan' => '01',
            'februari' => '02', 'pebruari' => '02', 'february' => '02', 'feb' => '02',
            'maret' => '03', 'march' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04',
            'mei' => '05', 'may' => '05',
            'juni' => '06', 'june' => '06', 'jun' => '06',
            'juli' => '07', 'july' => '07', 'jul' => '07',
            'agustus' => '08', 'august' => '08', 'agt' => '08', 'aug' => '08',
            'september' => '09', 'sept' => '09', 'sep' => '09',
            'oktober' => '10', 'october' => '10', 'okt' => '10', 'oct' => '10',
            'nopember' => '11', 'november' => '11', 'nop' => '11', 'nov' => '11',
            'desember' => '12', 'december' => '12', 'des' => '12', 'dec' => '12',
        ];

        // Format 1: "April 05, 1992" atau "September 05, 1993"
        if (preg_match('/^([a-z]+)\s+(\d{1,2}),?\s+(\d{4})$/i', $raw, $m)) {
            $mName = strtolower($m[1]);
            $month = $months[$mName] ?? '01';
            $day   = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year  = $m[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Format 2: "15 MEI 1959", "3  MARET 2000", "24 Pebruari 2003"
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/i', $raw, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mName = strtolower($m[2]);
            $month = $months[$mName] ?? '01';
            $year  = $m[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Format 3: Standar timestamp parser
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }
}
