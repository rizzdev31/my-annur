<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Penanda tangan slip gaji: namanya dan gambar tanda tangannya.
 *
 * Tabel ini hanya berisi SATU baris; selalu diakses lewat `ambil()`.
 */
class SettingSlipGaji extends Model
{
    protected $table = 'setting_slip_gaji';

    protected $fillable = [
        'nama_bendahara', 'jabatan_penanda_tangan', 'ttd_path', 'ditetapkan_oleh',
    ];

    /** Baris tunggal; dibuat bila belum ada supaya pemanggil tak perlu memeriksa. */
    public static function ambil(): self
    {
        return static::firstOrCreate([], ['jabatan_penanda_tangan' => 'Bendahara']);
    }

    /**
     * Nama yang tercetak di slip.
     *
     * Bila `nama_bendahara` kosong, diambil dari pemegang jabatan penanda tangan
     * yang sedang aktif — sehingga pergantian bendahara di data kepegawaian
     * otomatis tercermin tanpa ada yang perlu menyunting pengaturan ini.
     */
    public function namaTercetak(): ?string
    {
        if (filled($this->nama_bendahara)) {
            return $this->nama_bendahara;
        }

        $jabatan = Jabatan::whereNull('deleted_at')
            ->where('nama_jabatan', $this->jabatan_penanda_tangan)
            ->first();

        if (!$jabatan) return null;

        return JabatanGuru::with('tenagaPendidik.user')
            ->where('jabatan_id', $jabatan->id)
            ->aktif()
            ->get()
            ->map(fn ($jg) => $jg->tenagaPendidik?->user?->name)
            ->filter()
            ->first();
    }

    /** URL gambar tanda tangan, atau null bila belum diunggah. */
    public function ttdUrl(): ?string
    {
        if (blank($this->ttd_path)) return null;

        return \Illuminate\Support\Facades\Storage::disk('public')->exists($this->ttd_path)
            ? asset('storage/' . $this->ttd_path)
            : null;
    }

    public function ditetapkanOleh()
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }
}
