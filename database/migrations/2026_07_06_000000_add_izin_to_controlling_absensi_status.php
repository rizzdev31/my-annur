<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADDITIVE: tambah nilai enum 'izin' pada controlling_absensi.status.
 * Dipakai untuk santri yang sedang IZIN (disetujui) — termasuk izin pulang
 * Smart Health — agar tidak di-Alpha di Smart Controlling (tercatat "izin").
 * Tidak menghapus/mengubah nilai lama; hanya menambah opsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE controlling_absensi MODIFY status ENUM('hadir','telat','alpha','izin') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE controlling_absensi MODIFY status ENUM('hadir','telat','alpha') NOT NULL");
    }
};
