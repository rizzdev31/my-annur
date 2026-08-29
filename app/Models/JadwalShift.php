<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalShift extends Model
{
    protected $table = 'jadwal_shift';

    protected $fillable = [
        'tenaga_pendidik_id', 'setting_jam_kerja_id',
        'tanggal_mulai', 'tanggal_selesai', 'keterangan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jamKerja()
    {
        return $this->belongsTo(SettingJamKerja::class, 'setting_jam_kerja_id');
    }
}
