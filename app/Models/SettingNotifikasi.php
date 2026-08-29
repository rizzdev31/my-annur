<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingNotifikasi extends Model
{
    protected $table = 'setting_notifikasi';

    protected $fillable = [
        'event_kode', 'nama', 'kategori', 'deskripsi', 'wajib', 'aktif',
        'penerima', 'kanal', 'reminder', 'eskalasi', 'maks_per_hari',
    ];

    protected function casts(): array
    {
        return [
            'wajib'         => 'boolean',
            'aktif'         => 'boolean',
            'penerima'      => 'array',
            'kanal'         => 'array',
            'reminder'      => 'array',
            'eskalasi'      => 'array',
            'maks_per_hari' => 'integer',
        ];
    }

    /** Konfigurasi 1 event (cached per-request). */
    public static function untuk(string $kode): ?self
    {
        static $cache = [];
        if (array_key_exists($kode, $cache)) return $cache[$kode];
        return $cache[$kode] = static::where('event_kode', $kode)->first();
    }

    public function kanalAktif(string $kanal): bool
    {
        return (bool) ($this->kanal[$kanal] ?? false);
    }
}
