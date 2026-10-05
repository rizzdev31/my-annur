<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiSantri extends Model
{
    protected $table = 'absensi_santri';

    protected $fillable = [
        'absensi_mengajar_id',
        'santri_id',
        'status',     // hadir | telat | izin | sakit | alpha
        'catatan',
        'sumber',     // guru = diisi guru/piket · kegiatan = ditulis sistem saat libur pembelajaran
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function absensiMengajar()
    {
        return $this->belongsTo(AbsensiMengajar::class);
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /** Hanya kehadiran pembelajaran (bukan kehadiran kegiatan). */
    public function scopePembelajaran($query)
    {
        return $query->where('sumber', 'guru');
    }

    public function scopeKegiatan($query)
    {
        return $query->where('sumber', 'kegiatan');
    }
}
