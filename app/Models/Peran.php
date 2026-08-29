<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Peran RBAC web admin — bundel modul yang diberi nama (dibuat superadmin).
 */
class Peran extends Model
{
    protected $table = 'peran';

    protected $fillable = ['kode', 'nama', 'deskripsi', 'is_bawaan', 'is_aktif'];

    protected function casts(): array
    {
        return ['is_bawaan' => 'boolean', 'is_aktif' => 'boolean'];
    }

    public function modul()
    {
        return $this->hasMany(PeranModul::class, 'peran_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_peran', 'peran_id', 'user_id');
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    /** Daftar kode modul milik peran ini (difilter ke modul yang masih terdaftar di config). */
    public function daftarModul(): array
    {
        $valid = array_keys(config('modul.daftar', []));
        return $this->modul->pluck('modul')->filter(fn($m) => in_array($m, $valid, true))->values()->all();
    }
}
