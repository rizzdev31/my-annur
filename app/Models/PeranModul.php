<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot peran ↔ modul (kode modul dari config/modul.php).
 */
class PeranModul extends Model
{
    protected $table = 'peran_modul';

    protected $fillable = ['peran_id', 'modul'];

    public function peran()
    {
        return $this->belongsTo(Peran::class, 'peran_id');
    }
}
