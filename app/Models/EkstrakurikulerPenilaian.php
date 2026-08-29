<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkstrakurikulerPenilaian extends Model
{
    protected $table = 'ekstrakurikuler_penilaian';
    protected $fillable = ['ekstrakurikuler_id', 'santri_id', 'tahun_ajaran_id',
        'keaktifan', 'perkembangan', 'catatan', 'dinilai_oleh', 'tanggal'];
    protected $casts = ['tanggal' => 'date'];

    public function ekstrakurikuler() { return $this->belongsTo(Ekstrakurikuler::class); }
    public function santri()          { return $this->belongsTo(Santri::class); }
}
