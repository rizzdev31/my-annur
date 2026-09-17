<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'nama',
        'nama_deskriptif', // nama tokoh kelas (mis. "Ibnu Sina") — dikirim ke RamahAnak
        'jenis',        // sekolah | tahfidz | tahsin
        'level_tahsin', // khusus jenis=tahsin
        'tingkat',
        'tahun_ajaran_id',
        'wali_kelas_id',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'     => 'boolean',
            'level_tahsin' => 'integer',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function waliKelas()
    {
        return $this->belongsTo(TenagaPendidik::class, 'wali_kelas_id');
    }

    /** Seluruh keanggotaan, termasuk riwayat. Untuk anggota saat ini pakai santriAktif(). */
    public function santri()
    {
        return $this->belongsToMany(Santri::class, 'kelas_santri')
            ->withPivot(['tanggal_masuk', 'tanggal_keluar', 'tahun_ajaran_id', 'keterangan', 'is_aktif'])
            ->withTimestamps();
    }

    public function santriAktif()
    {
        return $this->santri()->wherePivot('is_aktif', true);
    }

    /**
     * Slot keanggotaan: santri hanya boleh punya SATU kelas aktif per slot.
     * Tahfidz & tahsin satu slot (program Quran) — santri lulus Persiapan Tahfidz
     * pindah ke kelas tahfidz dan otomatis keluar dari kelas tahsinnya.
     */
    public const SLOT = ['sekolah' => ['sekolah'], 'tahfidz' => ['tahfidz', 'tahsin'], 'tahsin' => ['tahfidz', 'tahsin']];

    public function jenisSeslot(): array
    {
        return self::SLOT[$this->jenis] ?? [$this->jenis];
    }

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeSekolah($query)
    {
        return $query->where('jenis', 'sekolah');
    }

    public function scopeTahfidz($query)
    {
        return $query->where('jenis', 'tahfidz');
    }

    public function scopeTahsin($query)
    {
        return $query->where('jenis', 'tahsin');
    }
}
