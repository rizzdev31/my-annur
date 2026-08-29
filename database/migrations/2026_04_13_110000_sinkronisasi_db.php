<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration sinkronisasi — menyesuaikan struktur DB yang sudah ada
 * dengan pembaruan terbaru. Tidak membuat tabel dari awal.
 *
 * Tabel yang SUDAH ADA dan tidak perlu dibuat ulang:
 *   - setting_lokasi_absensi   (sudah ada, perlu tambah 2 kolom)
 *   - tenaga_pendidik_lokasi   (sudah ada lengkap, skip)
 *   - koreksi_absensi          (sudah ada, perlu update enum + tambah 2 kolom)
 *   - absensi_harian           (sudah ada lengkap, skip)
 *   - setting_potongan         (sudah ada lengkap, skip)
 *   - hari_libur               (sudah ada lengkap, skip)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // 1. setting_lokasi_absensi
        //    Tambah: lingkup (global|per_user), izinkan_izin_aktif
        // ══════════════════════════════════════════════════════════════════════
        Schema::table('setting_lokasi_absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('setting_lokasi_absensi', 'lingkup')) {
                $table->enum('lingkup', ['global', 'per_user'])
                    ->default('global')
                    ->after('is_aktif')
                    ->comment('global=berlaku semua guru, per_user=hanya guru yang di-assign');
            }

            if (!Schema::hasColumn('setting_lokasi_absensi', 'izinkan_izin_aktif')) {
                $table->boolean('izinkan_izin_aktif')
                    ->default(true)
                    ->after('izinkan_dinas_luar')
                    ->comment('Guru yg punya izin disetujui boleh absen dari mana saja');
            }
        });

        // ══════════════════════════════════════════════════════════════════════
        // 2. koreksi_absensi
        //    DB punya: tipe_absensi enum('absen_harian','absen_mengajar')
        //    Kita butuh: enum('harian','mengajar','tugas_jabatan','tugas_tambahan')
        //    + tambah: realisasi_tugas_id, penugasan_tambahan_id
        // ══════════════════════════════════════════════════════════════════════
        Schema::table('koreksi_absensi', function (Blueprint $table) {
            // Ubah enum tipe_absensi — MariaDB/MySQL perlu MODIFY COLUMN
            // Gunakan raw statement agar aman di semua versi
        });

        // Ubah enum tipe_absensi dengan raw SQL (lebih reliable dari Blueprint)
        DB::statement("
            ALTER TABLE `koreksi_absensi`
            MODIFY COLUMN `tipe_absensi`
            ENUM('harian','mengajar','tugas_jabatan','tugas_tambahan')
            NOT NULL DEFAULT 'harian'
            COMMENT 'harian=absensi harian, mengajar=absensi mengajar, tugas_jabatan=realisasi tugas jabatan, tugas_tambahan=penugasan tambahan'
        ");

        Schema::table('koreksi_absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('koreksi_absensi', 'realisasi_tugas_id')) {
                $table->unsignedBigInteger('realisasi_tugas_id')
                    ->nullable()
                    ->after('absensi_mengajar_id')
                    ->comment('FK ke realisasi_tugas_jabatan');
            }

            if (!Schema::hasColumn('koreksi_absensi', 'penugasan_tambahan_id')) {
                $table->unsignedBigInteger('penugasan_tambahan_id')
                    ->nullable()
                    ->after('realisasi_tugas_id')
                    ->comment('FK ke penugasan_tambahan');
            }
        });

        // Index untuk performa query
        try {
            DB::statement("ALTER TABLE `koreksi_absensi` ADD INDEX `koreksi_tipe_idx` (`tipe_absensi`)");
        } catch (\Exception $e) {
            // Index sudah ada, skip
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function down(): void
    {
        // Rollback koreksi_absensi
        DB::statement("
            ALTER TABLE `koreksi_absensi`
            MODIFY COLUMN `tipe_absensi`
            ENUM('absen_harian','absen_mengajar')
            NOT NULL
        ");

        Schema::table('koreksi_absensi', function (Blueprint $table) {
            $table->dropColumn(['realisasi_tugas_id', 'penugasan_tambahan_id']);
        });

        // Rollback setting_lokasi_absensi
        Schema::table('setting_lokasi_absensi', function (Blueprint $table) {
            $table->dropColumn(['lingkup', 'izinkan_izin_aktif']);
        });
    }
};