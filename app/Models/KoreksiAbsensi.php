<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model KoreksiAbsensi
 *
 * Audit trail semua koreksi yang dilakukan admin terhadap:
 * - absensi harian (hadir/terlambat/alfa/dll)
 * - absensi mengajar (JP terlaksana, status)
 * - realisasi tugas jabatan (disetujui/ditolak)
 * - penugasan tambahan (status pengerjaan)
 *
 * Kolom tabel (sinkron dengan migration_koreksi_absensi.php):
 * id, tenaga_pendidik_id, tanggal, tipe_absensi,
 * absensi_harian_id, absensi_mengajar_id,
 * realisasi_tugas_id, penugasan_tambahan_id,
 * field_dikoreksi, nilai_lama, nilai_baru, alasan,
 * status, dikoreksi_oleh, created_at, updated_at
 */
class KoreksiAbsensi extends Model
{
    protected $table = 'koreksi_absensi';

    protected $fillable = [
        'tenaga_pendidik_id',
        'tanggal',
        'tipe_absensi',           // harian | mengajar | tugas_jabatan | tugas_tambahan
                                  // (DB lama: absen_harian | absen_mengajar)
        'absensi_harian_id',
        'absensi_mengajar_id',
        'realisasi_tugas_id',     // referensi ke realisasi_tugas_jabatan
        'penugasan_tambahan_id',  // referensi ke penugasan_tambahan
        'field_dikoreksi',        // kolom yang diubah, cth: 'status', 'jp_terlaksana'
        'nilai_lama',
        'nilai_baru',
        'alasan',
        'status',                 // pending | disetujui | ditolak
        'dikoreksi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function absensiHarian()
    {
        return $this->belongsTo(AbsensiHarian::class);
    }

    public function absensiMengajar()
    {
        return $this->belongsTo(AbsensiMengajar::class);
    }

    public function realisasiTugas()
    {
        return $this->belongsTo(RealisasiTugasJabatan::class, 'realisasi_tugas_id');
    }

    public function penugasanTambahan()
    {
        return $this->belongsTo(PenugasanTambahan::class, 'penugasan_tambahan_id');
    }

    public function dikoreksiOleh()
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    // ─── Accessor ────────────────────────────────────────────────────────────

    /**
     * Label tipe untuk UI.
     */
    public function getTipeLabelAttribute(): string
    {
        return [
            // Nilai baru (setelah migration sinkronisasi)
            'harian'          => 'Absensi Harian',
            'mengajar'        => 'Absensi Mengajar',
            'tugas_jabatan'   => 'Tugas Jabatan',
            'tugas_tambahan'  => 'Tugas Tambahan',
            // Nilai lama (backward compat sebelum migration)
            'absen_harian'    => 'Absensi Harian',
            'absen_mengajar'  => 'Absensi Mengajar',
        ][$this->tipe_absensi] ?? $this->tipe_absensi;
    }

    /**
     * Label status untuk UI.
     */
    public function getStatusLabelAttribute(): string
    {
        return [
            'pending'   => 'Pending',
            'disetujui' => 'Disetujui',
            'ditolak'   => 'Ditolak',
        ][$this->status] ?? $this->status;
    }

    /**
     * Decode nilai_lama dari JSON jika berbentuk JSON string.
     * Jika plain string (cth: 'hadir'), kembalikan string langsung.
     */
    public function getNilaiLamaDecodedAttribute(): mixed
    {
        if (!$this->nilai_lama) return null;
        $decoded = json_decode($this->nilai_lama, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->nilai_lama;
    }

    /**
     * Decode nilai_baru dari JSON jika berbentuk JSON string.
     */
    public function getNilaiBaruDecodedAttribute(): mixed
    {
        if (!$this->nilai_baru) return null;
        $decoded = json_decode($this->nilai_baru, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->nilai_baru;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByBulan($query, int $bulan, int $tahun)
    {
        return $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
    }

    public function scopeByGuru($query, int $guruId)
    {
        return $query->where('tenaga_pendidik_id', $guruId);
    }

    public function scopeByTipe($query, string $tipe)
    {
        return $query->where('tipe_absensi', $tipe);
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status', 'disetujui');
    }
}