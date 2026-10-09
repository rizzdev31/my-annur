<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Kegiatan yang punya buku tamu. Satu kegiatan = satu tautan publik (`token`).
 */
class KegiatanTamu extends Model
{
    protected $table = 'kegiatan_tamu';

    protected $fillable = [
        'nama', 'deskripsi', 'tanggal', 'tanggal_selesai', 'lokasi', 'penyelenggara',
        'token', 'is_dibuka', 'dibuka_sampai',
        'notulensi', 'notulensi_dikirim_pada', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'                => 'date',
            'tanggal_selesai'        => 'date',
            'is_dibuka'              => 'boolean',
            'dibuka_sampai'          => 'datetime',
            'notulensi_dikirim_pada' => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tamu()
    {
        return $this->hasMany(Tamu::class, 'kegiatan_tamu_id')->orderBy('nomor_urut');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Turunan ─────────────────────────────────────────────────────────────

    public static function tokenBaru(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function getTanggalAkhirAttribute(): \Carbon\Carbon
    {
        return $this->tanggal_selesai ?? $this->tanggal;
    }

    public function tautanPublik(): string
    {
        return url('/tamu/' . $this->token);
    }

    /** Rentang tanggal untuk tampilan. */
    public function rentangLabel(): string
    {
        if (!$this->tanggal_selesai || $this->tanggal_selesai->eq($this->tanggal)) {
            return $this->tanggal->locale('id')->isoFormat('dddd, D MMMM YYYY');
        }

        return $this->tanggal->locale('id')->isoFormat('D MMM')
            . ' – ' . $this->tanggal_akhir->locale('id')->isoFormat('D MMM YYYY');
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeTerbuka($q)
    {
        return $q->where('is_dibuka', true);
    }
}
