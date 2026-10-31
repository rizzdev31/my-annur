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
    public function tempatkanLevelTahsin(Kelas $kelasTahsin, ?int $oleh = null): bool
    {
        if ($kelasTahsin->jenis !== 'tahsin' || !$kelasTahsin->level_tahsin) return false;
        if ($this->punyaProgresTahsin()) return false;
        if ((int) $this->tahsin_level === (int) $kelasTahsin->level_tahsin) return false;

        $dari = $this->tahsin_level;
        $this->update(['tahsin_level' => $kelasTahsin->level_tahsin]);
        $this->catatLevelTahsin($dari, (int) $kelasTahsin->level_tahsin, 'penempatan', $kelasTahsin, $oleh);

        return true;
    }

    /**
     * Pindah ke kelas tahsin BERLEVEL LEBIH TINGGI = naik level, dan materinya
     * ikut berubah karena materi dibaca dari `tahsin_level` santri.
     *
     * Tahsin memang berjenjang (Level 1..5, lalu 6 = Persiapan Tahfidz), jadi
     * memindahkan santri dari Tahsin 1 ke Tahsin 2 adalah kenaikan — bukan
     * sekadar perombakan halaqoh. Sebelum ini levelnya tertahan sehingga guru
     * di kelas level 4 masih disuguhi materi Level 3 (terukur 31 Okt 2026:
     * 15 santri, semuanya kelasnya tepat satu level di atas levelnya).
     *
     * TIGA pagar, semuanya karena kerusakan 17 Sep 2026 yang pernah nyata:
     *
     *  1. TURUN TIDAK IKUT. Dipindah ke kelas berlevel lebih rendah tidak
     *     menurunkan pencapaian — dulu 9 santri turun 6→4 hanya karena
     *     kelompoknya ditukar, dan nilai mereka jadi menggantung di level yang
     *     tidak mereka tempati lagi.
     *  2. TASNIF BERJALAN MENAHAN. Bila masih ada tugas tasnif `ditugaskan` di
     *     level sekarang, kenaikannya ditahan: menaikkan level akan membuat
     *     ujian itu menguji level yang sudah dilewati, dan bila lulus akan
     *     menaikkan level sekali lagi.
     *  3. MELOMPAT LEBIH DARI SATU LEVEL DITAHAN. Lompatan 3→6 hampir selalu
     *     salah pilih kelas, bukan kenaikan beruntun.
     *
     * Materi level lama yang belum lulus TIDAK menahan: itu keputusan pedagogis
     * pengampu (sama seperti override pada naikLevel), hanya dilaporkan.
     *
     * @return array{status:string, dari:?int, ke:?int, alasan:?string}
     *         status: 'naik' | 'tetap' | 'tertahan'
     */
    public function naikkanLevelTahsinMengikutiKelas(Kelas $kelasTahsin, ?int $oleh = null): array
    {
        $hasil = fn (string $st, ?string $alasan = null, ?int $ke = null) => [
            'status' => $st, 'dari' => $this->tahsin_level, 'ke' => $ke, 'alasan' => $alasan,
        ];

        if ($kelasTahsin->jenis !== 'tahsin' || !$kelasTahsin->level_tahsin) {
            return $hasil('tetap');
        }

        // Belum berprogres → penempatan awal (boleh ke arah mana pun).
        if (!$this->punyaProgresTahsin()) {
            $berubah = $this->tempatkanLevelTahsin($kelasTahsin, $oleh);

            return $berubah
                ? ['status' => 'naik', 'dari' => null, 'ke' => (int) $kelasTahsin->level_tahsin,
                   'alasan' => 'penempatan awal']
                : $hasil('tetap');
        }

        $sekarang = (int) ($this->tahsin_level ?? 1);
        $tujuan   = (int) $kelasTahsin->level_tahsin;

        if ($tujuan <= $sekarang) {
            return $hasil('tetap', $tujuan < $sekarang
                ? 'kelasnya berlevel lebih rendah — pencapaian tidak diturunkan' : null);
        }

        if ($tujuan - $sekarang > 1) {
            return $hasil('tertahan',
                "melompat {$sekarang} → {$tujuan}; naikkan satu level setiap kali atau perbaiki level kelasnya");
        }

        if ($this->tasnifBerjalan($sekarang)) {
            return $hasil('tertahan',
                "masih ada tasnif level {$sekarang} yang berjalan; selesaikan atau batalkan dulu");
        }

        $this->update(['tahsin_level' => $tujuan]);
        $this->catatLevelTahsin($sekarang, $tujuan, 'naik_kelas', $kelasTahsin, $oleh);

        return ['status' => 'naik', 'dari' => $sekarang, 'ke' => $tujuan, 'alasan' => null];
    }

    /** Ada tugas tasnif yang masih berjalan di level tertentu? */
    public function tasnifBerjalan(int $level): bool
    {
        return \App\Models\TugasTasnif::where('santri_id', $this->id)
            ->where('level', $level)
            ->where('status', 'ditugaskan')
            ->exists();
    }

    /** Satu-satunya penulis jejak level tahsin. */
    public function catatLevelTahsin(
        ?int $dari,
        int $ke,
        string $sebab,
        ?Kelas $kelas = null,
        ?int $oleh = null,
        ?string $catatan = null,
    ): void {
        \App\Models\RiwayatLevelTahsin::create([
            'santri_id'  => $this->id,
            'level_dari' => $dari,
            'level_ke'   => $ke,
            'sebab'      => $sebab,
            'kelas_id'   => $kelas?->id,
            'kelas_nama' => $kelas?->nama,
            'catatan'    => $catatan,
            'oleh'       => $oleh ?? \Illuminate\Support\Facades\Auth::id(),
        ]);
    }

    public function riwayatLevelTahsin()
    {
        return $this->hasMany(\App\Models\RiwayatLevelTahsin::class, 'santri_id')->latest('id');
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
