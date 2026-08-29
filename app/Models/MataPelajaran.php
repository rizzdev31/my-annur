<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'nama',
        'kode',
        'kategori',
        'tingkat',
        'tipe',
        'is_aktif',
    ];

    public function scopeTahfidz($q) { return $q->whereIn('tipe', ['tahfidz', 'tahsin']); }

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeByKategori($query, string $kategori)
    {
        return $query->where('kategori', $kategori);
    }
}