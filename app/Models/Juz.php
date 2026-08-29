<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Juz extends Model
{
    protected $table = 'juz';
    protected $primaryKey = 'nomor';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['nomor', 'mulai_surah', 'mulai_ayat'];

    protected function casts(): array
    {
        return ['nomor' => 'integer', 'mulai_surah' => 'integer', 'mulai_ayat' => 'integer'];
    }
}
