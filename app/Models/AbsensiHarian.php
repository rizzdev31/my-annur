<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiHarian extends Model
{
    protected $table = 'absensi_harian';

    protected $fillable = [
        'tenaga_pendidik_id',
        'tanggal',
        'jam_masuk',
        'jam_pulang',
        'foto_masuk',
        'foto_pulang',
        'lat_masuk',
        'lng_masuk',
        'lat_pulang',
        'lng_pulang',
        'status',
        'menit_terlambat',
        'keterangan',
        'is_koreksi',
        'dikoreksi_oleh',
        // Validasi lokasi
        'validasi_lokasi',
        'nama_wifi',
        'bssid_wifi',
        'jarak_meter',
        'setting_lokasi_id',
        // Checkout luar lokasi
        'pulang_luar_lokasi',
        'alasan_pulang',
        'jarak_pulang_meter',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'            => 'date',
            'is_koreksi'         => 'boolean',
            'menit_terlambat'    => 'integer',
            'jarak_meter'        => 'float',
            'pulang_luar_lokasi' => 'boolean',
            'jarak_pulang_meter' => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function dikoreksiOleh()
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    public function koreksi()
    {
        return $this->hasMany(KoreksiAbsensi::class, 'absensi_harian_id');
    }

    public function settingLokasi()
    {
        return $this->belongsTo(\App\Models\SettingLokasiAbsensi::class, 'setting_lokasi_id');
    }

    // ─── Accessor ────────────────────────────────────────────────────────────

    /**
     * Apakah absensi ini tervalidasi lokasi (GPS atau WiFi).
     */
    public function getLokasiValidAttribute(): bool
    {
        return in_array($this->validasi_lokasi, [
            'valid_koordinat', 'valid_wifi', 'valid_dinas_luar', 'bypass_admin',
        ]);
    }

    /**
     * Label validasi lokasi untuk UI.
     */
    public function getValidasiLokasiLabelAttribute(): string
    {
        return [
            'valid_koordinat'  => '📍 GPS Valid',
            'valid_wifi'       => '📶 WiFi Valid',
            'valid_dinas_luar' => '🚗 Dinas Luar',
            'invalid'          => '❌ Di Luar Area',
            'bypass_admin'     => '🔧 Input Admin',
            'tidak_diperiksa'  => '—',
        ][$this->validasi_lokasi ?? 'tidak_diperiksa'] ?? '—';
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Return badge warna untuk tampilan UI.
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'hadir'      => ['label' => 'Hadir',      'color' => 'success'],
            'terlambat'  => ['label' => 'Terlambat',  'color' => 'warning'],
            'izin'       => ['label' => 'Izin',       'color' => 'info'],
            'sakit'      => ['label' => 'Sakit',      'color' => 'info'],
            'alfa'       => ['label' => 'Alfa',       'color' => 'danger'],
            'libur'      => ['label' => 'Libur',      'color' => 'secondary'],
            'dinas_luar' => ['label' => 'Dinas Luar', 'color' => 'primary'],
            default      => ['label' => ucfirst($this->status), 'color' => 'secondary'],
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByBulan($query, int $bulan, int $tahun)
    {
        return $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
    }

    public function scopeHadir($query)
    {
        return $query->whereIn('status', ['hadir', 'terlambat', 'dinas_luar']);
    }

    public function scopeAlfa($query)
    {
        return $query->where('status', 'alfa');
    }
}