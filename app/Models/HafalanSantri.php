<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HafalanSantri extends Model
{
    protected $table = 'hafalan_santri';

    protected $fillable = [
        'santri_id', 'total_ayat', 'perlu_murojaah',
        'last_surah', 'last_surah_selesai', 'last_ayat_mulai', 'last_ayat_selesai', 'last_juz',
    ];

    protected function casts(): array
    {
        return [
            'total_ayat'     => 'integer',
            'perlu_murojaah' => 'boolean',
            'last_surah'         => 'integer',
            'last_surah_selesai' => 'integer',
            'last_ayat_mulai'    => 'integer',
            'last_ayat_selesai'  => 'integer',
            'last_juz'       => 'integer',
        ];
    }

    public function santri() { return $this->belongsTo(Santri::class); }
}
