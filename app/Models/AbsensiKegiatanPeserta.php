<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AbsensiKegiatanPeserta — Daftar kehadiran peserta kegiatan
 * Guru yang hadir otomatis mendapat vakasi sesuai setting
 */
class AbsensiKegiatanPeserta extends Model
{
    protected $table = 'absensi_kegiatan_peserta';

    protected $fillable = [
        'absensi_kegiatan_id',
        'tenaga_pendidik_id',
        'status_kehadiran',
        'jam_hadir',
        'keterangan',
        'vakasi_diberikan',
        'nominal_vakasi',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'vakasi_diberikan' => 'boolean',
            'nominal_vakasi'   => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function kegiatan()
    {
        return $this->belongsTo(AbsensiKegiatan::class, 'absensi_kegiatan_id');
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function dicatatOleh()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function isHadir(): bool
    {
        return in_array($this->status_kehadiran, ['hadir', 'terlambat']);
    }

    public function statusLabel(): string
    {
        return match ($this->status_kehadiran) {
            'hadir'     => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin'      => 'Izin',
            'alfa'      => 'Alfa',
            default     => ucfirst($this->status_kehadiran),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status_kehadiran) {
            'hadir'     => 'emerald',
            'terlambat' => 'amber',
            'izin'      => 'blue',
            'alfa'      => 'red',
            default     => 'gray',
        };
    }
}