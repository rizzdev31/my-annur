<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Absensi santri: tambah status 'izin' & 'sakit' (sebelumnya hanya hadir/telat/alpha).
 * Alasan: guru bingung saat santri izin/sakit karena tak ada opsinya — terpaksa
 * ditandai Alpha (salah). Kini roster juga auto-terisi 'izin' bila santri punya
 * perizinan (izin_santri) yang sudah DISETUJUI mencakup tanggal sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE absensi_santri MODIFY status "
            . "ENUM('hadir','telat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir'"
        );
    }

    public function down(): void
    {
        // Normalkan baris baru agar tak menyalahi enum lama sebelum menciut.
        DB::table('absensi_santri')->whereIn('status', ['izin', 'sakit'])->update(['status' => 'hadir']);
        DB::statement(
            "ALTER TABLE absensi_santri MODIFY status "
            . "ENUM('hadir','telat','alpha') NOT NULL DEFAULT 'hadir'"
        );
    }
};
