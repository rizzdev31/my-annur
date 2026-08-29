<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiSantri extends Model
{
    protected $table = 'absensi_santri';

    protected $fillable = [
        'absensi_mengajar_id',
        'santri_id',
        'status',     // hadir | telat | alpha
        'catatan',
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
}
