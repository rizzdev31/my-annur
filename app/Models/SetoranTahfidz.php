<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetoranTahfidz extends Model
{
    protected $table = 'setoran_tahfidz';

    protected $fillable = [
        'absensi_mengajar_id', 'santri_id', 'tenaga_pendidik_id', 'tanggal',
        'jenis', 'surah_mulai', 'ayat_mulai', 'surah_selesai', 'ayat_selesai',
        'jumlah_ayat', 'juz_mulai', 'juz_selesai', 'nilai', 'lulus', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'     => 'date',
            'jumlah_ayat' => 'integer',
            'juz_mulai'   => 'integer',
            'juz_selesai' => 'integer',
            'nilai'       => 'float',
            'lulus'       => 'boolean',
        ];
    }

    public function santri()        { return $this->belongsTo(Santri::class); }
    public function tenagaPendidik() { return $this->belongsTo(TenagaPendidik::class); }
    public function absensiMengajar() { return $this->belongsTo(AbsensiMengajar::class); }

    public function scopeJenis($q, string $j) { return $q->where('jenis', $j); }
}
