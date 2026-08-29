<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penggajian extends Model
{
    protected $table = 'penggajian';

    protected $fillable = [
        'periode_penggajian_id',
        'tenaga_pendidik_id',
        'jabatan_id',
        'gaji_pokok',
        'vakasi_absen_harian',
        'vakasi_mengajar',
        'vakasi_tugas_jabatan',
        'vakasi_tugas_tambahan',
        'vakasi_peserta_kegiatan',  // FIX Bug 1: komponen baru
        'vakasi_lembur',            // komponen lembur
        'vakasi_piket',             // komponen guru piket
        'tunjangan_lainnya',
        'potongan_keterlambatan',
        'potongan_alfa',
        'potongan_tetap',
        'potongan_lainnya',
        'potongan_liburan',      // penyesuaian liburan manual
        'keterangan_liburan',
        'total_pendapatan',
        'total_potongan',
        'gaji_bersih',
        'total_hari_kerja',
        'total_hadir',
        'total_izin',
        'total_sakit',
        'total_alfa',
        'total_terlambat',
        'total_jp_mengajar',
        'status',
        'dibayar_pada',
        'catatan',
        'ada_koreksi_manual',
        'difinalisasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'gaji_pokok'              => 'float',
            'vakasi_absen_harian'     => 'float',
            'vakasi_mengajar'         => 'float',
            'vakasi_tugas_jabatan'      => 'float',
            'vakasi_tugas_tambahan'     => 'float',
            'vakasi_peserta_kegiatan'   => 'float',  // FIX Bug 1
            'vakasi_lembur'             => 'float',
            'vakasi_piket'              => 'float',
            'tunjangan_lainnya'         => 'float',
            'potongan_keterlambatan'  => 'float',
            'potongan_alfa'           => 'float',
            'potongan_tetap'          => 'float',
            'potongan_lainnya'        => 'float',
            'potongan_liburan'        => 'float',
            'total_pendapatan'        => 'float',
            'total_potongan'          => 'float',
            'gaji_bersih'             => 'float',
            'dibayar_pada'            => 'datetime',
            'ada_koreksi_manual'      => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function periodePenggajian()
    {
        return $this->belongsTo(PeriodePenggajian::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function detailPenggajian()
    {
        return $this->hasMany(DetailPenggajian::class);
    }

    public function difinalisasiOleh()
    {
        return $this->belongsTo(User::class, 'difinalisasi_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'draft'   => ['label' => 'Draft',   'color' => 'secondary'],
            'final'   => ['label' => 'Final',   'color' => 'primary'],
            'dibayar' => ['label' => 'Dibayar', 'color' => 'success'],
            default   => ['label' => ucfirst($this->status), 'color' => 'secondary'],
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeFinal($query)
    {
        return $query->where('status', 'final');
    }

    public function scopeByPeriode($query, int $periodeId)
    {
        return $query->where('periode_penggajian_id', $periodeId);
    }
}