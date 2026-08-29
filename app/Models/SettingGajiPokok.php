<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingGajiPokok extends Model
{
    protected $table = 'setting_gaji_pokok';

    protected $fillable = [
        'jabatan_id',
        'nominal',
        'berlaku_mulai',
        'berlaku_selesai',
        'keterangan',
        'is_aktif',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'         => 'float',
            'berlaku_mulai'   => 'date',
            'berlaku_selesai' => 'date',
            'is_aktif'        => 'boolean',
        ];
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}