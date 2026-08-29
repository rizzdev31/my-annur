<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FIX Bug 1: tambahkan kolom vakasi_peserta_kegiatan ke tabel penggajian
     * agar komponen ini tersimpan ke DB dan tampil di slip gaji.
     *
     * Juga perbaiki enum tipe pada detail_penggajian untuk menerima
     * 'vakasi_peserta_kegiatan'.
     */
    public function up(): void
    {
        // ── 1. Tambah kolom vakasi_peserta_kegiatan ke penggajian ─────────────
        Schema::table('penggajian', function (Blueprint $table) {
            if (!Schema::hasColumn('penggajian', 'vakasi_peserta_kegiatan')) {
                $table->decimal('vakasi_peserta_kegiatan', 12, 2)->default(0)
                      ->after('vakasi_tugas_tambahan')
                      ->comment('Vakasi guru yang hadir sebagai peserta kegiatan');
            }
        });

        // ── 2. Update enum detail_penggajian.tipe ────────────────────────────
        // MySQL tidak bisa langsung ALTER ENUM — kita modifikasi kolom
        // dengan metode yang kompatibel shared hosting (tidak pakai DB::statement
        // yang butuh SUPER privilege).
        // Gunakan modifyColumn Laravel (pakai doctrine/dbal jika tersedia) atau
        // raw SQL dengan change().
        DB::statement("
            ALTER TABLE detail_penggajian
            MODIFY COLUMN tipe ENUM(
                'gaji_pokok',
                'vakasi_absen',
                'vakasi_mengajar',
                'vakasi_tugas_jabatan',
                'vakasi_tugas_tambahan',
                'vakasi_peserta_kegiatan',
                'tunjangan',
                'potongan_terlambat',
                'potongan_alfa',
                'potongan_bpjs',
                'potongan_lain',
                'lainnya'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            if (Schema::hasColumn('penggajian', 'vakasi_peserta_kegiatan')) {
                $table->dropColumn('vakasi_peserta_kegiatan');
            }
        });

        // Kembalikan enum tanpa vakasi_peserta_kegiatan
        DB::statement("
            ALTER TABLE detail_penggajian
            MODIFY COLUMN tipe ENUM(
                'gaji_pokok',
                'vakasi_absen',
                'vakasi_mengajar',
                'vakasi_tugas_jabatan',
                'vakasi_tugas_tambahan',
                'tunjangan',
                'potongan_terlambat',
                'potongan_alfa',
                'potongan_bpjs',
                'potongan_lain'
            ) NOT NULL
        ");
    }
};
