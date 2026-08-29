<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `notifikasi.tipe` semula ENUM terbatas (absensi/tugas_baru/…) sehingga tiap
 * jenis notifikasi baru (mis. reminder_absensi) tertolak/terpotong. Diubah ke
 * VARCHAR agar sistem notifikasi bisa diperluas per-fitur tanpa migrasi enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifikasi')) return;
        DB::statement("ALTER TABLE `notifikasi` MODIFY `tipe` VARCHAR(50) NOT NULL DEFAULT 'pengumuman'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifikasi')) return;
        // Nilai di luar daftar lama dinormalkan agar aman kembali ke ENUM.
        DB::table('notifikasi')->whereNotIn('tipe', [
            'absensi','tugas_baru','tugas_update','penggajian','koreksi','pengumuman',
        ])->update(['tipe' => 'pengumuman']);
        DB::statement("ALTER TABLE `notifikasi` MODIFY `tipe`
            ENUM('absensi','tugas_baru','tugas_update','penggajian','koreksi','pengumuman')
            NOT NULL DEFAULT 'pengumuman'");
    }
};
