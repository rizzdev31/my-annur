<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasTambahan extends Model
{
    protected $table = 'tugas_tambahan';

    protected $fillable = [
        'judul',
        'deskripsi',
        'tanggal_mulai',
        'tanggal_selesai',
        'tipe',
        'tipe_pengerjaan',   // mandiri | absen_kegiatan
        'setting_vakasi_id',
        'vakasi_override',
        'vakasi_dikunci',
        'vakasi_ditetapkan_oleh',
        'vakasi_ditetapkan_pada',
        'wajib_laporan',
        'status',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
            'vakasi_override' => 'float',
            'vakasi_dikunci'  => 'boolean',
            'vakasi_ditetapkan_pada' => 'datetime',
            'wajib_laporan'   => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function settingVakasi()   { return $this->belongsTo(SettingVakasi::class); }
    public function dibuatOleh()      { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function penugasan()       { return $this->hasMany(PenugasanTambahan::class); }
    public function riwayatVakasi()   { return $this->hasMany(RiwayatVakasiTugas::class)->latest(); }
    public function vakasiDitetapkanOleh() { return $this->belongsTo(User::class, 'vakasi_ditetapkan_oleh'); }
    public function kegiatanAbsensi() {
        return $this->hasMany(AbsensiKegiatan::class, 'sumber_id')
            ->where('sumber_tipe', 'tugas_tambahan');
    }

    public function tenagaPendidik()
    {
        return $this->belongsToMany(TenagaPendidik::class, 'penugasan_tambahan')
                    ->withPivot(['status_pengerjaan', 'laporan', 'disetujui'])
                    ->withTimestamps();
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Nominal vakasi tugas ini. Jawabannya datang dari VakasiTugasService —
     * satu pintu yang sama dengan payroll dan layar superadmin. Dulu metode ini
     * membaca relasi setting apa adanya, sehingga setting NONAKTIF tetap
     * membayar dan urutan sumbernya bisa berbeda dari payroll.
     */
    public function getNominalVakasi(): float
    {
        return app(\App\Services\VakasiTugasService::class)->untukTugas($this)['nominal'];
    }

    public function getIsMandiriAttribute(): bool
    {
        return ($this->tipe_pengerjaan ?? 'mandiri') === 'mandiri';
    }

    public function getIsAbsenKegiatanAttribute(): bool
    {
        return $this->tipe_pengerjaan === 'absen_kegiatan';
    }

    public function getTipePengerjaanLabelAttribute(): string
    {
        return match ($this->tipe_pengerjaan ?? 'mandiri') {
            'mandiri'        => 'Mandiri',
            'absen_kegiatan' => 'Absen Kegiatan',
            default          => 'Mandiri',
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($q)          { return $q->where('status', 'aktif'); }
    public function scopeMandiri($q)         { return $q->where('tipe_pengerjaan', 'mandiri'); }
    public function scopeAbsenKegiatan($q)   { return $q->where('tipe_pengerjaan', 'absen_kegiatan'); }
}