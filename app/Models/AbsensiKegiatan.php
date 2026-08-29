<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AbsensiKegiatan — Master data kegiatan yang perlu diabsen
 * Dibuat oleh guru yang mendapat tugas tipe 'absen_kegiatan'
 */
class AbsensiKegiatan extends Model
{
    protected $table = 'absensi_kegiatan';

    protected $fillable = [
        'sumber_tipe',       // 'tugas_jabatan' | 'tugas_tambahan'
        'sumber_id',
        'realisasi_id',
        'penugasan_id',
        'pengabsen_id',
        'nama_kegiatan',
        'tanggal_kegiatan',
        'jam_mulai',
        'jam_selesai',
        'deskripsi',
        'lokasi',
        'status',
        'setting_vakasi_id',
        'vakasi_per_peserta',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kegiatan' => 'date',
            'vakasi_per_peserta' => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function peserta()
    {
        return $this->hasMany(AbsensiKegiatanPeserta::class);
    }

    public function pengabsen()
    {
        return $this->belongsTo(TenagaPendidik::class, 'pengabsen_id');
    }

    public function realisasi()
    {
        return $this->belongsTo(RealisasiTugasJabatan::class, 'realisasi_id');
    }

    public function penugasan()
    {
        return $this->belongsTo(PenugasanTambahan::class, 'penugasan_id');
    }

    public function settingVakasi()
    {
        return $this->belongsTo(SettingVakasi::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function getSumberAttribute()
    {
        return $this->sumber_tipe === 'tugas_jabatan'
            ? TugasJabatan::find($this->sumber_id)
            : TugasTambahan::find($this->sumber_id);
    }

    public function getTotalHadirAttribute(): int
    {
        return $this->peserta->where('status_kehadiran', 'hadir')->count();
    }

    public function getTotalPesertaAttribute(): int
    {
        return $this->peserta->count();
    }

    public function scopeAktif($q) { return $q->where('status', 'berlangsung'); }
    public function scopeSelesai($q) { return $q->where('status', 'selesai'); }
}