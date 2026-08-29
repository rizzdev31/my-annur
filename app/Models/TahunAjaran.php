<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'nama',
        'semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
            'is_aktif'        => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Ambil tahun ajaran yang sedang aktif.
     */
    public static function aktif(): ?self
    {
        return static::where('is_aktif', true)->first();
    }

    public function getLabelAttribute(): string
    {
        return "{$this->nama} — " . ucfirst($this->semester);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}