<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HafalanJuz extends Model
{
    protected $table = 'hafalan_juz';

    protected $fillable = [
        'santri_id', 'juz', 'ayat_terkumpul', 'jumlah_ayat_juz', 'status',
    ];

    protected function casts(): array
    {
        return [
            'juz'             => 'integer',
            'ayat_terkumpul'  => 'integer',
            'jumlah_ayat_juz' => 'integer',
        ];
    }

    public function santri() { return $this->belongsTo(Santri::class); }
}
