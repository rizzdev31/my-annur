<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekapKinerjaBulanan extends Model
{
    protected $table = 'rekap_kinerja_bulanan';

    protected $fillable = [
        // Identitas
        'tenaga_pendidik_id', 'bulan', 'tahun',

        // ── Skor 3 komponen (BARU — dari KinerjaCalculationService) ──────────
        'skor_absensi',        // skor komponen absensi (0-100)
        'skor_tugas',          // skor komponen tugas (0-100)
        'skor_administrasi',   // skor komponen administrasi (0-100)
        'skor_piket',          // skor komponen piket (0-100)
        'setting_kinerja_id',  // setting yang dipakai saat kalkulasi

        // ── Backward compat (kolom lama) ─────────────────────────────────────
        'skor_keaktifan',      // alias skor_log (backward compat)
        'skor_penugasan',      // alias skor_tugas (backward compat)
        'skor_total',

        // ── Data mentah log kerja ─────────────────────────────────────────────
        'total_log_submitted',
        'total_log_diverifikasi',
        'total_durasi_menit',

        // ── Data mentah penugasan ─────────────────────────────────────────────
        'total_penugasan_diterima',
        'total_penugasan_selesai',

        // ── Data mentah absensi (BARU) ────────────────────────────────────────
        'total_hadir',
        'total_terlambat',
        'total_izin',
        'total_sakit',
        'total_alfa',
        'total_dinas_luar',
        'total_hari_kerja',

        // ── Data mentah mengajar (BARU) ───────────────────────────────────────
        'total_sesi_jadwal',
        'total_sesi_terlaksana',
        'total_sesi_dilaporkan',
        'total_jp_jadwal',
        'total_jp_terlaksana',

        // ── Data mentah realisasi jabatan (BARU) ──────────────────────────────
        'total_realisasi_jabatan',
        'total_realisasi_disetujui',

        // ── Audit ─────────────────────────────────────────────────────────────
        'catatan_superadmin',
        'dikaji_oleh',
        'dikaji_pada',
        'sudah_dikunci',
    ];

    protected function casts(): array
    {
        return [
            'dikaji_pada'          => 'datetime',
            'sudah_dikunci'        => 'boolean',
            // Skor
            'skor_absensi'         => 'float',
            'skor_tugas'           => 'float',
            'skor_administrasi'    => 'float',
            'skor_piket'           => 'float',
            'skor_keaktifan'       => 'float',
            'skor_penugasan'       => 'float',
            'skor_total'           => 'float',
        ];
    }

    // ─── Relasi ───────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function dikajioleh()
    {
        return $this->belongsTo(User::class, 'dikaji_oleh');
    }

    public function settingKinerja()
    {
        return $this->belongsTo(SettingKinerja::class, 'setting_kinerja_id');
    }

    // ─── Accessor: Label & Badge dinamis dari SettingKinerja ──────────────────

    /**
     * Label grade membaca dari SettingKinerja yang dipakai saat kalkulasi.
     * Fallback ke hardcoded jika setting tidak ada.
     */
    public function getLabelSkorAttribute(): string
    {
        $setting = $this->settingKinerja ?? SettingKinerja::getDefault();
        return $setting->getLabelGrade($this->skor_total);
    }

    public function getBadgeSkorAttribute(): array
    {
        $setting = $this->settingKinerja ?? SettingKinerja::getDefault();
        return $setting->getBadgeGrade($this->skor_total);
    }

    /**
     * Grade huruf (A/B/C/D/E).
     */
    public function getGradeAttribute(): string
    {
        $setting = $this->settingKinerja ?? SettingKinerja::getDefault();
        return $setting->getGrade($this->skor_total);
    }

    /**
     * Persentase kehadiran dari data mentah.
     */
    public function getPctHadirAttribute(): float
    {
        $hk = $this->total_hari_kerja ?? 0;
        if ($hk === 0) return 0;
        $hadir = ($this->total_hadir ?? 0)
               + ($this->total_terlambat ?? 0)
               + ($this->total_dinas_luar ?? 0);
        return round($hadir / $hk * 100, 1);
    }
}