<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah 'guru_sync' ke enum outbox_laporan.jenis (ADITIF).
 * Sinkron identitas guru (tenaga_pendidik) Smart → RamahAnak by NIP.
 * Smart = master; RA role 'tenaga_pendidik' saja (guru_bk milik RA).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat','absensi','santri_sync','guru_sync') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('outbox_laporan')->where('jenis', 'guru_sync')->delete();
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat','absensi','santri_sync') NOT NULL"
        );
    }
};
