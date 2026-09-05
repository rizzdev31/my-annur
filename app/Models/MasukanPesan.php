<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pesan di dalam utas masukan.
 *
 * `pengirim_tipe` = guru | admin | bot. Nilai 'bot' disediakan sejak awal
 * untuk jawaban otomatis (Gemini) — pesan bot ditulis lewat jalur yang sama
 * dengan pesan manusia, sehingga UI tidak perlu diubah saat bot diaktifkan.
 */
class MasukanPesan extends Model
{
    protected $table = 'masukan_pesan';

    protected $fillable = ['masukan_id', 'pengirim_tipe', 'user_id', 'isi', 'lampiran', 'meta'];

    protected function casts(): array
    {
        return [
            'lampiran' => 'array',
            'meta'     => 'array',
        ];
    }

    public function masukan()
    {
        return $this->belongsTo(Masukan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** URL publik tiap lampiran (path disimpan relatif terhadap disk 'public'). */
    public function lampiranUrl(): array
    {
        return collect($this->lampiran ?? [])->map(fn($p) => asset('storage/' . $p))->all();
    }
}
