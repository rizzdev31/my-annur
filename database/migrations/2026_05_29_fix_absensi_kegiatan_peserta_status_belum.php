<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambahkan nilai 'belum' ke ENUM status_kehadiran
     * dan ubah DEFAULT menjadi 'belum' (bukan 'hadir').
     *
     * Sebelumnya: ENUM('hadir','izin','alfa','terlambat') DEFAULT 'hadir'
     * Sesudahnya : ENUM('belum','hadir','terlambat','izin','alfa') DEFAULT 'belum'
     */
    public function up(): void
    {
        // Saat migrate:fresh, migrasi ini tersortir SEBELUM tabel dibuat
        // (file create tanpa prefix tanggal). Tabel fresh sudah memakai enum final
        // dengan default 'belum' → ALTER ini tidak diperlukan, aman di-skip.
        if (!\Illuminate\Support\Facades\Schema::hasTable('absensi_kegiatan_peserta')) {
            return;
        }

        DB::statement("
            ALTER TABLE `absensi_kegiatan_peserta`
            MODIFY COLUMN `status_kehadiran`
            ENUM('belum','hadir','terlambat','izin','alfa')
            NOT NULL DEFAULT 'belum'
        ");
    }

    public function down(): void
    {
        // Kembalikan ke definisi semula (tanpa 'belum', default 'hadir')
        // Baris yang masih 'belum' akan di-set ke 'hadir' sebelum rollback
        DB::statement("
            UPDATE `absensi_kegiatan_peserta`
            SET `status_kehadiran` = 'hadir'
            WHERE `status_kehadiran` = 'belum'
        ");

        DB::statement("
            ALTER TABLE `absensi_kegiatan_peserta`
            MODIFY COLUMN `status_kehadiran`
            ENUM('hadir','terlambat','izin','alfa')
            NOT NULL DEFAULT 'hadir'
        ");
    }
};
