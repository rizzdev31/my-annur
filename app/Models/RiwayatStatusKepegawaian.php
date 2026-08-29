<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatStatusKepegawaian extends Model
{
    protected $table = 'riwayat_status_kepegawaian';

    protected $fillable = [
        'tenaga_pendidik_id',
        'status_lama',
        'status_baru',
        'tanggal_efektif',
        'tanggal_kembali',
        'alasan',
        'dokumen_pendukung',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_efektif' => 'date',
            'tanggal_kembali' => 'date',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function dicatatOleh()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            'aktif'               => 'Aktif',
            'cuti'                => 'Cuti',
            'cuti_sakit'          => 'Cuti Sakit',
            'nonaktif_sementara'  => 'Nonaktif Sementara',
            'resign'              => 'Resign',
            'pensiun'             => 'Pensiun',
            'meninggal'           => 'Meninggal Dunia',
            default               => ucfirst($status),
        };
    }

    public static function badgeStatus(string $status): array
    {
        return match ($status) {
            'aktif'               => ['label' => 'Aktif',              'class' => 'bg-emerald-50 text-emerald-700'],
            'cuti'                => ['label' => 'Cuti',               'class' => 'bg-amber-50 text-amber-700'],
            'cuti_sakit'          => ['label' => 'Cuti Sakit',         'class' => 'bg-blue-50 text-blue-700'],
            'nonaktif_sementara'  => ['label' => 'Nonaktif Sementara', 'class' => 'bg-orange-50 text-orange-700'],
            'resign'              => ['label' => 'Resign',             'class' => 'bg-red-50 text-red-700'],
            'pensiun'             => ['label' => 'Pensiun',            'class' => 'bg-gray-100 text-gray-600'],
            'meninggal'           => ['label' => 'Meninggal',          'class' => 'bg-gray-200 text-gray-700'],
            default               => ['label' => ucfirst($status),     'class' => 'bg-gray-100 text-gray-600'],
        };
    }

    /**
     * Apakah status ini bersifat sementara (bisa kembali aktif).
     */
    public static function isSementara(string $status): bool
    {
        return in_array($status, ['cuti', 'cuti_sakit', 'nonaktif_sementara']);
    }

    /**
     * Apakah status ini permanen (tidak bisa aktif kembali).
     */
    public static function isPermanent(string $status): bool
    {
        return in_array($status, ['resign', 'pensiun', 'meninggal']);
    }
}