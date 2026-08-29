<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Santri extends Model
{
    use SoftDeletes, HasApiTokens;

    protected $table = 'santri';

    protected $fillable = [
        'nip',
        'nama_lengkap',
        'nama_panggilan',
        'email',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'no_whatsapp',
        'foto',
        'is_aktif',
        'tahsin_level',
        'program_quran',
        'password',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_aktif'      => 'boolean',
            'password'      => 'hashed',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'kelas_santri')
            ->withPivot(['tanggal_masuk', 'tanggal_keluar', 'tahun_ajaran_id', 'keterangan', 'is_aktif'])
            ->withTimestamps();
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}
