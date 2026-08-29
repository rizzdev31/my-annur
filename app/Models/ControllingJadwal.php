<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControllingJadwal extends Model
{
    protected $table = 'controlling_jadwal';

    protected $fillable = [
        'periode_id', 'kegiatan_id', 'hari', 'jam_mulai', 'jam_selesai',
        'ambang_telat_menit', 'is_aktif',
    ];

    protected $casts = ['is_aktif' => 'boolean', 'ambang_telat_menit' => 'integer'];

    public function periode()  { return $this->belongsTo(ControllingPeriode::class, 'periode_id'); }
    public function kegiatan() { return $this->belongsTo(ControllingKegiatan::class, 'kegiatan_id'); }
    public function absensi()  { return $this->hasMany(ControllingAbsensi::class, 'jadwal_id'); }
}
