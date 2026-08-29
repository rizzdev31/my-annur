<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketJadwal extends Model
{
    protected $table = 'piket_jadwal';

    protected $fillable = [
        'tanggal', 'tenaga_pendidik_id', 'ditunjuk_oleh', 'catatan_harian',
        'vakasi_dibayar', 'dibayar_periode_id',
    ];

    protected $casts = [
        'tanggal'            => 'date',
        'vakasi_dibayar'     => 'boolean',
        'dibayar_periode_id' => 'integer',
    ];

    public function tenagaPendidik() { return $this->belongsTo(TenagaPendidik::class); }
    public function penilaian()      { return $this->hasMany(PiketPenilaian::class, 'piket_jadwal_id'); }
}
