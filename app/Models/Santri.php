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

    /**
     * SELURUH keanggotaan kelas, termasuk riwayat yang sudah ditutup. Untuk
     * "kelas santri saat ini" pakai kelasAktif() / scopeAnggotaKelas().
     */
    public function kelas()
    {
        return $this->belongsToMany(Kelas::class, 'kelas_santri')
            ->withPivot(['tanggal_masuk', 'tanggal_keluar', 'tahun_ajaran_id', 'keterangan', 'is_aktif'])
            ->withTimestamps();
    }

    /** Keanggotaan kelas yang masih berjalan. */
    public function kelasAktif()
    {
        return $this->kelas()->wherePivot('is_aktif', true);
    }

    /**
     * Santri yang SAAT INI anggota kelas tsb.
     *
     * Wajib dipakai untuk semua roster. kelas_santri menyimpan riwayat — santri
     * yang pindah kelas tetap punya baris lama (is_aktif=false). Tanpa filter ini
     * ia muncul di kelas lama DAN baru: diabsen dua kali, WA wali ganda.
     */
    public function scopeAnggotaKelas($query, int $kelasId)
    {
        return $query->whereHas('kelas', fn ($q) => $q
            ->where('kelas.id', $kelasId)
            ->where('kelas_santri.is_aktif', true));
    }

    /**
     * Selaraskan tahsin_level dengan kelas TAHSIN aktif santri (materi mengikuti kelas):
     * kelas Persiapan Tahfidz (level 6) → tahsin_level 6, dst. Tak berbuat apa-apa
     * bila santri tak berada di kelas tahsin. Kembalikan true bila ada perubahan.
     */
    public function selaraskanLevelTahsin(): bool
    {
        $kelasTahsin = $this->kelas()
            ->wherePivot('is_aktif', true)
            ->where('kelas.jenis', 'tahsin')
            ->whereNotNull('level_tahsin')
            ->orderByDesc('level_tahsin')
            ->first();

        if ($kelasTahsin && (int) $this->tahsin_level !== (int) $kelasTahsin->level_tahsin) {
            $this->update(['tahsin_level' => $kelasTahsin->level_tahsin]);
            return true;
        }
        return false;
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
