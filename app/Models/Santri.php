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

    /** Santri sudah punya pencapaian tahsin (nilai materi atau ujian tasnif). */
    public function punyaProgresTahsin(): bool
    {
        return \App\Models\TahsinPenilaian::where('santri_id', $this->id)->exists()
            || \Illuminate\Support\Facades\DB::table('tugas_tasnif')->where('santri_id', $this->id)->where('lulus', true)->exists();
    }

    /**
     * Tempatkan level tahsin saat santri MASUK kelas tahsin.
     *
     * PENCAPAIAN TIDAK BERUBAH KARENA PINDAH KELAS — prinsip yang sama dengan
     * tahfidz (hafalan melekat pada santri, bukan pada halaqoh):
     *  - Santri belum punya progres tahsin → level mengikuti kelas (penempatan awal).
     *  - Santri sudah punya progres → level TETAP, lanjut dari pencapaiannya,
     *    walau kelas barunya berlevel lain. Naik level hanya lewat tasnif/naikLevel.
     *
     * Dulu level selalu disamakan dengan kelas pada SETIAP perubahan keanggotaan,
     * termasuk pindah kelas sekolah atau sekadar menyimpan form santri. Santri yang
     * lulus tasnif (level naik, kelas tetap) akan turun level diam-diam begitu
     * keanggotaannya tersentuh.
     *
     * @return bool true bila level diubah
     */
    public function tempatkanLevelTahsin(Kelas $kelasTahsin): bool
    {
        if ($kelasTahsin->jenis !== 'tahsin' || !$kelasTahsin->level_tahsin) return false;
        if ($this->punyaProgresTahsin()) return false;
        if ((int) $this->tahsin_level === (int) $kelasTahsin->level_tahsin) return false;

        $this->update(['tahsin_level' => $kelasTahsin->level_tahsin]);
        return true;
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
