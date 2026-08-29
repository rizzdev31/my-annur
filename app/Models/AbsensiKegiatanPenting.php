<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiKegiatanPenting extends Model
{
    protected $table = 'absensi_kegiatan_penting';

    protected $fillable = [
        'kegiatan_penting_id', 'tenaga_pendidik_id', 'tanggal',
        'status', 'jam_hadir', 'dicatat_oleh', 'keterangan',
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
