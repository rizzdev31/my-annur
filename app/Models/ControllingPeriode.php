<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControllingPeriode extends Model
{
    protected $table = 'controlling_periode';

    protected $fillable = ['nama', 'bulan', 'tahun', 'is_aktif'];

    protected $casts = ['is_aktif' => 'boolean', 'bulan' => 'integer', 'tahun' => 'integer'];

    public function jadwal()
    {
        return $this->hasMany(ControllingJadwal::class, 'periode_id');
    }

    /**
     * Periode yang sedang berjalan.
     * UTAMAKAN periode yang cocok dengan BULAN+TAHUN sistem saat ini → otomatis
     * ikut pergantian bulan tanpa perlu "Aktifkan" manual (mis. hasil Duplikat
     * bulan berikutnya langsung dipakai begitu masuk bulannya).
     * FALLBACK ke periode yang ditandai aktif manual bila periode bulan berjalan
     * belum dibuat (agar sistem tidak mati mendadak di pergantian bulan).
     */
    public static function aktif(): ?self
    {
        $now = \App\Services\TimezoneHelper::now();
        return static::where('bulan', (int) $now->format('n'))
                ->where('tahun', (int) $now->format('Y'))
                ->first()
            ?? static::where('is_aktif', true)->latest('id')->first();
    }

    /** Apakah sudah ada periode untuk bulan sistem saat ini (agar admin bisa diingatkan). */
    public static function adaBulanIni(): bool
    {
        $now = \App\Services\TimezoneHelper::now();
        return static::where('bulan', (int) $now->format('n'))
            ->where('tahun', (int) $now->format('Y'))->exists();
    }
}
