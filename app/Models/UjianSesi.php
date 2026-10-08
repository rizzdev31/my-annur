<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu sesi ujian: satu kelas, satu mata ujian, satu penjaga.
 *
 * Sesi ini DIWUJUDKAN sebagai baris `jadwal_mengajar` bermasa-berlaku sehari
 * (relasi `jadwal`), sehingga absensi, roster santri, laporan, papan piket,
 * kinerja, dan INVAL berjalan dengan mesin yang sudah ada. Baris di tabel ini
 * menyimpan hal yang khas ujian: mata ujian, ruangan, dan buku-besar vakasinya.
 *
 * Vakasi penjaga dibayar PER SESI dan nominalnya di-snapshot saat penjaga
 * ditunjuk, supaya perubahan tarif tidak mengubah slip yang sudah terbit.
 */
class UjianSesi extends Model
{
    protected $table = 'ujian_sesi';

    protected $fillable = [
        'ujian_id', 'tanggal', 'jam_mulai', 'jam_selesai',
        'kelas_id', 'mata_pelajaran_id', 'ruangan', 'jumlah_jp',
        'penjaga_id', 'nominal_vakasi', 'vakasi_dibayar', 'dibayar_periode_id',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'jumlah_jp'      => 'integer',
            'nominal_vakasi' => 'float',
            'vakasi_dibayar' => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function penjaga()
    {
        return $this->belongsTo(TenagaPendidik::class, 'penjaga_id');
    }

    /** Jadwal bentukan sesi ini (ada setelah penjaga ditunjuk). */
    public function jadwal()
    {
        return $this->hasOne(JadwalMengajar::class, 'ujian_sesi_id');
    }

    // ─── Turunan ─────────────────────────────────────────────────────────────

    /** Catatan absensi mengajar untuk sesi ini (null bila belum ada/belum ada jadwal). */
    public function absensi(): ?AbsensiMengajar
    {
        $jadwalId = $this->jadwal?->id;
        if (!$jadwalId) return null;

        return AbsensiMengajar::where('jadwal_mengajar_id', $jadwalId)
            ->whereDate('tanggal', $this->tanggal->toDateString())->first();
    }

    /**
     * Siapa yang BENAR-BENAR menjaga: pengganti bila sesinya di-inval, selain itu
     * penjaga yang ditunjuk. Dipakai pembayaran vakasi — karena bila di-inval,
     * yang dibayar adalah penggantinya dengan nominal vakasi penjaga yang sama.
     */
    public function penjagaAktual(): ?int
    {
        $am = $this->absensi();
        if ($am && $am->digantikan_oleh) return (int) $am->digantikan_oleh;

        return $this->penjaga_id ? (int) $this->penjaga_id : null;
    }

    /** Sesi sudah benar-benar dijaga (ada absen & JP terlaksana)? */
    public function sudahDijaga(): bool
    {
        $am = $this->absensi();
        if (!$am) return false;

        return in_array($am->status, ['terlaksana', 'hadir', 'pengganti'], true)
            && ((int) $am->jp_terlaksana > 0 || $am->jam_mulai_aktual);
    }

    public function jamLabel(): string
    {
        return substr((string) $this->jam_mulai, 0, 5) . '–' . substr((string) $this->jam_selesai, 0, 5);
    }

    public function durasiMenit(): int
    {
        return (int) round(
            (strtotime($this->tanggal->toDateString() . ' ' . $this->jam_selesai)
                - strtotime($this->tanggal->toDateString() . ' ' . $this->jam_mulai)) / 60
        );
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopePadaTanggal($q, string $tanggal)
    {
        return $q->whereDate('tanggal', $tanggal);
    }

    public function scopeBelumBerpenjaga($q)
    {
        return $q->whereNull('penjaga_id');
    }
}
