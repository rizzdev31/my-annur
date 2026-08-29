<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingTahsinMateri extends Model
{
    protected $table = 'setting_tahsin_materi';

    protected $fillable = ['level', 'urutan', 'nama', 'is_aktif'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'urutan' => 'integer', 'is_aktif' => 'boolean'];
    }

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
    public function scopeLevel($q, int $level) { return $q->where('level', $level); }
}
