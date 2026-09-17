<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi level tahsin 13 santri yang berubah salah akibat aturan lama
 * "level santri = level kelas" (Santri::selaraskanLevelTahsin), yang aktif di
 * panel Atur Santri 17 Sep 2026 13:34–14:13 sebelum diganti aturan
 * "pindah kelas tidak mengubah pencapaian".
 *
 * Kejadian 17 Sep: admin menukar kelompok antara Tahsin Putri 1 dan Persiapan
 * Tahfidz 4.
 *
 *  A. 13:43:49 — 9 santri dari Persiapan Tahfidz 3/4/5 (level 6) dipindah ke
 *     Tahsin Putri 1 yang saat itu masih level 4 → level mereka turun ke 4.
 *     Seluruh nilai mereka tercatat di Level 6; dua yang belum bernilai juga
 *     berasal dari kelas Persiapan Tahfidz. Pukul 15:08 Tahsin Putri 1 diubah
 *     ke level 6, tetapi santri tertinggal di level 4 → guru melihat materi
 *     Level 4 di kelas Persiapan Tahfidz. Benar: 6.
 *
 *  B. 13:42:11 — 4 santri dipindah ke Persiapan Tahfidz 4 (level 6) → level
 *     melompat ke 6 tanpa ujian. Nilai mereka di Level 4/5 dan tasnif level
 *     4/5 mereka sedang berjalan; bila lulus, naikLevel dari 6 akan
 *     menganggap mereka lulus Persiapan Tahfidz. Benar: kembali ke level asal.
 *
 * Pengaman: hanya mengubah santri yang levelnya MASIH bernilai hasil bug.
 * Tidak ada penilaian yang tercatat di level salah (nilai terakhir 12–16 Sep).
 */
return new class extends Migration
{
    /** [santri_id => [level_salah, level_benar]] */
    private array $koreksi = [
        // A — kelas Tahsin Putri 1 (level 6), pengampu Haniyyah Afifatu Thohiroh
        21  => [4, 6], 25 => [4, 6], 28 => [4, 6], 29 => [4, 6], 65 => [4, 6],
        70  => [4, 6], 73 => [4, 6], 99 => [4, 6], 114 => [4, 6],
        // B — dipindah ke Persiapan Tahfidz 4, tasnif level asal sedang berjalan
        24  => [6, 4], 26 => [6, 4], 132 => [6, 4], 98 => [6, 5],
    ];

    public function up(): void
    {
        foreach ($this->koreksi as $id => [$salah, $benar]) {
            DB::table('santri')->where('id', $id)->where('tahsin_level', $salah)
                ->update(['tahsin_level' => $benar, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->koreksi as $id => [$salah, $benar]) {
            DB::table('santri')->where('id', $id)->where('tahsin_level', $benar)
                ->update(['tahsin_level' => $salah, 'updated_at' => now()]);
        }
    }
};
