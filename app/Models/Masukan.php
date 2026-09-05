<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu utas saran / laporan bug dari pengguna sistem. */
class Masukan extends Model
{
    protected $table = 'masukan';

    protected $fillable = [
        'user_id', 'kategori', 'judul', 'modul', 'status', 'prioritas',
        'ditangani_oleh', 'selesai_pada', 'catatan_admin',
        'belum_dibaca_admin', 'belum_dibaca_user', 'pesan_terakhir_pada',
    ];

    protected function casts(): array
    {
        return [
            'selesai_pada'        => 'datetime',
            'pesan_terakhir_pada' => 'datetime',
            'belum_dibaca_admin'  => 'boolean',
            'belum_dibaca_user'   => 'boolean',
        ];
    }

    public const KATEGORI = ['bug', 'saran', 'pertanyaan', 'lainnya'];
    public const STATUS   = ['baru', 'diproses', 'selesai', 'ditolak'];

    public const LABEL_KATEGORI = [
        'bug'        => 'Laporan Bug',
        'saran'      => 'Saran / Usulan',
        'pertanyaan' => 'Pertanyaan',
        'lainnya'    => 'Lainnya',
    ];

    public const LABEL_STATUS = [
        'baru'     => 'Baru',
        'diproses' => 'Diproses',
        'selesai'  => 'Selesai',
        'ditolak'  => 'Ditolak',
    ];

    /** Utas yang sudah tidak menunggu tindakan admin. */
    public function scopeTuntas($q)
    {
        return $q->whereIn('status', ['selesai', 'ditolak']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ditanganiOleh()
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    public function pesan()
    {
        return $this->hasMany(MasukanPesan::class)->orderBy('id');
    }

    public function pesanTerakhir()
    {
        return $this->hasOne(MasukanPesan::class)->latestOfMany('id');
    }
}
