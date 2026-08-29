<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master inventaris sekolah: benda, ruang, bangunan, kendaraan, dll.
 * jumlah_total = kapasitas paralel (ruang/bangunan biasanya 1).
 */
class Inventaris extends Model
{
    protected $table = 'inventaris';

    protected $fillable = [
        'kode', 'nama', 'kategori', 'lokasi',
        'jumlah_total', 'satuan', 'kondisi',
        'perlu_persetujuan', 'is_aktif', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_total'      => 'integer',
            'perlu_persetujuan' => 'boolean',
            'is_aktif'          => 'boolean',
        ];
    }

    public function peminjaman()
    {
        return $this->hasMany(PeminjamanInventaris::class);
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }
}
