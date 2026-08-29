<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasTasmi extends Model
{
    protected $table = 'tugas_tasmi';

    protected $fillable = [
        'santri_id', 'juz', 'pengampu_id', 'penguji_id',
        'tugas_tambahan_id', 'penugasan_id', 'status', 'nilai', 'lulus', 'catatan',
        'nilai_kelancaran', 'nilai_makhorijul_huruf', 'nilai_tajwid', 'nilai_fashohah',
    ];

    protected function casts(): array
    {
        return [
            'juz' => 'integer', 'nilai' => 'float', 'lulus' => 'boolean',
            'nilai_kelancaran' => 'float', 'nilai_makhorijul_huruf' => 'float',
            'nilai_tajwid' => 'float', 'nilai_fashohah' => 'float',
        ];
    }

    public function santri()   { return $this->belongsTo(Santri::class); }
    public function pengampu() { return $this->belongsTo(TenagaPendidik::class, 'pengampu_id'); }
    public function penguji()  { return $this->belongsTo(TenagaPendidik::class, 'penguji_id'); }
    public function penugasan() { return $this->belongsTo(PenugasanTambahan::class, 'penugasan_id'); }
}
