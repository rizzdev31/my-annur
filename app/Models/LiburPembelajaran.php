<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Libur pembelajaran karena kegiatan — pembelajaran diganti kegiatan, guru &
 * santri tetap masuk. BEDA dengan HariLibur (libur sehari penuh untuk semua).
 *
 * Semua penulisan absensi akibat baris ini lewat LiburMengajarService; jangan
 * menulis status 'libur' dari controller.
 */
class LiburPembelajaran extends Model
{
    protected $table = 'libur_pembelajaran';

    public const CAKUPAN = [
        'semua' => 'Semua pembelajaran',
        'kelas' => 'Kelas terpilih',
        'sesi'  => 'Sesi terpilih',
    ];

    protected $fillable = [
        'nama', 'tanggal', 'tanggal_selesai', 'cakupan', 'jenis_kelas',
        'jam_mulai', 'jam_selesai', 'materi_jurnal', 'hitung_jp',
        'isi_absensi_santri', 'status_santri', 'keterangan', 'is_aktif',
        'dibatalkan_pada', 'alasan_pembatalan', 'dibatalkan_oleh', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'            => 'date',
            'tanggal_selesai'    => 'date',
            'jenis_kelas'        => 'array',
            'hitung_jp'          => 'boolean',
            'isi_absensi_santri' => 'boolean',
            'is_aktif'           => 'boolean',
            'dibatalkan_pada'    => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'libur_pembelajaran_kelas', 'libur_pembelajaran_id', 'kelas_id');
    }

    public function jadwal()
    {
        return $this->belongsToMany(
            JadwalMengajar::class, 'libur_pembelajaran_jadwal', 'libur_pembelajaran_id', 'jadwal_mengajar_id'
        );
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function dibatalkanOleh()
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function absensiMengajar()
    {
        return $this->hasMany(AbsensiMengajar::class, 'libur_pembelajaran_id');
    }

    // ─── Turunan ─────────────────────────────────────────────────────────────

    public function getTanggalAkhirAttribute(): \Carbon\Carbon
    {
        return $this->tanggal_selesai ?? $this->tanggal;
    }

    public function getDurasiHariAttribute(): int
    {
        return $this->tanggal->diffInDays($this->tanggal_akhir) + 1;
    }

    public function getIsDibatalkanAttribute(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    /** Teks yang ditulis ke kolom materi jurnal. */
    public function teksJurnal(): string
    {
        return trim((string) $this->materi_jurnal) !== '' ? $this->materi_jurnal : $this->nama;
    }

    public function keteranganSesi(): string
    {
        return 'Libur pembelajaran: ' . $this->nama;
    }

    /** Mencakup tanggal tsb? (hanya rentangnya — cakupan jadwal dihitung service) */
    public function mencakupTanggal(string $tanggal): bool
    {
        return $tanggal >= $this->tanggal->toDateString()
            && $tanggal <= $this->tanggal_akhir->toDateString();
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeBerlaku($q)
    {
        return $q->where('is_aktif', true)->whereNull('dibatalkan_pada');
    }

    public function scopePadaTanggal($q, string $tanggal)
    {
        return $q->where('tanggal', '<=', $tanggal)
            ->where(fn ($s) => $s->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal));
    }
}
