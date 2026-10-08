<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paket ujian sekolah (mis. "UAS Semester 1") — wadah sesi-sesi ujian.
 *
 * Pematian pembelajaran reguler TIDAK diurus di sini: paket ini menitipkannya
 * ke LiburPembelajaran (lihat kolom libur_pembelajaran_id), supaya hanya ada
 * satu mesin yang boleh menulis status 'libur' pada absensi mengajar.
 */
class Ujian extends Model
{
    protected $table = 'ujian';

    protected $fillable = [
        'nama', 'tanggal_mulai', 'tanggal_selesai', 'keterangan',
        'libur_pembelajaran_id', 'is_aktif',
        'dibatalkan_pada', 'alasan_pembatalan', 'dibatalkan_oleh', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'    => 'date',
            'tanggal_selesai'  => 'date',
            'is_aktif'         => 'boolean',
            'dibatalkan_pada'  => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function sesi()
    {
        return $this->hasMany(UjianSesi::class)->orderBy('tanggal')->orderBy('jam_mulai');
    }

    public function liburPembelajaran()
    {
        return $this->belongsTo(LiburPembelajaran::class, 'libur_pembelajaran_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function dibatalkanOleh()
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    // ─── Turunan ─────────────────────────────────────────────────────────────

    public function getTanggalAkhirAttribute(): \Carbon\Carbon
    {
        return $this->tanggal_selesai ?? $this->tanggal_mulai;
    }

    public function getDurasiHariAttribute(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_akhir) + 1;
    }

    public function getIsDibatalkanAttribute(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    /** Daftar tanggal yang dicakup paket ini. */
    public function tanggalCakupan(): array
    {
        $out = [];
        for ($d = $this->tanggal_mulai->copy(); $d->lte($this->tanggal_akhir); $d->addDay()) {
            $out[] = $d->toDateString();
        }
        return $out;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeBerlaku($q)
    {
        return $q->where('is_aktif', true)->whereNull('dibatalkan_pada');
    }
}
