<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LemburPeserta — state per guru dalam satu event lembur + bukti (foto/deskripsi/GPS).
 * Status: menunggu_bukti → sah (otomatis upload / manual admin) | ditolak | dibatalkan.
 */
class LemburPeserta extends Model
{
    protected $table = 'lembur_peserta';

    protected $fillable = [
        'lembur_id',
        'tenaga_pendidik_id',
        'status',
        // Bukti
        'deskripsi',
        'foto_bukti',
        'diunggah_pada',
        // Geo
        'latitude',
        'longitude',
        'validasi_lokasi',
        'jarak_meter',
        'setting_lokasi_id',
        'nama_wifi',
        'bssid_wifi',
        // Vakasi
        'nominal_vakasi',
        // Pengesahan
        'metode_pengesahan',  // otomatis | manual_admin
        'disahkan_oleh',
        'disahkan_pada',
        'alasan_pengesahan',
    ];

    protected function casts(): array
    {
        return [
            'diunggah_pada'  => 'datetime',
            'latitude'       => 'float',
            'longitude'      => 'float',
            'jarak_meter'    => 'float',
            'nominal_vakasi' => 'float',
            'disahkan_pada'  => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function lembur()
    {
        return $this->belongsTo(Lembur::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function settingLokasi()
    {
        return $this->belongsTo(SettingLokasiAbsensi::class, 'setting_lokasi_id');
    }

    public function disahkanOleh()
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getFotoBuktiUrlAttribute(): ?string
    {
        return $this->foto_bukti ? asset('storage/'.$this->foto_bukti) : null;
    }

    public function getSudahUploadAttribute(): bool
    {
        return $this->foto_bukti !== null;
    }

    /** Terlewat = sudah lewat tenggang upload tapi belum ada bukti & masih menunggu. */
    public function getTerlewatAttribute(): bool
    {
        return $this->status === 'menunggu_bukti'
            && !$this->sudah_upload
            && $this->relationLoaded('lembur')
            && $this->lembur?->sudahTerlewat();
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeSah($q)            { return $q->where('status', 'sah'); }
    public function scopeMenungguBukti($q)  { return $q->where('status', 'menunggu_bukti'); }
    public function scopeByGuru($q, int $id) { return $q->where('tenaga_pendidik_id', $id); }
}
