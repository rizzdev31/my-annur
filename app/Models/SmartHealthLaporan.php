<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Laporan kesehatan santri (sakit) → divalidasi Bagian Kesehatan → dipantau.
 */
class SmartHealthLaporan extends Model
{
    protected $table = 'smart_health_laporan';

    protected $fillable = [
        'santri_id', 'pelapor_tenaga_pendidik_id', 'deskripsi_penyakit', 'foto',
        'status', 'kondisi_akhir', 'disetujui_oleh', 'disetujui_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return ['disetujui_pada' => 'datetime'];
    }

    public function santri()      { return $this->belongsTo(Santri::class); }
    public function pelapor()     { return $this->belongsTo(TenagaPendidik::class, 'pelapor_tenaga_pendidik_id'); }
    public function disetujuiOleh(){ return $this->belongsTo(TenagaPendidik::class, 'disetujui_oleh'); }
    public function pengecekan()  { return $this->hasMany(SmartHealthPengecekan::class, 'laporan_id'); }

    public function scopeMenunggu($q) { return $q->where('status', 'menunggu'); }
    public function scopeAktif($q)    { return $q->where('status', 'dalam_pengecekan'); }

    public function fotoUrl(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }
}
