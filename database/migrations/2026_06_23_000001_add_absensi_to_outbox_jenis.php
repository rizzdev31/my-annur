<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai 'absensi' ke enum outbox_laporan.jenis (ADITIF, tidak ubah data lama).
 * Jawaban absensi (telat/alpha) dikirim sebagai pelanggaran dgn kode dari
 * config('controlling.absensi_kode') → fleksibel mengubah kode tanpa ubah kode program.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat','absensi') NOT NULL"
        );
    }

    public function down(): void
    {
        // Kembalikan baris 'absensi' ke 'telat' agar enum lama tetap valid.
        DB::table('outbox_laporan')->where('jenis', 'absensi')->update(['jenis' => 'telat']);
        DB::statement(
            "ALTER TABLE outbox_laporan MODIFY COLUMN jenis "
            . "ENUM('pelanggaran','apresiasi','konselor','telat') NOT NULL"
        );
    }
};
