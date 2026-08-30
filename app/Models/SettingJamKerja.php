<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingJamKerja extends Model
{
    protected $table = 'setting_jam_kerja';

    protected $fillable = [
        'nama',
        // Kolom lama — backward compat (jika gunakan_jadwal_per_hari = false)
        'jam_masuk',
        'jam_pulang',
        'toleransi_terlambat',
        'hari_kerja',
        'total_jam_kerja_sehari',
        // Kolom baru — jadwal fleksibel per hari
        'jadwal_per_hari',
        'gunakan_jadwal_per_hari',
        'is_default',
        'is_aktif',
        'is_template',
        'induk_template_id',
        'tenaga_pendidik_id',
    ];

    protected function casts(): array
    {
        return [
            'hari_kerja'              => 'array',
            'jadwal_per_hari'         => 'array',
            'gunakan_jadwal_per_hari' => 'boolean',
            'is_default'              => 'boolean',
            'is_aktif'                => 'boolean',
            'is_template'             => 'boolean',
        ];
    }

    /** Hanya template (jam kerja bersama) — sembunyikan hasil generate per-guru. */
    public function scopeTemplate($query)
    {
        return $query->where('is_template', true);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public static function getDefault(): ?self
    {
        // Prioritas: yang is_default=true
        $default = static::where('is_default', true)
            ->where('is_aktif', true)
            ->first();

        if ($default) return $default;

        // Fallback: ambil yang is_aktif=true saja (jika belum ada yang diset default)
        // Ini mencegah checkout bebas hanya karena admin belum klik "set default"
        return static::where('is_aktif', true)->first();
    }

    /**
     * Ambil setting jam kerja untuk hari tertentu.
     * Support overnight: jam_pulang < jam_masuk berarti lintas tengah malam.
     *
     * @param string $hari  'senin','selasa',...
     * @return array|null   null = hari libur/tidak bekerja
     */
    public function getJamUntukHari(string $hari): ?array
    {
        $hari = strtolower($hari);

        if ($this->gunakan_jadwal_per_hari && $this->jadwal_per_hari) {
            $config = $this->jadwal_per_hari[$hari] ?? null;

            // Hari ini ada dan aktif di jadwal per-hari → pakai itu
            if ($config && ($config['aktif'] ?? false)) {
                return [
                    'jam_masuk'   => $config['jam_masuk'],
                    'jam_pulang'  => $config['jam_pulang'],
                    'toleransi'   => $config['toleransi'] ?? $this->toleransi_terlambat ?? 15,
                    'lintas_hari' => $this->isLintasHari($config['jam_masuk'], $config['jam_pulang']),
                    'durasi_menit'=> $this->hitungDurasiMenit($config['jam_masuk'], $config['jam_pulang']),
                ];
            }

            // Hari ini KONFIGURASINYA ADA tapi tidak aktif → libur eksplisit.
            // Jadwal per-hari adalah sumber kebenaran: JANGAN fallback ke jam global.
            if ($config !== null) {
                return null;
            }

            // Hari ini TIDAK terdaftar di jadwal_per_hari → baru boleh fallback global
            if ($this->jam_masuk && $this->jam_pulang) {
                $hariKerja = $this->hari_kerja ?? [];
                if (empty($hariKerja) || in_array($hari, $hariKerja)) {
                    return [
                        'jam_masuk'   => $this->jam_masuk,
                        'jam_pulang'  => $this->jam_pulang,
                        'toleransi'   => $this->toleransi_terlambat ?? 15,
                        'lintas_hari' => $this->isLintasHari($this->jam_masuk, $this->jam_pulang),
                        'durasi_menit'=> $this->total_jam_kerja_sehari ?? 480,
                    ];
                }
            }

            // Tidak ada jadwal per-hari NOR global → hari ini libur/tidak kerja
            return null;
        }

        // Mode setting global (gunakan_jadwal_per_hari = false)
        $hariKerja = $this->hari_kerja ?? [];

        // Fix: handle double-encoded JSON (jika hari_kerja masih berupa string setelah cast)
        if (is_string($hariKerja)) {
            $hariKerja = json_decode($hariKerja, true) ?? [];
            // Jika masih string (triple-encoded), decode lagi
            if (is_string($hariKerja)) {
                $hariKerja = json_decode($hariKerja, true) ?? [];
            }
        }

        // Jika hari_kerja diisi → filter; jika kosong → semua hari kerja (berlaku semua hari)
        if (!empty($hariKerja) && !in_array($hari, $hariKerja)) return null;

        // Wajib ada jam_masuk dan jam_pulang di setting global
        if (!$this->jam_masuk || !$this->jam_pulang) return null;

        return [
            'jam_masuk'    => $this->jam_masuk,
            'jam_pulang'   => $this->jam_pulang,
            'toleransi'    => $this->toleransi_terlambat ?? 15,
            'lintas_hari'  => $this->isLintasHari($this->jam_masuk, $this->jam_pulang),
            'durasi_menit' => $this->total_jam_kerja_sehari ?? 480,
        ];
    }

    /**
     * Hitung durasi menit antara jam masuk dan jam pulang.
     * Support overnight: jika pulang < masuk, tambah 24 jam.
     */
    public function hitungDurasiMenit(string $jamMasuk, string $jamPulang): int
    {
        [$hMasuk, $mMasuk]   = array_map('intval', explode(':', $jamMasuk));
        [$hPulang, $mPulang] = array_map('intval', explode(':', $jamPulang));

        $menitMasuk  = $hMasuk  * 60 + $mMasuk;
        $menitPulang = $hPulang * 60 + $mPulang;

        // Overnight: jam pulang di hari berikutnya
        if ($menitPulang <= $menitMasuk) {
            $menitPulang += 24 * 60;
        }

        return $menitPulang - $menitMasuk;
    }

    /**
     * Cek apakah shift ini lintas tengah malam (overnight).
     */
    public function isLintasHari(string $jamMasuk, string $jamPulang): bool
    {
        [$hMasuk, $mMasuk]   = array_map('intval', explode(':', $jamMasuk));
        [$hPulang, $mPulang] = array_map('intval', explode(':', $jamPulang));
        return ($hPulang * 60 + $mPulang) <= ($hMasuk * 60 + $mMasuk);
    }

    /**
     * Cek apakah hari tertentu adalah hari kerja.
     */
    public function isHariKerja(string $hari): bool
    {
        $hari = strtolower($hari);

        if ($this->gunakan_jadwal_per_hari && $this->jadwal_per_hari) {
            return (bool) ($this->jadwal_per_hari[$hari]['aktif'] ?? false);
        }

        return in_array($hari, $this->hari_kerja ?? []);
    }

    /**
     * Apakah hari ini LIBUR secara eksplisit (bukan sekadar belum terkonfigurasi)?
     * - Mode per-hari: konfigurasi hari ADA tapi tidak aktif → libur.
     *   Jika hari tidak ada di konfigurasi → bergantung hari_kerja global.
     * - Mode global: libur jika hari_kerja diisi tapi tidak memuat hari ini.
     * (hari_kerja kosong = berlaku semua hari → BUKAN libur)
     */
    public function isHariLibur(string $hari): bool
    {
        $hari = strtolower($hari);

        if ($this->gunakan_jadwal_per_hari && $this->jadwal_per_hari) {
            $config = $this->jadwal_per_hari[$hari] ?? null;
            if ($config !== null) {
                return ! ($config['aktif'] ?? false);
            }
            $hk = $this->hari_kerja ?? [];
            return !empty($hk) && !in_array($hari, $hk);
        }

        $hk = $this->hari_kerja ?? [];
        return !empty($hk) && !in_array($hari, $hk);
    }

    /**
     * Ambil ringkasan hari kerja aktif beserta jam-nya.
     */
    public function getRingkasanJadwalAttribute(): array
    {
        $hariAll = ['senin','selasa','rabu','kamis','jumat','sabtu','ahad'];
        $result  = [];

        foreach ($hariAll as $hari) {
            $jam = $this->getJamUntukHari($hari);
            if ($jam) {
                $durasi = $jam['durasi_menit'];
                $result[$hari] = [
                    'aktif'       => true,
                    'jam_masuk'   => $jam['jam_masuk'],
                    'jam_pulang'  => $jam['jam_pulang'],
                    'lintas_hari' => $jam['lintas_hari'],
                    'durasi_jam'  => round($durasi / 60, 1),
                    'label'       => $jam['jam_masuk'] . ' – ' . $jam['jam_pulang']
                                     . ($jam['lintas_hari'] ? ' (+1)' : ''),
                ];
            } else {
                $result[$hari] = ['aktif' => false];
            }
        }

        return $result;
    }

    /**
     * Total jam kerja per minggu (menit).
     */
    public function getTotalMenitPerMingguAttribute(): int
    {
        $hariAll = ['senin','selasa','rabu','kamis','jumat','sabtu','ahad'];
        $total   = 0;
        foreach ($hariAll as $hari) {
            $jam = $this->getJamUntukHari($hari);
            if ($jam) $total += $jam['durasi_menit'];
        }
        return $total;
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }
}