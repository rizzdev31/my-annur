<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasTasnif extends Model
{
    protected $table = 'tugas_tasnif';

    protected $fillable = [
        'santri_id', 'level', 'pengampu_id', 'penguji_id',
        'tugas_tambahan_id', 'penugasan_id', 'status', 'nilai', 'lulus', 'catatan',
        'nilai_pemahaman_materi', 'nilai_kelancaran', 'nilai_fashohah', 'nilai_makhorijul_huruf',
        // Kolom pembatalan (14 Okt 2026). WAJIB didaftarkan: tanpa ini
        // Eloquent melewati update()-nya tanpa galat sama sekali, sehingga
        // statusnya berubah menjadi 'dibatalkan' tetapi alasan & pelakunya
        // hilang — jejak pembatalan jadi tidak bisa ditelusuri.
        'dibatalkan_oleh', 'dibatalkan_pada', 'alasan_batal',
        // Masukan pimpinan (14 Okt 2026). Didaftarkan sejak awal: dua kali
        // dalam pekerjaan ini kolom baru lupa didaftarkan dan Eloquent
        // melewatinya TANPA galat.
        'masukan_pimpinan', 'masukan_oleh', 'masukan_pada',
    ];

    protected $casts = [
        'level' => 'integer', 'nilai' => 'float', 'lulus' => 'boolean',
        'nilai_pemahaman_materi' => 'float', 'nilai_kelancaran' => 'float',
        'nilai_fashohah' => 'float', 'nilai_makhorijul_huruf' => 'float',
    ];

    public function santri()   { return $this->belongsTo(Santri::class); }
    public function pengampu() { return $this->belongsTo(TenagaPendidik::class, 'pengampu_id'); }
    public function penguji()  { return $this->belongsTo(TenagaPendidik::class, 'penguji_id'); }

    public function masukanOleh() { return $this->belongsTo(User::class, 'masukan_oleh'); }

    public function dibatalkanOleh() { return $this->belongsTo(User::class, 'dibatalkan_oleh'); }
}
