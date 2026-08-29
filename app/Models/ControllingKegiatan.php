<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControllingKegiatan extends Model
{
    protected $table = 'controlling_kegiatan';

    protected $fillable = ['nama', 'jenis', 'keterangan', 'is_aktif'];

    protected $casts = ['is_aktif' => 'boolean'];

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
}
