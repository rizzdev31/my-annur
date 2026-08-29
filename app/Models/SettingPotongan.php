<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingPotongan extends Model
{
    protected $table = 'setting_potongan';

    protected $fillable = [
        'nama',
        'kode',
        'kategori',
        'tipe_pemicu',
        'tipe_nominal',
        'nominal',
        'nominal_maksimal',
        'lingkup',
        'jabatan_ids',
        'tenaga_pendidik_ids',
        'deskripsi',
        'tampil_di_slip',
        'is_aktif',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'             => 'float',
            'nominal_maksimal'    => 'float',
            'jabatan_ids'         => 'array',
            'tenaga_pendidik_ids' => 'array',
            'tampil_di_slip'      => 'boolean',
            'is_aktif'            => 'boolean',
        ];
    }

    // ─── Label ────────────────────────────────────────────────────────────────

    public function getKategoriLabelAttribute(): string
    {
        return [
            'absensi'  => 'Absensi (Keterlambatan/Alfa)',
            'wajib'    => 'Potongan Wajib',
            'simpanan' => 'Simpanan / Tabungan',
            'pinjaman' => 'Cicilan Pinjaman',
            'lainnya'  => 'Lainnya',
        ][$this->kategori] ?? $this->kategori;
    }

    public function getTipePemicuLabelAttribute(): string
    {
        return [
            'per_keterlambatan'       => 'Per Kejadian Terlambat',
            'per_menit_keterlambatan' => 'Per Menit Keterlambatan',
            'per_alfa'                => 'Per Hari Alfa',
            'per_sesi_tidak_mengajar' => 'Per Sesi Tidak Mengajar',
            'per_bulan'               => 'Flat Per Bulan',
            'persen_gaji'             => 'Persen dari Gaji Pokok',
            'manual'                  => 'Input Manual',
        ][$this->tipe_pemicu] ?? $this->tipe_pemicu;
    }

    public function getNominalDisplayAttribute(): string
    {
        if ($this->tipe_nominal === 'persen') {
            return $this->nominal . '%';
        }
        return 'Rp ' . number_format($this->nominal, 0, ',', '.');
    }

    // ─── Hitung ───────────────────────────────────────────────────────────────

    /**
     * Hitung nominal potongan.
     *
     * @param float $gajiPokok    Untuk tipe persen_gaji
     * @param int   $kejadian     Jumlah kejadian (terlambat / alfa)
     * @return float
     */
    public function hitungNominal(float $gajiPokok = 0, int $kejadian = 1): float
    {
        $nominal = match ($this->tipe_nominal) {
            'persen' => $gajiPokok * $this->nominal / 100,
            default  => $this->nominal,
        };

        // Terapkan per kejadian untuk absensi
        // per_menit_keterlambatan: $kejadian berisi total menit terlambat
        // per_sesi_tidak_mengajar: $kejadian berisi jumlah sesi tidak mengajar/digantikan
        if (in_array($this->tipe_pemicu, ['per_keterlambatan', 'per_alfa', 'per_menit_keterlambatan', 'per_sesi_tidak_mengajar'])) {
            $nominal = $nominal * $kejadian;
        }

        // Batasi dengan nominal_maksimal jika ada
        if ($this->nominal_maksimal && $nominal > $this->nominal_maksimal) {
            $nominal = $this->nominal_maksimal;
        }

        return (float) $nominal;
    }

    /**
     * Cek apakah potongan berlaku untuk guru tertentu.
     */
    public function berlakuUntukGuru(TenagaPendidik $guru): bool
    {
        return match ($this->lingkup) {
            'semua'        => true,
            'per_jabatan'  => $this->cekJabatan($guru),
            'per_individu' => in_array($guru->id, $this->tenaga_pendidik_ids ?? []),
            'custom'       => $this->cekJabatan($guru) ||
                              in_array($guru->id, $this->tenaga_pendidik_ids ?? []),
            default        => true,
        };
    }

    private function cekJabatan(TenagaPendidik $guru): bool
    {
        $jabatanIds = $this->jabatan_ids ?? [];
        if (empty($jabatanIds)) return false;

        $jabatanAktif = $guru->jabatanGuru()
            ->whereNull('berlaku_selesai')->pluck('jabatan_id')->toArray();

        if (empty($jabatanAktif) && $guru->jabatan_id) {
            $jabatanAktif = [$guru->jabatan_id];
        }

        return !empty(array_intersect($jabatanIds, $jabatanAktif));
    }

    // ─── Relasi ───────────────────────────────────────────────────────────────

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Scope ────────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeByKategori($query, string $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    public function scopeByPemicu($query, string $pemicu)
    {
        return $query->where('tipe_pemicu', $pemicu);
    }

    public function scopeTampilDiSlip($query)
    {
        return $query->where('tampil_di_slip', true);
    }
}