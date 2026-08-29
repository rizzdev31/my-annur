<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah nilai 'izin' ke ENUM status pada absensi_mengajar.
     *
     * Sebelum: ENUM('terlaksana','tidak_terlaksana','pengganti','libur') DEFAULT 'terlaksana'
     * Sesudah : ENUM('terlaksana','tidak_terlaksana','pengganti','libur','izin') DEFAULT 'terlaksana'
     *
     * Status 'izin' digunakan ketika guru memiliki pengajuan izin/cuti yang disetujui
     * dan mengkonfirmasi tugas pengganti via aplikasi Flutter.
     * JP tetap diberikan penuh karena guru memiliki izin resmi yang disetujui admin.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `absensi_mengajar`
            MODIFY COLUMN `status`
            ENUM('terlaksana','tidak_terlaksana','pengganti','libur','izin')
            NOT NULL DEFAULT 'terlaksana'
        ");
    }

    public function down(): void
    {
        // Update record 'izin' → 'tidak_terlaksana' sebelum rollback ENUM
        DB::statement("
            UPDATE `absensi_mengajar`
            SET `status` = 'tidak_terlaksana'
            WHERE `status` = 'izin'
        ");

        DB::statement("
            ALTER TABLE `absensi_mengajar`
            MODIFY COLUMN `status`
            ENUM('terlaksana','tidak_terlaksana','pengganti','libur')
            NOT NULL DEFAULT 'terlaksana'
        ");
    }
};
