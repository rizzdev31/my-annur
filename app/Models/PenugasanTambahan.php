<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenugasanTambahan extends Model
{
    protected $table = 'penugasan_tambahan';

    protected $fillable = [
        'tugas_tambahan_id',
        'tenaga_pendidik_id',
        'status_pengerjaan',
        // Keputusan admin atas tenggat pengisian
        'tenggat_perpanjangan',
        'alasan_perpanjangan',
        'diperpanjang_oleh',
        'diputuskan_pada',
        // Vakasi per penerima
        'setting_vakasi_id',
        'vakasi_override',
        'vakasi_dikunci',
        'vakasi_ditetapkan_oleh',
        'vakasi_ditetapkan_pada',
        // Pengerjaan & pelaporan
        'laporan',
        'file_laporan',
        'bukti_tipe',       // teks | foto | link  (BARU)
        'link_bukti',       // URL bukti           (BARU)
        'teks_bukti',       // deskripsi teks      (BARU)
        'dikerjakan_pada',
        'dilaporkan_pada',
        // Verifikasi
        'disetujui',
        'diverifikasi_oleh',
        'catatan_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'tenggat_perpanjangan' => 'date',
            'diputuskan_pada'      => 'datetime',
            'dikerjakan_pada' => 'datetime',
            'dilaporkan_pada' => 'datetime',
            'disetujui'       => 'boolean',
            'vakasi_override' => 'float',
            'vakasi_dikunci'  => 'boolean',
            'vakasi_ditetapkan_pada' => 'datetime',
        ];
    }

    // ─── Tenggat pengisian ───────────────────────────────────────────────────

    /**
     * Batas pengisian yang BERLAKU untuk penerima ini: perpanjangan bila
     * diberikan admin, kalau tidak ya tanggal_selesai tugasnya. NULL = tanpa
     * batas (tugas terbuka), jadi tidak bisa dikatakan terlambat.
     */
    public function batasPengisian(): ?\Carbon\Carbon
    {
        if ($this->tenggat_perpanjangan) {
            return \Carbon\Carbon::parse($this->tenggat_perpanjangan)->endOfDay();
        }
        $akhir = $this->tugasTambahan?->tanggal_selesai;
        return $akhir ? \Carbon\Carbon::parse($akhir)->endOfDay() : null;
    }

    /** Tenggatnya sudah lewat? (tanpa batas = tidak pernah lewat) */
    public function lewatTenggat(?string $tanggal = null): bool
    {
        $batas = $this->batasPengisian();
        if (!$batas) return false;

        $acuan = $tanggal ? \Carbon\Carbon::parse($tanggal) : \App\Services\TimezoneHelper::now();
        return $acuan->gt($batas);
    }

    /**
     * Masih boleh diisi guru? Tugas yang sudah selesai, sudah diputuskan tidak
     * terlaksana, atau tenggatnya lewat tanpa perpanjangan → tidak bisa.
     */
    public function bisaDiisi(): bool
    {
        if (in_array($this->status_pengerjaan, ['selesai', 'tidak_selesai'], true)) return false;
        return !$this->lewatTenggat();
    }

    /** Sudah diputuskan admin sebagai tidak terlaksana. */
    public function tidakTerlaksana(): bool
    {
        return $this->status_pengerjaan === 'tidak_selesai';
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tugasTambahan()
    {
        return $this->belongsTo(TugasTambahan::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function settingVakasi()
    {
        return $this->belongsTo(SettingVakasi::class);
    }

    public function riwayatVakasi()
    {
        return $this->hasMany(RiwayatVakasiTugas::class, 'penugasan_id')->latest();
    }

    public function diverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /** Kegiatan TERBARU (backward-compat, tampilan tunggal). */
    public function kegiatanAbsensi()
    {
        return $this->hasOne(AbsensiKegiatan::class, 'penugasan_id')->latestOfMany();
    }

    /** SEMUA kegiatan pada penugasan ini (tugas rentang boleh punya banyak kegiatan). */
    public function kegiatanList()
    {
        return $this->hasMany(AbsensiKegiatan::class, 'penugasan_id')
            ->orderByDesc('tanggal_kegiatan')->orderByDesc('id');
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    /** URL foto bukti dari storage */
    public function getFileLaporanUrlAttribute(): ?string
    {
        return $this->file_laporan ? asset('storage/'.$this->file_laporan) : null;
    }

    /**
     * Paket bukti lengkap (semua tipe dalam satu array).
     * Dipakai Vue untuk render bukti sesuai tipe.
     */
    public function getBuktiLengkapAttribute(): array
    {
        return [
            'tipe' => $this->bukti_tipe,
            'foto' => $this->file_laporan_url,
            'link' => $this->link_bukti,
            'teks' => $this->teks_bukti ?? $this->laporan,
        ];
    }

    /**
     * Ringkasan bukti untuk satu sel tabel berita acara.
     * Foto/berkas tidak ikut dicetak, cukup disebut keberadaannya — dokumen
     * ini pengantar pelaporan, bukan pengganti lampirannya.
     */
    public function getRingkasBuktiAttribute(): string
    {
        $bagian = [];

        if ($teks = trim((string) ($this->teks_bukti ?? $this->laporan))) {
            $bagian[] = \Illuminate\Support\Str::limit($teks, 120);
        }
        if ($this->link_bukti)  $bagian[] = 'Tautan: ' . $this->link_bukti;
        if ($this->file_laporan) $bagian[] = '(foto/berkas terlampir di sistem)';

        return implode(' · ', $bagian);
    }

    /** Apakah sudah ada bukti dalam bentuk apapun */
    public function hasBukti(): bool
    {
        return (bool) ($this->file_laporan || $this->link_bukti
                     || $this->teks_bukti  || $this->laporan);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Nominal vakasi yang berlaku untuk penugasan ini.
     * Prioritas: override individu → setting penerima → setting tugas → 0
     */
    public function getNominalVakasi(): float
    {
        return app(\App\Services\VakasiTugasService::class)->untukPenerima($this)['nominal'];
    }

    /**
     * Label sumber vakasi untuk UI audit trail.
     */
    /**
     * Sumber nominal — dari service yang sama, bukan tebakan sendiri.
     *
     * Versi lama memeriksa `setting_tugas` SEBELUM `override_tugas`, padahal
     * nominalnya dihitung dengan urutan sebaliknya: sebuah tugas yang punya
     * override DAN setting akan dibayar dari override tetapi dilabeli
     * "setting_tugas". Label yang berdusta lebih buruk daripada tanpa label.
     */
    public function getSumberVakasiAttribute(): string
    {
        return app(\App\Services\VakasiTugasService::class)->untukPenerima($this)['sumber'];
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeDisetujui($query)
    {
        return $query->where('disetujui', true);
    }

    public function scopeSelesai($query)
    {
        return $query->where('status_pengerjaan', 'selesai');
    }

    public function scopeMenungguVerifikasi($query)
    {
        return $query->where('status_pengerjaan', 'selesai')
                     ->whereNull('disetujui');
    }
}