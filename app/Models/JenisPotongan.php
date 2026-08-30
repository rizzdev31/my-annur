<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPotongan extends Model
{
    protected $table = 'jenis_potongan';

    protected $fillable = ['nama', 'kategori', 'tampil_di_slip', 'urutan', 'is_aktif'];

    protected function casts(): array
    {
        return [
            'tampil_di_slip' => 'boolean',
            'is_aktif'       => 'boolean',
        ];
    }

    public function potonganGuru()
    {
        return $this->hasMany(PotonganGuru::class);
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    public function scopeUrut($q)
    {
        return $q->orderBy('urutan')->orderBy('nama');
    }

    public function getKategoriLabelAttribute(): string
    {
        return [
            'wajib'    => 'Potongan Wajib',
            'simpanan' => 'Simpanan',
            'pinjaman' => 'Simpan Pinjam',
            'lainnya'  => 'Lainnya',
        ][$this->kategori] ?? $this->kategori;
    }
}
