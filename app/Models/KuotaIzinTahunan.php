<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KuotaIzinTahunan extends Model
{
    protected $table = 'kuota_izin_tahunan';

    protected $fillable = [
        'tenaga_pendidik_id',
        'setting_jenis_pengajuan_id',
        'tahun',
        'kuota_total',
        'terpakai',
    ];

    protected function casts(): array
    {
        return [
            'tahun'       => 'integer',
            'kuota_total' => 'integer',
            'terpakai'    => 'integer',
        ];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jenisPengajuan()
    {
        return $this->belongsTo(SettingJenisPengajuan::class, 'setting_jenis_pengajuan_id');
    }

    public function getSisaAttribute(): int
    {
        return max(0, $this->kuota_total - $this->terpakai);
    }
}