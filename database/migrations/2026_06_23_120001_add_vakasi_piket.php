<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vakasi Piket (ADITIF):
 *  - tambah nilai 'piket' ke enum setting_vakasi.tipe_aktivitas (nominal diatur admin di Setting Vakasi).
 *  - tambah kolom penggajian.vakasi_piket untuk komponen slip.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas "
            . "ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur','piket') NOT NULL"
        );

        Schema::table('penggajian', function (Blueprint $t) {
            $t->decimal('vakasi_piket', 12, 2)->default(0)->after('vakasi_lembur');
        });
    }

    public function down(): void
    {
        Schema::table('penggajian', fn(Blueprint $t) => $t->dropColumn('vakasi_piket'));
        DB::table('setting_vakasi')->where('tipe_aktivitas', 'piket')->delete();
        DB::statement(
            "ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas "
            . "ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur') NOT NULL"
        );
    }
};
