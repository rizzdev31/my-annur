<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak override pencapaian santri (tahfidz & tahsin).
 *
 * Terpisah dari `koreksi_absensi` karena subjeknya santri + juz/level, bukan
 * absensi guru. Setiap pembenaran manual superadmin tercatat di sini beserta
 * alasannya — tanpa itu, data pencapaian bisa diubah tanpa bisa ditelusuri.
 */
class KoreksiPencapaian extends Model
{
    protected $table = 'koreksi_pencapaian';

    protected $fillable = [
        'santri_id', 'tipe', 'juz', 'level', 'field',
        'nilai_lama', 'nilai_baru', 'alasan', 'dikoreksi_oleh',
    ];

    protected function casts(): array
    {
        return ['juz' => 'integer', 'level' => 'integer'];
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function dikoreksiOleh()
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }
}
