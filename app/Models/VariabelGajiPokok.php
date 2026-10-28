<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nominal baku untuk mengisi komponen gaji pokok seorang guru.
 *
 * PENTING: katalog ini hanya MENGISI formulir. Yang dibayar adalah nominal
 * pada `komponen_gaji_pokok` milik guru, jadi mengubah variabel di sini tidak
 * menggeser gaji siapa pun secara surut.
 */
class VariabelGajiPokok extends Model
{
    protected $table = 'variabel_gaji_pokok';

    public const LABEL_JENIS = [
        'jabatan'              => 'Jabatan',
        'tunjangan_pendidikan' => 'Tunjangan Pendidikan',
    ];

    protected $fillable = [
        'jenis', 'label', 'nominal', 'urutan', 'is_aktif', 'catatan', 'ditetapkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'  => 'float',
            'urutan'   => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    public function ditetapkanOleh()
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }
}
