<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingAbsenMengajar extends Model
{
    protected $table = 'setting_absen_mengajar';

    protected $fillable = [
        'nama',
        'durasi_jp_menit',
        'toleransi_checkin_menit',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
}