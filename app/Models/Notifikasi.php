<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi in-app per user (dipakai al. gerbang Pengajuan Izin → superadmin/guru).
 * Tabel `notifikasi` sudah ada; model ini sebelumnya hilang sehingga relasi
 * User::notifikasi() & badge notifikasi diam-diam gagal (ditelan try/catch).
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $fillable = [
        'user_id', 'judul', 'pesan', 'tipe', 'event_kode', 'prioritas', 'data', 'sudah_dibaca', 'dibaca_pada',
    ];

    protected function casts(): array
    {
        return [
            'data'         => 'array',
            'sudah_dibaca' => 'boolean',
            'dibaca_pada'  => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBelumDibaca($q)
    {
        return $q->where('sudah_dibaca', false);
    }
}
