<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahsinPenilaian extends Model
{
    protected $table = 'tahsin_penilaian';

    protected $fillable = [
        'absensi_mengajar_id', 'santri_id', 'tenaga_pendidik_id',
        'level', 'materi_id', 'nilai', 'lulus', 'catatan', 'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'level'   => 'integer',
            'nilai'   => 'float',
            'lulus'   => 'boolean',
            'tanggal' => 'date',
        ];
    }

    public function santri()        { return $this->belongsTo(Santri::class); }
    public function materi()        { return $this->belongsTo(SettingTahsinMateri::class, 'materi_id'); }
    public function tenagaPendidik() { return $this->belongsTo(TenagaPendidik::class); }
}
