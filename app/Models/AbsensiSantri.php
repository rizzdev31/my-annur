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
        'dikoreksi_oleh',
        'dikoreksi_pada',
    ];

    protected function casts(): array
    {
        return ['dikoreksi_pada' => 'datetime'];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function absensiMengajar()
    {
        return $this->belongsTo(AbsensiMengajar::class);
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class);
    }

    public function dikoreksiOleh()
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
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
