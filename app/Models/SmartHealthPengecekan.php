<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log pemantauan kesehatan: keputusan Sembuh / Pengecekan (Hari 1–3) / Darurat.
 */
class SmartHealthPengecekan extends Model
{
    protected $table = 'smart_health_pengecekan';

    protected $fillable = [
        'laporan_id', 'hari_ke', 'keputusan', 'catatan', 'oleh_tenaga_pendidik_id', 'tanggal',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'hari_ke' => 'integer'];
    }

    public function laporan() { return $this->belongsTo(SmartHealthLaporan::class, 'laporan_id'); }
    public function oleh()    { return $this->belongsTo(TenagaPendidik::class, 'oleh_tenaga_pendidik_id'); }
}
