<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkstrakurikulerAbsensi extends Model
{
    protected $table = 'ekstrakurikuler_absensi';
    protected $fillable = ['pertemuan_id', 'santri_id', 'status', 'keterangan'];

    public function pertemuan() { return $this->belongsTo(EkstrakurikulerPertemuan::class, 'pertemuan_id'); }
    public function santri()    { return $this->belongsTo(Santri::class); }
}
