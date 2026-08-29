<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingLokasiAbsensi extends Model
{
    protected $table = 'setting_lokasi_absensi';

    protected $fillable = [
        'nama',
        'tipe',               // koordinat | wifi
        'latitude',
        'longitude',
        'radius_meter',
        'wifi_ssid',
        'wifi_bssid',
        'lingkup',            // global | per_user
        'is_aktif',
        'izinkan_dinas_luar',
        'izinkan_izin_aktif',
        'izinkan_checkout_luar_lokasi',
        'keterangan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'latitude'            => 'float',
            'longitude'           => 'float',
            'radius_meter'        => 'integer',
            'is_aktif'            => 'boolean',
            'izinkan_dinas_luar'  => 'boolean',
            'izinkan_izin_aktif'  => 'boolean',
            'izinkan_checkout_luar_lokasi' => 'boolean',
        ];
    }

    // ─── Label ────────────────────────────────────────────────────────────────

    public function getTipeLabelAttribute(): string
    {
        return $this->tipe === 'koordinat' ? '📍 Koordinat GPS' : '📶 WiFi';
    }

    public function getLingkupLabelAttribute(): string
    {
        return $this->lingkup === 'global' ? 'Semua Guru' : 'Per User';
    }

    // ─── Relasi ───────────────────────────────────────────────────────────────

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Guru yang di-assign ke lokasi ini (hanya jika lingkup = per_user).
     */
    public function tenagaPendidik()
    {
        return $this->belongsToMany(
            TenagaPendidik::class,
            'tenaga_pendidik_lokasi',
            'setting_lokasi_id',
            'tenaga_pendidik_id'
        )->withPivot([
            'konteks', 'berlaku_mulai', 'berlaku_selesai', 'keterangan', 'ditambahkan_oleh',
        ])->withTimestamps();
    }

    // ─── Scope ────────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeGlobal($query)
    {
        return $query->where('lingkup', 'global');
    }

    public function scopePerUser($query)
    {
        return $query->where('lingkup', 'per_user');
    }

    public function scopeKoordinat($query)
    {
        return $query->where('tipe', 'koordinat');
    }

    public function scopeWifi($query)
    {
        return $query->where('tipe', 'wifi');
    }
}