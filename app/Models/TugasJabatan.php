<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasJabatan extends Model
{
    protected $table = 'tugas_jabatan';

    protected $fillable = [
        'jabatan_id',
        'nama_tugas',
        'deskripsi',
        'frekuensi',
        'tipe_pengerjaan',      // mandiri | absen_kegiatan
        'perlu_verifikasi',     // true=realisasi butuh approve admin, false=auto-sah
        // 'setting_vakasi_id' → kolom ini di-DROP oleh migrasi 2026_create_kinerja_jabatan_tables
        // 'wajib_laporan'     → kolom ini di-DROP oleh migrasi 2026_create_kinerja_jabatan_tables
        'icon',
        'estimasi_durasi_menit',
        'urutan',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'         => 'boolean',
            'perlu_verifikasi' => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    /**
     * @deprecated setting_vakasi_id kolom sudah di-DROP dari skema.
     * Gunakan SettingVakasi::berlakuUntukGuru() di PayrollCalculationService.
     * Relasi ini dipertahankan untuk backward compat — akan selalu return null.
     */
    public function settingVakasi()
    {
        return $this->belongsTo(SettingVakasi::class);
    }

    public function realisasi()
    {
        return $this->hasMany(RealisasiTugasJabatan::class);
    }

    public function logKerja()
    {
        return $this->hasMany(LogKerjaHarian::class);
    }

    public function kegiatanAbsensi()
    {
        return $this->hasMany(AbsensiKegiatan::class, 'sumber_id')
            ->where('sumber_tipe', 'tugas_jabatan');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getFrekuensiLabelAttribute(): string
    {
        return match ($this->frekuensi) {
            'harian'     => 'Harian',
            'mingguan'   => 'Mingguan',
            'bulanan'    => 'Bulanan',
            'insidental' => 'Insidental',
            default      => ucfirst($this->frekuensi),
        };
    }

    public function getTipePengerjaanLabelAttribute(): string
    {
        return match ($this->tipe_pengerjaan ?? 'mandiri') {
            'mandiri'        => 'Mandiri',
            'absen_kegiatan' => 'Absen Kegiatan',
            default          => 'Mandiri',
        };
    }

    public function getIsMandiriAttribute(): bool
    {
        return ($this->tipe_pengerjaan ?? 'mandiri') === 'mandiri';
    }

    public function getIsAbsenKegiatanAttribute(): bool
    {
        return $this->tipe_pengerjaan === 'absen_kegiatan';
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
    public function scopeByJabatan($q, int $id) { return $q->where('jabatan_id', $id); }
    public function scopeMandiri($q) { return $q->where('tipe_pengerjaan', 'mandiri'); }
    public function scopeAbsenKegiatan($q) { return $q->where('tipe_pengerjaan', 'absen_kegiatan'); }
}