<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Perizinan santri: diajukan (sementara oleh guru petugas; santri menyusul),
 * disetujui/ditolak oleh petugas perizinan. Jenis: syar'i / non-syar'i.
 */
class IzinSantri extends Model
{
    protected $table = 'izin_santri';

    protected $fillable = [
        'santri_id', 'jenis', 'alasan', 'tanggal_mulai', 'tanggal_selesai',
        'lampiran', 'status', 'pengaju_tipe', 'diajukan_oleh', 'disetujui_oleh',
        'catatan_petugas', 'diputuskan_pada', 'sumber',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function santri()       { return $this->belongsTo(Santri::class); }
    public function diajukanOleh()  { return $this->belongsTo(TenagaPendidik::class, 'diajukan_oleh'); }
    public function disetujuiOleh() { return $this->belongsTo(TenagaPendidik::class, 'disetujui_oleh'); }

    public function scopePending($q) { return $q->where('status', 'diajukan'); }

    public function getJenisLabelAttribute(): string
    {
        return $this->jenis === 'syari' ? "Syar'i" : 'Non-Syar\'i';
    }
}
