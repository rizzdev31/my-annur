<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Surah extends Model
{
    protected $table = 'surah';
    protected $primaryKey = 'nomor';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['nomor', 'nama', 'jumlah_ayat'];

    protected function casts(): array
    {
        return ['nomor' => 'integer', 'jumlah_ayat' => 'integer'];
    }
}
