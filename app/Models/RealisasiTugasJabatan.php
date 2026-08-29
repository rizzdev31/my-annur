<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealisasiTugasJabatan extends Model
{
    protected $table = 'realisasi_tugas_jabatan';

    protected $fillable = [
        'tugas_jabatan_id',
        'tenaga_pendidik_id',
        'tanggal',
        'keterangan',
        'file_bukti',
        'bukti_tipe',    // teks | foto | link
        'link_bukti',
        'teks_bukti',
        'disetujui',
        'terlambat',
        'diverifikasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'   => 'date',
            'disetujui' => 'boolean',
            'terlambat' => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tugasJabatan()
    {
        return $this->belongsTo(TugasJabatan::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function diverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function kegiatanAbsensi()
    {
        return $this->hasOne(AbsensiKegiatan::class, 'realisasi_id');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getFileBuktiUrlAttribute(): ?string
    {
        return $this->file_bukti ? asset('storage/'.$this->file_bukti) : null;
    }

    public function getBuktiLengkapAttribute(): array
    {
        return [
            'tipe'  => $this->bukti_tipe,
            'foto'  => $this->file_bukti_url,
            'link'  => $this->link_bukti,
            'teks'  => $this->teks_bukti ?? $this->keterangan,
        ];
    }

    public function hasBukti(): bool
    {
        return $this->file_bukti || $this->link_bukti || $this->teks_bukti || $this->keterangan;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeDisetujui($q)   { return $q->where('disetujui', true); }
    public function scopePending($q)     { return $q->whereNull('disetujui'); }
    public function scopeByBulan($q, int $bulan, int $tahun)
    {
        return $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
    }
}