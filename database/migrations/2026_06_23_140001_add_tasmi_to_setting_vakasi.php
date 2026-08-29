<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah tipe 'tasmi' ke enum setting_vakasi.tipe_aktivitas (ADITIF).
 * Nominal vakasi tasmi kini diatur di Setting Vakasi & dipakai otomatis saat
 * pengampu menunjuk penguji (tugas tambahan tasmi).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas "
            . "ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur','piket','tasmi') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('setting_vakasi')->where('tipe_aktivitas', 'tasmi')->delete();
        DB::statement(
            "ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas "
            . "ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur','piket') NOT NULL"
        );
    }
};
