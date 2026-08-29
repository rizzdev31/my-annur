<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingJenisPengajuan extends Model
{
    protected $table = 'setting_jenis_pengajuan';

    protected $fillable = [
        'nama', 'kode', 'kategori', 'deskripsi',
        'max_hari_per_pengajuan', 'kuota_per_tahun',
        'min_hari_pengajuan_sebelumnya',
        'butuh_dokumen', 'keterangan_dokumen',
        'auto_approve',
        'pengaruh_gaji', 'persen_potongan',
        'update_status_kepegawaian',
        'status_kepegawaian_tujuan',
        'min_hari_untuk_update_status',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'butuh_dokumen'                => 'boolean',
            'auto_approve'                 => 'boolean',
            'update_status_kepegawaian'    => 'boolean',
            'is_aktif'                     => 'boolean',
            'persen_potongan'              => 'float',
        ];
    }

    public function pengajuan()
    {
        return $this->hasMany(PengajuanIzin::class);
    }

    public function kuotaTahunan()
    {
        return $this->hasMany(KuotaIzinTahunan::class);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public static function badgeKategori(string $kategori): array
    {
        return match ($kategori) {
            'sakit' => ['label' => 'Sakit',  'class' => 'bg-blue-50 text-blue-700'],
            'izin'  => ['label' => 'Izin',   'class' => 'bg-amber-50 text-amber-700'],
            'cuti'  => ['label' => 'Cuti',   'class' => 'bg-violet-50 text-violet-700'],
            'dinas' => ['label' => 'Dinas',  'class' => 'bg-indigo-50 text-indigo-700'],
            default => ['label' => ucfirst($kategori), 'class' => 'bg-gray-100 text-gray-600'],
        };
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}