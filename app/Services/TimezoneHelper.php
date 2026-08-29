<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * TimezoneHelper
 * 
 * Semua operasi tanggal/waktu di aplikasi ini menggunakan WIB (Asia/Jakarta = UTC+7).
 * Helper ini memastikan konsistensi timezone di seluruh backend.
 */
class TimezoneHelper
{
    const TZ = 'Asia/Jakarta';

    /** Carbon sekarang dalam WIB */
    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /** Carbon hari ini (00:00:00) dalam WIB */
    public static function today(): Carbon
    {
        return Carbon::today(self::TZ);
    }

    /** Parse string tanggal ke Carbon WIB */
    public static function parse(string $date): Carbon
    {
        return Carbon::parse($date, self::TZ);
    }

    /**
     * Nama hari dalam bahasa Inggris dari tanggal WIB (lowercase).
     * Misal: 'senin', 'selasa', dst.
     * 
     * JadwalMengajar menyimpan hari dalam format:
     * senin, selasa, rabu, kamis, jumat, sabtu, ahad
     */
    public static function namaHariInggris(Carbon $tanggal): string
    {
        return strtolower($tanggal->locale('en')->dayName);
    }

    /**
     * Konversi nama hari Inggris → format DB pesantren.
     * JadwalMengajar.hari menggunakan: senin,selasa,...,ahad (bukan sunday)
     */
    public static function normalisasiHari(string $hari): string
    {
        return match (strtolower($hari)) {
            'sunday'    => 'ahad',
            'monday'    => 'senin',
            'tuesday'   => 'selasa',
            'wednesday' => 'rabu',
            'thursday'  => 'kamis',
            'friday'    => 'jumat',
            'saturday'  => 'sabtu',
            default     => strtolower($hari),
        };
    }

    /**
     * Nama hari DB dari Carbon WIB.
     * Return: senin | selasa | rabu | kamis | jumat | sabtu | ahad
     */
    public static function namaHariDB(Carbon $tanggal): string
    {
        return self::normalisasiHari(
            $tanggal->locale('en')->dayName
        );
    }

    /**
     * Parse tanggal dari request Flutter.
     * Flutter mengirim device_date (Y-m-d WIB) sebagai parameter opsional.
     * Jika ada → pakai itu. Jika tidak → pakai today() WIB.
     */
    public static function tanggalDariRequest(?string $deviceDate): Carbon
    {
        if ($deviceDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deviceDate)) {
            return Carbon::parse($deviceDate, self::TZ)->startOfDay();
        }
        return self::today();
    }

    /**
     * Ambil jadwal jam kerja untuk hari ini dengan fallback robust.
     * 
     * Urutan priority:
     * 1. Setting per-hari (jadwal_per_hari[namaHari])
     * 2. Fallback setting global (jam_masuk, jam_pulang global)
     * 3. Return null jika benar-benar tidak ada setting
     */
    public static function getJadwalHariIni(\App\Models\SettingJamKerja $jamKerja, Carbon $tanggal): ?array
    {
        $namaHari = self::namaHariDB($tanggal); // 'senin','rabu', dll

        // Coba ambil jadwal normal
        $jadwal = $jamKerja->getJamUntukHari($namaHari);
        if ($jadwal) return $jadwal;

        // ── Hari libur EKSPLISIT (mis. Ahad dinonaktifkan) → benar-benar libur ──
        // Jangan paksa fallback jam global; kembalikan null agar diperlakukan libur.
        if ($jamKerja->isHariLibur($namaHari)) {
            \Log::info('[JADWAL] Hari libur (jam kerja) — tidak ada jam kerja', compact('namaHari'));
            return null;
        }

        // ── Fallback Layer 2: jam global dari model ──────────────────────────
        // Pakai jam_masuk/jam_pulang global jika:
        // - jadwal_per_hari tidak punya entry untuk hari ini, ATAU
        // - gunakan_jadwal_per_hari = false
        if ($jamKerja->jam_masuk && $jamKerja->jam_pulang) {
            $hariKerja = $jamKerja->hari_kerja ?? [];

            // Hari ini ada di hari_kerja global → pakai global
            if (in_array($namaHari, $hariKerja)) {
                \Log::info('[JADWAL] Menggunakan jam global (hari ada di hari_kerja)', compact('namaHari'));
                return [
                    'jam_masuk'   => $jamKerja->jam_masuk,
                    'jam_pulang'  => $jamKerja->jam_pulang,
                    'toleransi'   => $jamKerja->toleransi_terlambat ?? 15,
                    'lintas_hari' => $jamKerja->isLintasHari($jamKerja->jam_masuk, $jamKerja->jam_pulang),
                    'durasi_menit'=> $jamKerja->total_jam_kerja_sehari ?? 480,
                ];
            }

            // hari_kerja kosong → tidak ada filter hari → pakai global
            if (empty($hariKerja)) {
                \Log::info('[JADWAL] Menggunakan jam global (hari_kerja kosong)', compact('namaHari'));
                return [
                    'jam_masuk'   => $jamKerja->jam_masuk,
                    'jam_pulang'  => $jamKerja->jam_pulang,
                    'toleransi'   => $jamKerja->toleransi_terlambat ?? 15,
                    'lintas_hari' => false,
                    'durasi_menit'=> $jamKerja->total_jam_kerja_sehari ?? 480,
                ];
            }
        }

        // ── Fallback Layer 3: paksa pakai jam global meski hari tidak terdaftar ─
        // Ini terjadi jika:
        //   gunakan_jadwal_per_hari=true + 'kamis' tidak aktif di jadwal_per_hari
        //   + hari_kerja diisi tapi tidak include 'kamis'
        // Daripada return null (validasi skip), gunakan jam global jika ada
        if ($jamKerja->jam_masuk && $jamKerja->jam_pulang) {
            \Log::warning('[JADWAL] Fallback paksa jam global — hari tidak ada di jadwal/hari_kerja', compact('namaHari'));
            return [
                'jam_masuk'   => $jamKerja->jam_masuk,
                'jam_pulang'  => $jamKerja->jam_pulang,
                'toleransi'   => $jamKerja->toleransi_terlambat ?? 15,
                'lintas_hari' => false,
                'durasi_menit'=> $jamKerja->total_jam_kerja_sehari ?? 480,
            ];
        }

        \Log::error('[JADWAL] Tidak ada setting jam kerja yang bisa di-resolve', compact('namaHari'));
        return null;
    }
}