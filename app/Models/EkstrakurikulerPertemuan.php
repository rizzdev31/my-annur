<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkstrakurikulerPertemuan extends Model
{
    protected $table = 'ekstrakurikuler_pertemuan';
    protected $fillable = ['ekstrakurikuler_id', 'tanggal', 'jam_mulai_aktual', 'materi',
        'status', 'pembina_id', 'nominal_vakasi', 'vakasi_diberikan'];
    protected $casts = ['tanggal' => 'date', 'nominal_vakasi' => 'float', 'vakasi_diberikan' => 'boolean'];

    public function ekstrakurikuler() { return $this->belongsTo(Ekstrakurikuler::class); }
    public function pembina()         { return $this->belongsTo(TenagaPendidik::class, 'pembina_id'); }
    public function absensi()         { return $this->hasMany(EkstrakurikulerAbsensi::class, 'pertemuan_id'); }
}
