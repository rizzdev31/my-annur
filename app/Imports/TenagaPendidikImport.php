<?php

namespace App\Imports;

use App\Models\User;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\JabatanGuru;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import tenaga pendidik (membuat User + TenagaPendidik + jabatan utama).
 * Heading: name, email, username, password, jabatan, nip, jenis_kelamin,
 * tanggal_masuk, jenis_guru, nik, tempat_lahir, tanggal_lahir,
 * pendidikan_terakhir, jurusan, no_hp, alamat, no_rekening, nama_bank, nama_rekening.
 * password kosong → default = NIP. jabatan diisi NAMA jabatan (dicocokkan).
 */
class TenagaPendidikImport implements ToCollection, WithHeadingRow
{
    public int $berhasil = 0;
    public int $gagal = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $baris = $i + 2;

            $name  = $this->str($row['name'] ?? null);
            $nip   = $this->str($row['nip'] ?? null);
            if ($name === '' && $nip === '') continue; // baris kosong

            $namaJabatan = $this->str($row['jabatan'] ?? null);
            $password    = $this->str($row['password'] ?? null) ?: $nip; // default = NIP

            $data = [
                'name'          => $name,
                'email'         => $this->str($row['email'] ?? null),
                'username'      => $this->str($row['username'] ?? null),
                'nip'           => $nip,
                'jenis_kelamin' => strtoupper($this->str($row['jenis_kelamin'] ?? null)),
                'tanggal_masuk' => $this->tgl($row['tanggal_masuk'] ?? null),
                'jenis_guru'    => strtolower($this->str($row['jenis_guru'] ?? null)),
            ];

            $v = Validator::make($data, [
                'name'          => 'required|string|max:100',
                'email'         => 'required|email|unique:users,email',
                'username'      => 'required|string|max:50|unique:users,username|alpha_dash',
                'nip'           => 'required|string|max:30|unique:tenaga_pendidik,nip',
                'jenis_kelamin' => 'required|in:L,P',
                'tanggal_masuk' => 'required|date',
                'jenis_guru'    => 'required|in:mukim,non_mukim',
            ]);

            if ($v->fails()) {
                $this->gagal++;
                $this->errors[] = "Baris {$baris}: " . $v->errors()->first();
                continue;
            }

            $jabatan = Jabatan::whereRaw('LOWER(nama_jabatan) = ?', [strtolower($namaJabatan)])->first();
            if (!$jabatan) {
                $this->gagal++;
                $this->errors[] = "Baris {$baris}: jabatan \"{$namaJabatan}\" tidak ditemukan.";
                continue;
            }

            try {
                DB::transaction(function () use ($row, $data, $password, $jabatan) {
                    $user = User::create([
                        'name'     => $data['name'],
                        'email'    => $data['email'],
                        'username' => $data['username'],
                        'password' => Hash::make($password),
                        'role'     => 'tenaga_pendidik',
                        'status'   => 'aktif',
                    ]);

                    $guru = TenagaPendidik::create([
                        'user_id'             => $user->id,
                        'jabatan_id'          => $jabatan->id,
                        'nip'                 => $data['nip'],
                        'nik'                 => $this->str($row['nik'] ?? null) ?: null,
                        'jenis_kelamin'       => $data['jenis_kelamin'],
                        'tanggal_masuk'       => $data['tanggal_masuk'],
                        'jenis_guru'          => $data['jenis_guru'],
                        'tempat_lahir'        => $this->str($row['tempat_lahir'] ?? null) ?: null,
                        'tanggal_lahir'       => $this->tgl($row['tanggal_lahir'] ?? null),
                        'pendidikan_terakhir' => $this->str($row['pendidikan_terakhir'] ?? null) ?: null,
                        'jurusan'             => $this->str($row['jurusan'] ?? null) ?: null,
                        'no_hp'               => $this->str($row['no_hp'] ?? null) ?: null,
                        'alamat'              => $this->str($row['alamat'] ?? null) ?: null,
                        'no_rekening'         => $this->str($row['no_rekening'] ?? null) ?: null,
                        'nama_bank'           => $this->str($row['nama_bank'] ?? null) ?: null,
                        'nama_rekening'       => $this->str($row['nama_rekening'] ?? null) ?: null,
                        'is_aktif'            => true,
                        'status_kepegawaian'  => 'aktif',
                    ]);

                    JabatanGuru::create([
                        'tenaga_pendidik_id' => $guru->id,
                        'jabatan_id'         => $jabatan->id,
                        'adalah_utama'       => true,
                        'berlaku_mulai'      => $data['tanggal_masuk'],
                        'berlaku_selesai'    => null,
                        'keterangan'         => 'Jabatan awal (import Excel)',
                        'ditetapkan_oleh'    => auth()->id(),
                    ]);
                });
                $this->berhasil++;
            } catch (\Throwable $e) {
                $this->gagal++;
                $this->errors[] = "Baris {$baris}: gagal disimpan ({$e->getMessage()})";
            }
        }
    }

    private function str($v): string { return trim((string) ($v ?? '')); }

    private function tgl($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) {
            try { return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d'); } catch (\Throwable) { return null; }
        }
        return trim((string) $v);
    }
}
