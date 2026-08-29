<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahsinPenilaianRiwayat extends Model
{
    protected $table = 'tahsin_penilaian_riwayat';

    protected $fillable = [
        'santri_id', 'materi_id', 'level', 'nilai', 'lulus',
        'catatan', 'tenaga_pendidik_id', 'tanggal',
    ];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'lulus' => 'boolean', 'tanggal' => 'date'];
    }

    public function santri()         { return $this->belongsTo(Santri::class); }
    public function materi()         { return $this->belongsTo(SettingTahsinMateri::class, 'materi_id'); }
    public function tenagaPendidik() { return $this->belongsTo(TenagaPendidik::class); }
}
