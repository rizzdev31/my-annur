<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HafalanJuz extends Model
{
    protected $table = 'hafalan_juz';

    protected $fillable = [
        'santri_id', 'juz', 'ayat_terkumpul', 'jumlah_ayat_juz', 'status',
        // Kolom 14 Okt 2026. Tanpa didaftarkan di sini, update()-nya dilewati
        // Eloquent TANPA galat — override status admin tersimpan statusnya
        // tetapi kuncinya tidak, sehingga hitungan otomatis menimpanya lagi.
        'ayat_seed', 'status_dikunci', 'dikoreksi_oleh', 'dikoreksi_pada', 'alasan_koreksi',
    ];

    protected function casts(): array
    {
        return [
            'juz'             => 'integer',
            'ayat_terkumpul'  => 'integer',
            'jumlah_ayat_juz' => 'integer',
            'ayat_seed'       => 'integer',
            'status_dikunci'  => 'boolean',
            'dikoreksi_pada'  => 'datetime',
        ];
    }

    public function santri() { return $this->belongsTo(Santri::class); }

    public function dikoreksiOleh() { return $this->belongsTo(User::class, 'dikoreksi_oleh'); }
}
