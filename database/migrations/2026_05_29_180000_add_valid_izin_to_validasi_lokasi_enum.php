<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah nilai 'valid_izin' ke ENUM absensi_harian.validasi_lokasi.
     *
     * LokasiAbsensiService mengembalikan tipe_validasi 'valid_izin' saat guru
     * memiliki izin aktif + setting izinkan_izin_aktif. Nilai ini sebelumnya
     * TIDAK ada di ENUM → menyebabkan "Data truncated for column 'validasi_lokasi'".
     *
     * Catatan: alur check-in kini memblokir guru izin (non-dinas), jadi nilai ini
     * jarang tersimpan — tapi ditambahkan agar konsisten & aman dari error.
     */
    public function up(): void
    {
        // Saat migrate:fresh, migrasi ini tersortir SEBELUM kolom validasi_lokasi
        // dibuat (file pembuat kolom tanpa prefix tanggal → tersortir akhir).
        // Kolom belum ada → skip. Pada fresh kolom dibuat sebagai string (permisif,
        // menerima 'valid_izin' dll) sehingga penyempitan ke ENUM ini tak wajib.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('absensi_harian', 'validasi_lokasi')) {
            return;
        }

        DB::statement("
            ALTER TABLE `absensi_harian`
            MODIFY COLUMN `validasi_lokasi`
            ENUM('valid_koordinat','valid_wifi','valid_dinas_luar','valid_izin','invalid','bypass_admin','tidak_diperiksa')
            NOT NULL DEFAULT 'tidak_diperiksa'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE `absensi_harian` SET `validasi_lokasi` = 'tidak_diperiksa'
            WHERE `validasi_lokasi` = 'valid_izin'
        ");
        DB::statement("
            ALTER TABLE `absensi_harian`
            MODIFY COLUMN `validasi_lokasi`
            ENUM('valid_koordinat','valid_wifi','valid_dinas_luar','invalid','bypass_admin','tidak_diperiksa')
            NOT NULL DEFAULT 'tidak_diperiksa'
        ");
    }
};
