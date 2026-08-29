<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodePenggajian extends Model
{
    protected $table = 'periode_penggajian';

    protected $fillable = [
        'nama',
        'bulan',
        'tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'dikunci_pada',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
            'dikunci_pada'    => 'datetime',
            'bulan'           => 'integer',
            'tahun'           => 'integer',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function penggajian()
    {
        return $this->hasMany(Penggajian::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function isKunci(): bool
    {
        return $this->dikunci_pada !== null;
    }

    /**
     * True bila ada periode gaji yang sedang diproses / dihitung tapi belum
     * dibayar (status 'proses' atau 'selesai'). Dipakai untuk mengunci
     * perubahan data yang mempengaruhi gaji (mis. Jabatan) selama proses gaji.
     */
    public static function sedangProses(): bool
    {
        return static::whereIn('status', ['proses', 'selesai'])->exists();
    }

    public function getNamaBulanAttribute(): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April',   5 => 'Mei',       6 => 'Juni',
            7 => 'Juli',    8 => 'Agustus',   9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($bulan[$this->bulan] ?? '') . ' ' . $this->tahun;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->whereIn('status', ['draft', 'proses', 'selesai']);
    }
}