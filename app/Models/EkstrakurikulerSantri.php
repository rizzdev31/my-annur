<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkstrakurikulerSantri extends Model
{
    protected $table = 'ekstrakurikuler_santri';
    protected $fillable = ['ekstrakurikuler_id', 'santri_id', 'tanggal_masuk', 'is_aktif'];
    protected $casts = ['is_aktif' => 'boolean', 'tanggal_masuk' => 'date'];

    public function ekstrakurikuler() { return $this->belongsTo(Ekstrakurikuler::class); }
    public function santri()          { return $this->belongsTo(Santri::class); }
}
