<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiMengajar extends Model
{
    protected $table = 'absensi_mengajar';

    protected $fillable = [
        'jadwal_mengajar_id',
        'tenaga_pendidik_id',
        'tanggal',
        'jam_mulai_aktual',
        'jam_selesai_aktual',
        'jp_terlaksana',
        'status',
        'digantikan_oleh',
        'materi',
        'keterangan',
        'foto_mengajar',       // Foto dokumentasi dari Flutter
        'sudah_buka_jurnal',   // Wajib buka link jurnal dulu
        'is_koreksi',
        'dikoreksi_oleh',
        'libur_pembelajaran_id',  // sesi ini diliburkan oleh kegiatan tsb
        'status_sebelum',         // status sebelum diliburkan (untuk pemulihan)
    ];

    protected function casts(): array
    {
        return [
            'tanggal'           => 'date',
            'jp_terlaksana'     => 'integer',
            'is_koreksi'        => 'boolean',
            'sudah_buka_jurnal' => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function jadwalMengajar()
    {
        return $this->belongsTo(JadwalMengajar::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function digantikanOleh()
    {
        return $this->belongsTo(TenagaPendidik::class, 'digantikan_oleh');
    }

    public function dikoreksiOleh()
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    public function absensiSantri()
    {
        return $this->hasMany(AbsensiSantri::class);
    }

    public function liburPembelajaran()
    {
        return $this->belongsTo(LiburPembelajaran::class, 'libur_pembelajaran_id');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getFotoMengajarUrlAttribute(): ?string
    {
        return $this->foto_mengajar
            ? asset('storage/' . $this->foto_mengajar)
            : null;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByBulan($query, int $bulan, int $tahun)
    {
        return $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
    }

    public function scopeTerlaksana($query)
    {
        return $query->where('status', 'terlaksana');
    }
}