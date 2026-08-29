<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    // Log tidak perlu updated_at
    const UPDATED_AT = null;

    protected $table = 'log_aktivitas';

    protected $fillable = [
        'user_id',
        'aksi',
        'model_type',
        'model_id',
        'data_lama',
        'data_baru',
        'ip_address',
        'user_agent',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'data_lama'  => 'array',
            'data_baru'  => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi polimorfik ke model manapun yang di-log.
     */
    public function subject()
    {
        return $this->morphTo('model');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Label aksi yang lebih ramah untuk UI.
     */
    public function getLabelAksiAttribute(): string
    {
        return match ($this->aksi) {
            'koreksi_absensi_harian'  => 'Koreksi Absensi Harian',
            'insert_absensi_manual'   => 'Insert Absensi Manual',
            'libur_mendadak_auto_update' => 'Auto Update — Libur Darurat',
            'batalkan_libur_darurat'  => 'Batalkan Libur Darurat',
            'override_gaji_manual'    => 'Override Gaji Manual',
            'recalculate_penggajian'  => 'Recalculate Penggajian',
            'finalisasi_penggajian'   => 'Finalisasi Penggajian',
            default => ucfirst(str_replace('_', ' ', $this->aksi)),
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByAksi($query, string $aksi)
    {
        return $query->where('aksi', $aksi);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeHariIni($query)
    {
        return $query->whereDate('created_at', today());
    }
}
