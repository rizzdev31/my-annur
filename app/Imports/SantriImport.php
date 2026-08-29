<?php

namespace App\Imports;

use App\Models\Santri;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import data santri dari Excel. Validasi per baris; baris gagal dilewati (dicatat).
 * Heading kolom (baris 1): nip, nama_lengkap, nama_panggilan, email, jenis_kelamin,
 * tempat_lahir, tanggal_lahir, no_whatsapp, tahsin_level, program_quran.
 */
class SantriImport implements ToCollection, WithHeadingRow
{
    public int $berhasil = 0;
    public int $gagal = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $baris = $i + 2; // +1 header, +1 index→nomor

            $data = [
                'nip'            => $this->str($row['nip'] ?? null),
                'nama_lengkap'   => $this->str($row['nama_lengkap'] ?? null),
                'nama_panggilan' => $this->str($row['nama_panggilan'] ?? null) ?: null,
                'email'          => $this->str($row['email'] ?? null) ?: null,
                'jenis_kelamin'  => strtoupper($this->str($row['jenis_kelamin'] ?? null)),
                'tempat_lahir'   => $this->str($row['tempat_lahir'] ?? null) ?: null,
                'tanggal_lahir'  => $this->tgl($row['tanggal_lahir'] ?? null),
                'no_whatsapp'    => $this->str($row['no_whatsapp'] ?? null) ?: null,
                'tahsin_level'   => ($row['tahsin_level'] ?? null) !== null && $row['tahsin_level'] !== '' ? (int) $row['tahsin_level'] : null,
                'program_quran'  => strtolower($this->str($row['program_quran'] ?? null)) ?: null,
            ];

            if ($data['nip'] === '' && $data['nama_lengkap'] === '') continue; // baris kosong

            $v = Validator::make($data, [
                'nip'           => 'required|string|max:30|unique:santri,nip',
                'nama_lengkap'  => 'required|string|max:150',
                'email'         => 'nullable|email|max:150',
                'jenis_kelamin' => 'required|in:L,P',
                'tahsin_level'  => 'nullable|integer|min:1|max:6',
                'program_quran' => 'nullable|in:tahsin,tahfidz',
            ]);

            if ($v->fails()) {
                $this->gagal++;
                $this->errors[] = "Baris {$baris}: " . $v->errors()->first();
                continue;
            }

            Santri::create($data + ['is_aktif' => true]);
            $this->berhasil++;
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
