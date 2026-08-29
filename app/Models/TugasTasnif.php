<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasTasnif extends Model
{
    protected $table = 'tugas_tasnif';

    protected $fillable = [
        'santri_id', 'level', 'pengampu_id', 'penguji_id',
        'tugas_tambahan_id', 'penugasan_id', 'status', 'nilai', 'lulus', 'catatan',
        'nilai_pemahaman_materi', 'nilai_kelancaran', 'nilai_fashohah', 'nilai_makhorijul_huruf',
    ];

    protected $casts = [
        'level' => 'integer', 'nilai' => 'float', 'lulus' => 'boolean',
        'nilai_pemahaman_materi' => 'float', 'nilai_kelancaran' => 'float',
        'nilai_fashohah' => 'float', 'nilai_makhorijul_huruf' => 'float',
    ];

    public function santri()   { return $this->belongsTo(Santri::class); }
    public function pengampu() { return $this->belongsTo(TenagaPendidik::class, 'pengampu_id'); }
    public function penguji()  { return $this->belongsTo(TenagaPendidik::class, 'penguji_id'); }
}
