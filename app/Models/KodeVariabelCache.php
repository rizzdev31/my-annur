<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeVariabelCache extends Model
{
    protected $table = 'kode_variabel_cache';

    protected $fillable = ['jenis', 'kode', 'kategori', 'poin', 'label'];

    protected $casts = ['poin' => 'integer'];

    public function scopeJenis($q, string $jenis)
    {
        return $q->where('jenis', $jenis);
    }
}
