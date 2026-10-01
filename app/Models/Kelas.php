<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'nama',
        'nama_deskriptif', // nama tokoh kelas (mis. "Ibnu Sina") — dikirim ke RamahAnak
        'jenis',        // sekolah | pesantren | tahfidz | tahsin
        'level_tahsin', // khusus jenis=tahsin
        'tingkat',
        'tahun_ajaran_id',
        'wali_kelas_id',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'     => 'boolean',
            'level_tahsin' => 'integer',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function waliKelas()
    {
        return $this->belongsTo(TenagaPendidik::class, 'wali_kelas_id');
    }

    /** Seluruh keanggotaan, termasuk riwayat. Untuk anggota saat ini pakai santriAktif(). */
    public function santri()
    {
        return $this->belongsToMany(Santri::class, 'kelas_santri')
            ->withPivot(['tanggal_masuk', 'tanggal_keluar', 'tahun_ajaran_id', 'keterangan', 'is_aktif'])
            ->withTimestamps();
    }

    public function santriAktif()
    {
        return $this->santri()->wherePivot('is_aktif', true);
    }

    /**
     * Slot keanggotaan: santri hanya boleh punya SATU kelas aktif per slot.
     * Tahfidz & tahsin satu slot (program Quran) — santri lulus Persiapan Tahfidz
     * pindah ke kelas tahfidz dan otomatis keluar dari kelas tahsinnya.
     *
     * PESANTREN slotnya SENDIRI, bukan bergabung dengan sekolah. Kegiatan
     * pesantren (mis. Muhadharah SMA Putra/Putri) mengumpulkan santri dari
     * beberapa kelas sekolah sekaligus; bila satu slot dengan sekolah, memasukkan
     * santri ke kelas pesantren akan MENGELUARKANNYA dari kelas X/XI/XII-nya.
     */
    public const SLOT = [
        'sekolah'   => ['sekolah'],
        'pesantren' => ['pesantren'],
        'tahfidz'   => ['tahfidz', 'tahsin'],
        'tahsin'    => ['tahfidz', 'tahsin'],
    ];

    /**
     * Jenis yang pembelajarannya berjalan sama seperti sekolah: dijadwalkan di
     * Jadwal Mengajar umum, memakai mapel tipe `reguler`, jurnal & absensi santri
     * biasa. Yang membedakan pesantren dari sekolah hanya slot keanggotaannya.
     */
    public const JENIS_REGULER = ['sekolah', 'pesantren'];

    /** Label jenis untuk pesan & tampilan. */
    public const LABEL_JENIS = [
        'sekolah'   => 'Sekolah',
        'pesantren' => 'Pesantren',
        'tahfidz'   => 'Tahfidz',
        'tahsin'    => 'Tahsin',
    ];

    public function jenisSeslot(): array
    {
        return self::SLOT[$this->jenis] ?? [$this->jenis];
    }

    /** Nama slot untuk pesan ke pengguna, mis. "kelas sekolah". */
    public function slotLabel(): string
    {
        return match ($this->jenis) {
            'sekolah'   => 'kelas sekolah',
            'pesantren' => 'kelas pesantren',
            default     => 'kelas tahfidz/tahsin',
        };
    }

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeSekolah($query)
    {
        return $query->where('jenis', 'sekolah');
    }

    public function scopePesantren($query)
    {
        return $query->where('jenis', 'pesantren');
    }

    /** Sekolah + pesantren — kelas yang dijadwalkan & dijurnal dengan cara yang sama. */
    public function scopeReguler($query)
    {
        return $query->whereIn('jenis', self::JENIS_REGULER);
    }

    public function scopeTahfidz($query)
    {
        return $query->where('jenis', 'tahfidz');
    }

    public function scopeTahsin($query)
    {
        return $query->where('jenis', 'tahsin');
    }
}
