<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControllingAbsensi extends Model
{
    protected $table = 'controlling_absensi';

    protected $fillable = [
        'periode_id', 'jadwal_id', 'kegiatan_id', 'santri_id', 'tanggal',
        'status', 'jam_scan', 'petugas', 'keterangan', 'dikirim_outbox',
    ];

    protected $casts = ['tanggal' => 'date', 'dikirim_outbox' => 'boolean'];

    public function santri()   { return $this->belongsTo(Santri::class); }
    public function jadwal()   { return $this->belongsTo(ControllingJadwal::class, 'jadwal_id'); }
    public function kegiatan() { return $this->belongsTo(ControllingKegiatan::class, 'kegiatan_id'); }
}
