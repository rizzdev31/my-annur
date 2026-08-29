<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PunishmentKinerja — tindakan admin atas kinerja bulanan buruk.
 * jenis: potongan (→ potongan penggajian periode), evaluasi, peringatan, pencopotan (→ cabut jabatan).
 */
class PunishmentKinerja extends Model
{
    protected $table = 'punishment_kinerja';

    protected $fillable = [
        'tenaga_pendidik_id',
        'bulan',
        'tahun',
        'jenis',          // potongan | evaluasi | peringatan | pencopotan
        'nominal',        // utk potongan
        'jabatan_id',     // utk pencopotan
        'skor_kinerja',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'bulan'        => 'integer',
            'tahun'        => 'integer',
            'nominal'      => 'float',
            'skor_kinerja' => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function getJenisLabelAttribute(): string
    {
        return [
            'potongan'   => 'Potongan Gaji',
            'evaluasi'   => 'Catatan Evaluasi',
            'peringatan' => 'Surat Peringatan',
            'pencopotan' => 'Pencopotan Jabatan',
        ][$this->jenis] ?? ucfirst($this->jenis);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByPeriode($q, int $bulan, int $tahun)
    {
        return $q->where('bulan', $bulan)->where('tahun', $tahun);
    }

    public function scopePotongan($q)
    {
        return $q->where('jenis', 'potongan');
    }
}
