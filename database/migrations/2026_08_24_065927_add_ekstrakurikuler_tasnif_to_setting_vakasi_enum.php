<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Perluas enum setting_vakasi.tipe_aktivitas: +tasnif, +ekstrakurikuler. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas
            ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur','piket','tasmi','tasnif','ekstrakurikuler') NOT NULL");
        DB::statement("ALTER TABLE setting_vakasi MODIFY COLUMN satuan
            ENUM('per_hari','per_jp','per_tugas','per_jam','per_bulan','per_pertemuan') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas
            ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur','piket','tasmi') NOT NULL");
    }
};
