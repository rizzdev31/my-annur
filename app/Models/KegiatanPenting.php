<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KegiatanPenting extends Model
{
    protected $table = 'kegiatan_penting';

    protected $fillable = [
        'nama', 'sasaran', 'jam', 'poin_hadir', 'poin_absen', 'is_aktif', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'   => 'boolean',
            'poin_hadir' => 'integer',
            'poin_absen' => 'integer',
        ];
    }

    public function absensi()
    {
        return $this->hasMany(AbsensiKegiatanPenting::class);
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    /** jenis_guru yang termasuk sasaran kegiatan ini. */
    public function jenisGuruSasaran(): array
    {
        return $this->sasaran === 'semua' ? ['mukim', 'non_mukim'] : [$this->sasaran];
    }
}
