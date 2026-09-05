<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Kebijakan waktu absen mengajar — SUMBER TUNGGAL.
 *
 * Guru (dan guru pengganti) diberi TENGGANG setelah jam_selesai jadwal untuk
 * menyelesaikan jurnal: mengajar sampai bel, lalu memotret bukti & mengisi
 * materi butuh beberapa menit. Tanpa tenggang, guru yang benar-benar mengajar
 * penuh bisa kehilangan seluruh JP hanya karena upload molor beberapa menit.
 *
 * CATATAN: sengaja TIDAK dibaca dari `setting_vakasi.batas_grace_menit`.
 * Kolom itu terisi 60 di SEMUA baris sebagai nilai bawaan migration (bukan
 * kebijakan yang pernah diputuskan), sehingga membacanya akan diam-diam
 * memberlakukan 60 menit. Bila kelak tenggang ingin dibuat dapat diatur admin,
 * ubah di sini satu tempat.
 *
 * Berlaku SAMA untuk guru reguler dan guru pengganti agar tidak ada
 * ketimpangan perlakuan.
 */
class KebijakanMengajar
{
    /** Tenggang mengisi jurnal setelah jam_selesai jadwal (menit). */
    public const GRACE_MENIT = 15;

    /** Batas akhir absen/jurnal masih dihitung penuh = jam_selesai + tenggang. */
    public static function batasAbsen(Carbon $jamSelesai): Carbon
    {
        return $jamSelesai->copy()->addMinutes(self::GRACE_MENIT);
    }

    /** Batas akhir untuk satu sesi pada tanggal tertentu. */
    public static function batasAbsenSesi(string $tanggal, string $jamSelesai): Carbon
    {
        return self::batasAbsen(
            Carbon::parse($tanggal . ' ' . $jamSelesai, TimezoneHelper::TZ)
        );
    }

    /**
     * Apakah sesi sudah lewat tenggang (JP hangus / boleh ditandai terlewat)?
     * $sekarang default = waktu sekarang WIB.
     */
    public static function lewatTenggang(string $tanggal, string $jamSelesai, ?Carbon $sekarang = null): bool
    {
        $sekarang ??= TimezoneHelper::now();

        return $sekarang->gt(self::batasAbsenSesi($tanggal, $jamSelesai));
    }
}
