<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Materi tambahan tahsin (pelengkap jurnal) — di luar materi wajib, tak
 * memengaruhi naik level. Boleh banyak entri per santri per hari.
 */
class TahsinMateriTambahan extends Model
{
    protected $table = 'tahsin_materi_tambahan';

    protected $fillable = [
        'santri_id', 'tenaga_pendidik_id', 'absensi_mengajar_id',
        'nama_materi', 'nilai', 'catatan', 'tanggal',
    ];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'tanggal' => 'date'];
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }
}
