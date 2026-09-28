<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiKegiatanPenting extends Model
{
    public const LABEL_STATUS = [
        'hadir'       => 'Hadir',
        'tidak_hadir' => 'Tidak Hadir',
        'izin'        => 'Izin (netral)',
    ];

    /** Label siap pakai — klien lama yang tak mengenal 'izin' tetap aman. */
    public function getStatusLabelAttribute(): ?string
    {
        return $this->status ? (self::LABEL_STATUS[$this->status] ?? $this->status) : null;
    }

    protected $table = 'absensi_kegiatan_penting';

    protected $fillable = [
        'kegiatan_penting_id', 'tenaga_pendidik_id', 'tanggal',
        'status', 'sumber', 'jam_hadir', 'dicatat_oleh', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function kegiatan()
    {
        return $this->belongsTo(KegiatanPenting::class, 'kegiatan_penting_id');
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function dicatatOleh()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
