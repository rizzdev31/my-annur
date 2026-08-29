<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman/pamflet pop-up aplikasi Flutter. Dikelola superadmin via web.
 */
class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected $fillable = ['judul', 'gambar', 'link_url', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    /** URL penuh gambar (dikonsumsi Flutter & Inertia). */
    public function getGambarUrlAttribute(): ?string
    {
        return $this->gambar ? asset('storage/' . $this->gambar) : null;
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
