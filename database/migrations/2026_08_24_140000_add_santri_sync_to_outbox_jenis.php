<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai 'santri_sync' ke enum outbox_laporan.jenis (ADITIF).
 * Dipakai untuk sinkronisasi identitas & kelas santri Smart → RamahAnak
 * (Smart = master; kirim upsert by NISN). Lihat docs/PRD-Sync-Santri-RamahAnak.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat','absensi','santri_sync') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('outbox_laporan')->where('jenis', 'santri_sync')->delete();
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat','absensi') NOT NULL"
        );
    }
};
