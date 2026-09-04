<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengawas monitoring (pimpinan) — ditunjuk superadmin. Lihat PengawasService
 * untuk gerbang izin; JANGAN mengecek izin langsung dari model ini di controller.
 */
class Pengawas extends Model
{
    protected $table = 'pengawas';

    /** Modul yang bisa diawasi (kunci = nilai di kolom `modul`). */
    public const MODUL = [
        'absen_harian'   => 'Absen Masuk/Pulang',
        'absen_mengajar' => 'Absensi Pembelajaran',
        'perizinan'      => 'Perizinan Guru',
        'tugas_tambahan' => 'Tugas Tambahan',
        'kinerja'        => 'Kinerja',
    ];

    protected $fillable = [
        'tenaga_pendidik_id', 'modul', 'cakupan', 'boleh_setujui_izin',
        'is_aktif', 'ditunjuk_oleh', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'modul'              => 'array',
            'boleh_setujui_izin' => 'boolean',
            'is_aktif'           => 'boolean',
        ];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    /** Guru yang dipantau (dipakai saat cakupan = 'pilih'). */
    public function guruDiawasi()
    {
        return $this->belongsToMany(
            TenagaPendidik::class, 'pengawas_guru', 'pengawas_id', 'tenaga_pendidik_id'
        )->withTimestamps();
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }
}
