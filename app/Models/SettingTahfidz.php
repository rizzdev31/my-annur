<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingTahfidz extends Model
{
    protected $table = 'setting_tahfidz';

    protected $fillable = [
        'nilai_min', 'nilai_maks', 'nilai_lulus',
        'jam_pagi_mulai', 'jam_pagi_selesai', 'jam_sore_mulai', 'jam_sore_selesai',
        'jp_per_sesi', 'pola_jadwal', 'vakasi_tasmi',
    ];

    protected function casts(): array
    {
        return [
            'nilai_min'    => 'integer',
            'nilai_maks'   => 'integer',
            'nilai_lulus'  => 'float',
            'jp_per_sesi'  => 'integer',
            'pola_jadwal'  => 'array',
            'vakasi_tasmi' => 'float',
        ];
    }

    public const POLA_DEFAULT = [
        'senin'  => ['sore'],
        'selasa' => ['pagi', 'sore'],
        'rabu'   => ['pagi', 'sore'],
        'kamis'  => ['pagi', 'sore'],
        'jumat'  => ['pagi'],
    ];

    /** Ambil setting (buat default bila belum ada). */
    public static function get(): self
    {
        return static::first() ?? static::create([
            'nilai_min' => 1, 'nilai_maks' => 10, 'nilai_lulus' => 7,
            'pola_jadwal' => self::POLA_DEFAULT,
        ]);
    }

    /** Jam mulai/selesai untuk satu sesi (pagi|sore). */
    public function jamSesi(string $sesi): array
    {
        return $sesi === 'pagi'
            ? [substr((string) $this->jam_pagi_mulai, 0, 5), substr((string) $this->jam_pagi_selesai, 0, 5)]
            : [substr((string) $this->jam_sore_mulai, 0, 5), substr((string) $this->jam_sore_selesai, 0, 5)];
    }
}
