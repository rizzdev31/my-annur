<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman/pamflet pop-up aplikasi Flutter. Dikelola superadmin via web.
 */
class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected $fillable = [
        'judul', 'tipe', 'gambar', 'file', 'nama_file', 'isi',
        'link_url', 'aktif', 'urutan', 'sumber_tipe', 'sumber_id',
    ];

    protected $casts = ['aktif' => 'boolean', 'urutan' => 'integer'];

    /** Pamflet gambar atau berkas PDF (mis. notulensi rapat). */
    public const TIPE = ['gambar', 'pdf'];

    /** URL penuh gambar (dikonsumsi Flutter & Inertia). */
    public function getGambarUrlAttribute(): ?string
    {
        return $this->gambar ? asset('storage/' . $this->gambar) : null;
    }

    /** URL unduh berkas PDF (null bila pengumuman ini berupa gambar). */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file ? asset('storage/' . $this->file) : null;
    }

    /** Pengumuman layak tampil bila ada isi yang bisa dibuka. */
    public function bisaDitampilkan(): bool
    {
        return (bool) ($this->gambar ?: $this->file ?: $this->isi);
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /** Urutan tampil: urutan kecil dulu, lalu yang terbaru. */
    public function scopeTerurut($query)
    {
        return $query->orderBy('urutan')->orderByDesc('updated_at');
    }
}
