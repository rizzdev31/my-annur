<?php

namespace App\Imports;

use App\Models\MataPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import mata pelajaran dari Excel.
 * Heading: nama, kode, kategori, tingkat, tipe (reguler|tahfidz|tahsin).
 */
class MataPelajaranImport implements ToCollection, WithHeadingRow
{
    public int $berhasil = 0;
    public int $gagal = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $baris = $i + 2;

            $data = [
                'nama'     => trim((string) ($row['nama'] ?? '')),
                'kode'     => trim((string) ($row['kode'] ?? '')),
                'kategori' => trim((string) ($row['kategori'] ?? '')) ?: null,
                'tingkat'  => trim((string) ($row['tingkat'] ?? '')) ?: null,
                'tipe'     => strtolower(trim((string) ($row['tipe'] ?? ''))) ?: null,
            ];

            if ($data['nama'] === '' && $data['kode'] === '') continue;

            $v = Validator::make($data, [
                'nama'     => 'required|string|max:100',
                'kode'     => 'required|string|max:20|unique:mata_pelajaran,kode',
                'kategori' => 'nullable|string|max:50',
                'tingkat'  => 'nullable|string|max:20',
                'tipe'     => 'nullable|in:reguler,tahfidz,tahsin',
            ]);

            if ($v->fails()) {
                $this->gagal++;
                $this->errors[] = "Baris {$baris}: " . $v->errors()->first();
                continue;
            }

            MataPelajaran::create($data + ['is_aktif' => true]);
            $this->berhasil++;
        }
    }
}
