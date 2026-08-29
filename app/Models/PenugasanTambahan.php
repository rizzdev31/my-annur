<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenugasanTambahan extends Model
{
    protected $table = 'penugasan_tambahan';

    protected $fillable = [
        'tugas_tambahan_id',
        'tenaga_pendidik_id',
        'status_pengerjaan',
        // Vakasi per penerima
        'setting_vakasi_id',
        'vakasi_override',
        // Pengerjaan & pelaporan
        'laporan',
        'file_laporan',
        'bukti_tipe',       // teks | foto | link  (BARU)
        'link_bukti',       // URL bukti           (BARU)
        'teks_bukti',       // deskripsi teks      (BARU)
        'dikerjakan_pada',
        'dilaporkan_pada',
        // Verifikasi
        'disetujui',
        'diverifikasi_oleh',
        'catatan_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'dikerjakan_pada' => 'datetime',
            'dilaporkan_pada' => 'datetime',
            'disetujui'       => 'boolean',
            'vakasi_override' => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tugasTambahan()
    {
        return $this->belongsTo(TugasTambahan::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function settingVakasi()
    {
        return $this->belongsTo(SettingVakasi::class);
    }

    public function diverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /** Kegiatan TERBARU (backward-compat, tampilan tunggal). */
    public function kegiatanAbsensi()
    {
        return $this->hasOne(AbsensiKegiatan::class, 'penugasan_id')->latestOfMany();
    }

    /** SEMUA kegiatan pada penugasan ini (tugas rentang boleh punya banyak kegiatan). */
    public function kegiatanList()
    {
        return $this->hasMany(AbsensiKegiatan::class, 'penugasan_id')
            ->orderByDesc('tanggal_kegiatan')->orderByDesc('id');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    /** URL foto bukti dari storage */
    public function getFileLaporanUrlAttribute(): ?string
    {
        return $this->file_laporan ? asset('storage/'.$this->file_laporan) : null;
    }

    /**
     * Paket bukti lengkap (semua tipe dalam satu array).
     * Dipakai Vue untuk render bukti sesuai tipe.
     */
    public function getBuktiLengkapAttribute(): array
    {
        return [
            'tipe' => $this->bukti_tipe,
            'foto' => $this->file_laporan_url,
            'link' => $this->link_bukti,
            'teks' => $this->teks_bukti ?? $this->laporan,
        ];
    }

    /** Apakah sudah ada bukti dalam bentuk apapun */
    public function hasBukti(): bool
    {
        return (bool) ($this->file_laporan || $this->link_bukti
                     || $this->teks_bukti  || $this->laporan);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Nominal vakasi yang berlaku untuk penugasan ini.
     * Prioritas: override individu → setting penerima → setting tugas → 0
     */
    public function getNominalVakasi(): float
    {
        if ($this->vakasi_override !== null) {
            return (float) $this->vakasi_override;
        }
        if ($this->settingVakasi) {
            return (float) $this->settingVakasi->nominal;
        }
        return (float) ($this->tugasTambahan?->getNominalVakasi() ?? 0);
    }

    /**
     * Label sumber vakasi untuk UI audit trail.
     */
    public function getSumberVakasiAttribute(): string
    {
        if ($this->vakasi_override !== null)                     return 'override_individu';
        if ($this->setting_vakasi_id)                            return 'setting_penerima';
        if ($this->tugasTambahan?->setting_vakasi_id)            return 'setting_tugas';
        if ($this->tugasTambahan?->vakasi_override !== null)     return 'override_tugas';
        return 'tidak_ada';
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeDisetujui($query)
    {
        return $query->where('disetujui', true);
    }

    public function scopeSelesai($query)
    {
        return $query->where('status_pengerjaan', 'selesai');
    }

    public function scopeMenungguVerifikasi($query)
    {
        return $query->where('status_pengerjaan', 'selesai')
                     ->whereNull('disetujui');
    }
}