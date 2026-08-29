<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketKategori extends Model
{
    protected $table = 'piket_kategori';

    protected $fillable = ['nama', 'jenis', 'dimensi', 'poin', 'is_aktif'];

    protected $casts = [
        'poin'     => 'float',
        'is_aktif' => 'boolean',
    ];

    /** Poin bertanda: apresiasi (+), catatan (−). */
    public function poinBertandaAttribute(): float
    {
        return $this->jenis === 'catatan' ? -abs($this->poin) : abs($this->poin);
    }

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
}
