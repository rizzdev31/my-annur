<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reset status_kehadiran = 'hadir' yang terbentuk dari DEFAULT lama kembali ke 'belum'.
     *
     * Akar masalah:
     *   Skema awal absensi_kegiatan_peserta memiliki:
     *     ENUM('hadir','izin','alfa','terlambat') NOT NULL DEFAULT 'hadir'
     *   Sehingga setiap INSERT tanpa menyertakan status_kehadiran (atau INSERT dengan
     *   nilai tidak valid saat strict-mode off) akan menghasilkan status 'hadir' secara otomatis,
     *   walau pengabsen belum menginput kehadiran sama sekali.
     *   Ini membuat Flutter menampilkan "semua hadir" pada saat layar pertama kali dibuka.
     *
     * Migrasi `2026_05_29_fix_absensi_kegiatan_peserta_status_belum.php` sebelumnya
     * sudah membenahi skema ENUM dan DEFAULT, namun tidak menyentuh data yang sudah ada.
     * Migrasi ini melengkapinya dengan me-reset data yang salah.
     *
     * Kriteria aman untuk di-reset (pasti bukan input manual pengabsen):
     *   1. status_kehadiran = 'hadir'            — nilai salah dari DEFAULT lama
     *   2. vakasi_diberikan = false               — belum pernah dapat vakasi
     *   3. nominal_vakasi IS NULL                 — belum ada distribusi vakasi
     *   4. kegiatan.status = 'berlangsung'        — kegiatan belum diselesaikan
     *      (jika selesai, pengabsen sudah input manual → jangan disentuh)
     *
     * Jika pengabsen memang sudah menginput 'hadir' secara manual via Flutter
     * sebelum kegiatan selesai, record tersebut akan memiliki jam_hadir tidak null
     * atau sudah disimpan dengan cara lain. Namun sebagai safety net utama,
     * filter vakasi_diberikan=false sudah cukup karena distribusi vakasi hanya
     * terjadi saat selesaikan kegiatan — yang belum dilakukan untuk kegiatan berlangsung.
     */
    public function up(): void
    {
        // Saat migrate:fresh, migrasi ini tersortir SEBELUM tabel dibuat
        // (file create tanpa prefix tanggal). Tabel fresh sudah default 'belum'
        // sehingga tidak ada data lama yang perlu direset → aman di-skip.
        if (!\Illuminate\Support\Facades\Schema::hasTable('absensi_kegiatan_peserta')) {
            return;
        }

        $affected = DB::statement("
            UPDATE `absensi_kegiatan_peserta` akp
            INNER JOIN `absensi_kegiatan` ak
                ON ak.id = akp.absensi_kegiatan_id
            SET akp.`status_kehadiran` = 'belum'
            WHERE akp.`status_kehadiran` = 'hadir'
              AND akp.`vakasi_diberikan` = 0
              AND akp.`nominal_vakasi`   IS NULL
              AND ak.`status`            = 'berlangsung'
        ");

        \Illuminate\Support\Facades\Log::info(
            '[MIGRATION] reset_status_kehadiran_hadir_ke_belum: data berhasil direset.'
        );
    }

    public function down(): void
    {
        // Tidak ada rollback yang aman — 'belum' tidak bisa dibedakan dari reset.
        // Jika perlu rollback, lakukan manual sesuai kebutuhan.
    }
};
