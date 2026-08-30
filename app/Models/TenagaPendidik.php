<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class TenagaPendidik extends Model
{
    use SoftDeletes;

    protected $table = 'tenaga_pendidik';

    /**
     * Setelah data guru tersimpan (create/update/ubah status/jabatan), sinkronkan
     * identitasnya ke RamahAnak (Smart = master). afterCommit → aman dari transaksi;
     * refId di-hash → hanya perubahan yang benar-benar terkirim (idempoten).
     */
    protected static function booted(): void
    {
        static::saved(function (self $guru): void {
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($guru) {
                app(\App\Services\GuruSyncService::class)->sync($guru->id);
            });
        });
    }

    protected $fillable = [
        'user_id',
        'jabatan_id',              // backward compat — jabatan utama
        'setting_jam_kerja_id',    // jam kerja per individu (null → pakai default)
        'nip', 'nik',
        'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'pendidikan_terakhir', 'jurusan',
        'no_hp', 'alamat',
        'tanggal_masuk', 'tanggal_keluar',
        'no_rekening', 'nama_bank', 'nama_rekening',
        'jenis_guru', 'is_aktif',
        'is_mukim',                 // guru mukim asrama → punya libur individu rolling
        'hari_libur',               // hari libur mingguan tetap (array, mis. ["sabtu"])
        // Status kepegawaian (dari file user)
        'status_kepegawaian',
        'tanggal_nonaktif',
        'alasan_nonaktif',
        'dinonaktifkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir'    => 'date',
            'tanggal_masuk'    => 'date',
            'tanggal_keluar'   => 'date',
            'tanggal_nonaktif' => 'date',
            'is_aktif'         => 'boolean',
            'hari_libur'       => 'array',
            'is_mukim'         => 'boolean',
        ];
    }

    // ─── Libur individu (guru mukim) ───────────────────────────────────────────

    public function liburTendik()
    {
        return $this->hasMany(LiburTendik::class);
    }

    /** Apakah TANGGAL ini hari libur individu guru ini. */
    public function isLiburPada(string $tanggal): bool
    {
        return LiburTendik::isLibur($this->id, $tanggal);
    }

    public function scopeMukim($query)
    {
        return $query->where('is_mukim', true);
    }

    // ─── Delegasi peran petugas (perizinan/kesehatan) ──────────────────────────

    public function petugasPeran()
    {
        return $this->hasMany(PetugasPeran::class);
    }

    /** Apakah guru ini petugas aktif untuk peran tertentu (perizinan|kesehatan). */
    public function isPetugas(string $peran): bool
    {
        return PetugasPeran::isPetugas($this->id, $peran);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELASI — USER & JABATAN
    // ═══════════════════════════════════════════════════════════════════════════

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Jam kerja yang di-assign khusus ke tendik ini (opsional).
     */
    public function jamKerja()
    {
        return $this->belongsTo(SettingJamKerja::class, 'setting_jam_kerja_id');
    }

    /**
     * Jam kerja efektif: setting milik tendik (jika ada & aktif) → fallback default global.
     * Dipakai semua alur absensi/payroll/kinerja agar mendukung shift berbeda per guru.
     */
    /** Memo shift per tanggal (cegah query berulang saat dipanggil dalam loop). */
    protected array $memoJamKerja = [];

    /**
     * Jam kerja efektif untuk suatu tanggal (default hari ini).
     * Prioritas: roster SHIFT yang mencakup tanggal → setting individu → default.
     * Roster shift (jadwal_shift) TIDAK mengubah setting_jam_kerja_id asli guru.
     */
    public function jamKerjaAktif(?string $tanggal = null): ?SettingJamKerja
    {
        $tgl = $tanggal ?: now()->toDateString();
        if (array_key_exists($tgl, $this->memoJamKerja)) return $this->memoJamKerja[$tgl];

        // Overlay shift untuk tanggal ini.
        $shift = \App\Models\JadwalShift::where('tenaga_pendidik_id', $this->id)
            ->whereDate('tanggal_mulai', '<=', $tgl)
            ->whereDate('tanggal_selesai', '>=', $tgl)
            ->latest('tanggal_mulai')->first();
        if ($shift && $shift->jamKerja && $shift->jamKerja->is_aktif) {
            return $this->memoJamKerja[$tgl] = $shift->jamKerja;
        }

        $own = $this->setting_jam_kerja_id ? $this->jamKerja : null;
        return $this->memoJamKerja[$tgl] = (($own && $own->is_aktif) ? $own : SettingJamKerja::getDefault());
    }

    /**
     * Backward compat — jabatan dari kolom jabatan_id.
     */
    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    /**
     * Semua jabatan yang pernah/sedang dipegang (via pivot jabatan_guru).
     */
    public function jabatanGuru()
    {
        return $this->hasMany(JabatanGuru::class);
    }

    /**
     * Jabatan yang sedang aktif (mendukung rangkap jabatan).
     */
    public function jabatanAktif()
    {
        return $this->hasMany(JabatanGuru::class)
            ->aktif()
            ->with('jabatan');
    }

    /**
     * Jabatan utama (primer) saat ini.
     */
    public function jabatanUtama()
    {
        return $this->hasOne(JabatanGuru::class)
            ->aktif()
            ->utama()
            ->with('jabatan');
    }

    /**
     * Riwayat status kepegawaian.
     */
    public function riwayatStatus()
    {
        return $this->hasMany(RiwayatStatusKepegawaian::class)
            ->orderByDesc('tanggal_efektif');
    }

    public function dinonaktifkanOleh()
    {
        return $this->belongsTo(User::class, 'dinonaktifkan_oleh');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELASI — ABSENSI & JADWAL
    // ═══════════════════════════════════════════════════════════════════════════

    public function absensiHarian()
    {
        return $this->hasMany(AbsensiHarian::class);
    }

    public function absensiMengajar()
    {
        return $this->hasMany(AbsensiMengajar::class);
    }

    public function jadwalMengajar()
    {
        return $this->hasMany(JadwalMengajar::class);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELASI — LOKASI ABSENSI (BARU — per-user multi lokasi)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Semua lokasi absensi yang pernah di-assign ke guru ini.
     * Many-to-many via pivot tenaga_pendidik_lokasi.
     */
    public function lokasiAbsensi()
    {
        return $this->belongsToMany(
            SettingLokasiAbsensi::class,
            'tenaga_pendidik_lokasi',
            'tenaga_pendidik_id',
            'setting_lokasi_id'
        )->withPivot([
            'konteks',
            'berlaku_mulai',
            'berlaku_selesai',
            'keterangan',
            'ditambahkan_oleh',
        ])->withTimestamps();
    }

    /**
     * Lokasi absensi yang AKTIF berlaku sekarang untuk guru ini.
     * Dipakai LokasiAbsensiService untuk validasi real-time.
     */
    public function lokasiAbsensiAktif()
    {
        return $this->lokasiAbsensi()
            ->where('setting_lokasi_absensi.is_aktif', true)
            ->where(function ($q) {
                $q->whereNull('tenaga_pendidik_lokasi.berlaku_mulai')
                  ->orWhere('tenaga_pendidik_lokasi.berlaku_mulai', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('tenaga_pendidik_lokasi.berlaku_selesai')
                  ->orWhere('tenaga_pendidik_lokasi.berlaku_selesai', '>=', now());
            });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RELASI — TUGAS & PENGGAJIAN
    // ═══════════════════════════════════════════════════════════════════════════

    public function penugasanTambahan()
    {
        return $this->hasMany(PenugasanTambahan::class);
    }

    public function realisasiTugasJabatan()
    {
        return $this->hasMany(RealisasiTugasJabatan::class);
    }

    public function lemburPeserta()
    {
        return $this->hasMany(LemburPeserta::class);
    }

    public function punishmentKinerja()
    {
        return $this->hasMany(PunishmentKinerja::class);
    }

    public function penggajian()
    {
        return $this->hasMany(Penggajian::class);
    }

    public function gajiPokokIndividu()
    {
        return $this->hasMany(GajiPokokIndividu::class);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCESSOR — JABATAN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Ambil semua jabatan aktif saat ini.
     * Prioritas: pivot jabatan_guru → fallback jabatan_id.
     *
     * @return Collection<Jabatan>
     */
    public function getJabatanAktifAttribute(): Collection
    {
        $viaP = $this->jabatanGuru()->aktif()->with('jabatan')->get();

        if ($viaP->isNotEmpty()) {
            return $viaP->map(fn($jg) => $jg->jabatan)->filter()->values();
        }

        // Fallback ke kolom jabatan_id lama
        if ($this->jabatan_id && $this->jabatan) {
            return collect([$this->jabatan]);
        }

        return collect();
    }

    /**
     * Nama jabatan untuk display UI.
     * Contoh: "Guru Kelas (+1 jabatan)" jika rangkap.
     */
    public function getNamaJabatanDisplayAttribute(): string
    {
        $jabatan = $this->jabatan_aktif;

        if ($jabatan->isEmpty()) return '—';

        $utama = $jabatan->first()?->nama_jabatan ?? '—';
        $total = $jabatan->count();

        return $total > 1
            ? "{$utama} (+" . ($total - 1) . " jabatan)"
            : $utama;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GAJI POKOK — MULTI JABATAN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Hitung TOTAL gaji pokok dari SEMUA jabatan aktif.
     *
     * Prioritas:
     * 1. Override individu total (jabatan_id = NULL)
     * 2. Sum gaji dari semua jabatan aktif
     */
    public function getGajiPokokAktif(): float
    {
        try {
            $override = $this->gajiPokokIndividu()
                ->where('berlaku_mulai', '<=', now())
                ->where(fn($q) => $q->whereNull('berlaku_selesai')
                                    ->orWhere('berlaku_selesai', '>=', now()))
                ->whereNull('jabatan_id')
                ->latest('berlaku_mulai')
                ->first();

            if ($override) return (float) $override->nominal;
        } catch (\Exception $e) {
            // Kolom jabatan_id belum ada — fallback
            $override = $this->gajiPokokIndividu()
                ->where('berlaku_mulai', '<=', now())
                ->where(fn($q) => $q->whereNull('berlaku_selesai')
                                    ->orWhere('berlaku_selesai', '>=', now()))
                ->latest('berlaku_mulai')
                ->first();

            if ($override) return (float) $override->nominal;
        }

        return $this->getDetailGajiPokokPerJabatan()->sum('nominal');
    }

    /**
     * Detail gaji pokok per jabatan.
     * Dipakai PayrollCalculationService dan slip gaji.
     *
     * @return Collection
     */
    public function getDetailGajiPokokPerJabatan(): Collection
    {
        $jabatanAktif = $this->jabatan_aktif;

        // Fallback jika pivot belum ada data
        if ($jabatanAktif->isEmpty() && $this->jabatan_id) {
            $jabatanAktif = collect([$this->jabatan])->filter();
        }

        if ($jabatanAktif->isEmpty()) return collect();

        return $jabatanAktif->map(function ($jabatan) {
            // Override per jabatan
            try {
                $overridePerJabatan = $this->gajiPokokIndividu()
                    ->where('jabatan_id', $jabatan->id)
                    ->where('berlaku_mulai', '<=', now())
                    ->where(fn($q) => $q->whereNull('berlaku_selesai')
                                        ->orWhere('berlaku_selesai', '>=', now()))
                    ->latest('berlaku_mulai')
                    ->first();
            } catch (\Exception $e) {
                $overridePerJabatan = null;
            }

            if ($overridePerJabatan) {
                return [
                    'jabatan_id'   => $jabatan->id,
                    'nama_jabatan' => $jabatan->nama_jabatan,
                    'nominal'      => (float) $overridePerJabatan->nominal,
                    'sumber'       => 'override_individu',
                ];
            }

            $setting = SettingGajiPokok::where('jabatan_id', $jabatan->id)
                ->where('is_aktif', true)
                ->where('berlaku_mulai', '<=', now())
                ->where(fn($q) => $q->whereNull('berlaku_selesai')
                                    ->orWhere('berlaku_selesai', '>=', now()))
                ->latest('berlaku_mulai')
                ->first();

            return [
                'jabatan_id'   => $jabatan->id,
                'nama_jabatan' => $jabatan->nama_jabatan,
                'nominal'      => $setting ? (float) $setting->nominal : 0,
                'sumber'       => $setting ? 'setting_jabatan' : 'tidak_ada',
            ];
        })->filter(fn($d) => $d['nominal'] > 0)->values();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // STATUS KEPEGAWAIAN
    // ═══════════════════════════════════════════════════════════════════════════

    public function getStatusBadgeAttribute(): array
    {
        return RiwayatStatusKepegawaian::badgeStatus(
            $this->status_kepegawaian ?? 'aktif'
        );
    }

    public function isStatusSementara(): bool
    {
        return RiwayatStatusKepegawaian::isSementara(
            $this->status_kepegawaian ?? 'aktif'
        );
    }

    public function isStatusPermanent(): bool
    {
        return RiwayatStatusKepegawaian::isPermanent(
            $this->status_kepegawaian ?? 'aktif'
        );
    }

    public function bisaAktifKembali(): bool
    {
        return !$this->isStatusPermanent();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SCOPE
    // ═══════════════════════════════════════════════════════════════════════════

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeByJabatan($query, int $jabatanId)
    {
        return $query->where('jabatan_id', $jabatanId);
    }

    public function scopeByStatusKepegawaian($query, string $status)
    {
        return $query->where('status_kepegawaian', $status);
    }
}