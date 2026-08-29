<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingKinerja extends Model
{
    protected $table = 'setting_kinerja';

    protected $fillable = [
        'nama', 'is_aktif', 'is_default',
        // Absensi
        'bobot_absensi', 'bobot_absensi_harian', 'bobot_absensi_mengajar',
        'nilai_hadir', 'nilai_terlambat', 'nilai_izin',
        'nilai_sakit', 'nilai_dinas_luar', 'nilai_alfa',
        'hitung_penalty_terlambat', 'toleransi_terlambat_menit',
        'penalty_per_terlambat', 'max_penalty_terlambat',
        // Tugas
        'bobot_tugas', 'bobot_tugas_tambahan', 'bobot_tugas_jabatan',
        'jika_tidak_ada_tugas',
        // Administrasi
        'bobot_administrasi', 'bobot_laporan_mengajar', 'bobot_log_kerja',
        'target_log_per_hari',
        // Piket (komponen ke-4)
        'bobot_piket',
        'skor_min_piket',   // floor sub-skor piket (batas bawah, adil)
        // Grade
        'grade_a', 'grade_b', 'grade_c', 'grade_d',
        'keterangan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'                 => 'boolean',
            'is_default'               => 'boolean',
            'hitung_penalty_terlambat' => 'boolean',
            'bobot_absensi'            => 'float',
            'bobot_absensi_harian'     => 'float',
            'bobot_absensi_mengajar'   => 'float',
            'nilai_hadir'              => 'float',
            'nilai_terlambat'          => 'float',
            'nilai_izin'               => 'float',
            'nilai_sakit'              => 'float',
            'nilai_dinas_luar'         => 'float',
            'nilai_alfa'               => 'float',
            'penalty_per_terlambat'    => 'float',
            'max_penalty_terlambat'    => 'float',
            'bobot_tugas'              => 'float',
            'bobot_tugas_tambahan'     => 'float',
            'bobot_tugas_jabatan'      => 'float',
            'bobot_administrasi'       => 'float',
            'bobot_piket'              => 'float',
            'skor_min_piket'           => 'integer',
            'bobot_laporan_mengajar'   => 'float',
            'bobot_log_kerja'          => 'float',
            'target_log_per_hari'      => 'float',
            'grade_a' => 'float', 'grade_b' => 'float',
            'grade_c' => 'float', 'grade_d' => 'float',
        ];
    }

    // ─── Nilai per status (mapping status absensi → nilai) ────────────────────

    /**
     * Ambil nilai numerik untuk status absensi tertentu.
     * Dipakai KinerjaCalculationService saat hitung skor harian.
     */
    public function nilaiStatus(string $status): float
    {
        return match($status) {
            'hadir'      => $this->nilai_hadir,
            'terlambat'  => $this->nilai_terlambat,
            'izin'       => $this->nilai_izin,
            'izin_sakit' => $this->nilai_izin,   // sama dengan izin
            'sakit'      => $this->nilai_sakit,
            'dinas_luar' => $this->nilai_dinas_luar,
            'alfa'       => $this->nilai_alfa,
            'libur'      => -1,  // -1 = dikecualikan dari perhitungan
            default      => 0,
        };
    }

    // ─── Validasi ─────────────────────────────────────────────────────────────

    public function isBobotValid(): bool
    {
        return abs(($this->bobot_absensi + $this->bobot_tugas + $this->bobot_administrasi + ($this->bobot_piket ?? 0)) - 100) < 0.01;
    }

    // ─── Grade ───────────────────────────────────────────────────────────────

    public function getGrade(float $skor): string
    {
        if ($skor >= $this->grade_a) return 'A';
        if ($skor >= $this->grade_b) return 'B';
        if ($skor >= $this->grade_c) return 'C';
        if ($skor >= $this->grade_d) return 'D';
        return 'E';
    }

    public function getLabelGrade(float $skor): string
    {
        return ['A' => 'Sangat Baik', 'B' => 'Baik', 'C' => 'Cukup',
                'D' => 'Perlu Perhatian', 'E' => 'Rendah'][$this->getGrade($skor)];
    }

    public function getBadgeGrade(float $skor): array
    {
        return [
            'A' => ['label' => 'Sangat Baik',    'class' => 'bg-emerald-50 text-emerald-700'],
            'B' => ['label' => 'Baik',            'class' => 'bg-teal-50 text-teal-700'],
            'C' => ['label' => 'Cukup',           'class' => 'bg-amber-50 text-amber-700'],
            'D' => ['label' => 'Perlu Perhatian', 'class' => 'bg-orange-50 text-orange-700'],
            'E' => ['label' => 'Rendah',          'class' => 'bg-red-50 text-red-700'],
        ][$this->getGrade($skor)];
    }

    // ─── Relasi & Scope ───────────────────────────────────────────────────────

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public static function getDefault(): self
    {
        return static::aktif()->where('is_default', true)->first()
            ?? static::aktif()->first()
            ?? new self([
                'bobot_absensi' => 35, 'bobot_absensi_harian' => 70, 'bobot_absensi_mengajar' => 30,
                'nilai_hadir' => 100, 'nilai_terlambat' => 75, 'nilai_izin' => 70,
                'nilai_sakit' => 80, 'nilai_dinas_luar' => 100, 'nilai_alfa' => 0,
                'hitung_penalty_terlambat' => false, 'toleransi_terlambat_menit' => 0,
                'penalty_per_terlambat' => 5, 'max_penalty_terlambat' => 20,
                'bobot_tugas' => 30, 'bobot_tugas_tambahan' => 60, 'bobot_tugas_jabatan' => 40,
                'jika_tidak_ada_tugas' => 'sempurna',
                'bobot_administrasi' => 20, 'bobot_laporan_mengajar' => 60, 'bobot_log_kerja' => 40,
                'target_log_per_hari' => 1,
                'bobot_piket' => 15, 'skor_min_piket' => 50,
                'grade_a' => 90, 'grade_b' => 75, 'grade_c' => 60, 'grade_d' => 40,
            ]);
    }
}