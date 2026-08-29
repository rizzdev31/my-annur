<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan "Tidak Mengajar" — mendukung kebijakan baru:
 *   - Mengajar jadwal sendiri TIDAK lagi dibayar vakasi (sudah masuk gaji pokok).
 *   - Guru pengganti tetap dapat vakasi mengajar.
 *   - Guru yang tidak mengajar (digantikan / sesi tidak terlaksana) kena POTONGAN
 *     FLAT per sesi yang bisa diatur admin.
 *
 * Migrasi ini: (1) tambah nilai enum `per_sesi_tidak_mengajar` ke tipe_pemicu
 * (sekaligus memasukkan `per_menit_keterlambatan` yang sudah dipakai kode tapi
 * belum ada di enum), (2) seed 1 baris default (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_potongan')) return;

        DB::statement("
            ALTER TABLE `setting_potongan`
            MODIFY COLUMN `tipe_pemicu`
            ENUM('per_keterlambatan','per_menit_keterlambatan','per_alfa',
                 'per_sesi_tidak_mengajar','per_bulan','persen_gaji','manual')
            NOT NULL DEFAULT 'per_bulan'
        ");

        // Seed default (admin menyesuaikan nominal via menu Setting Potongan)
        $exists = DB::table('setting_potongan')->where('kode', 'TIDAK_MENGAJAR')->exists();
        if (!$exists) {
            DB::table('setting_potongan')->insert([
                'kode'           => 'TIDAK_MENGAJAR',
                'nama'           => 'Potongan Tidak Mengajar',
                'kategori'       => 'absensi',
                'tipe_pemicu'    => 'per_sesi_tidak_mengajar',
                'tipe_nominal'   => 'nominal',
                'nominal'        => 25000,   // Rp 25.000 / sesi — DEFAULT, silakan ubah
                'nominal_maksimal' => null,
                'lingkup'        => 'semua',
                'deskripsi'      => 'Potongan flat per sesi mengajar yang tidak dilaksanakan (digantikan guru pengganti atau tidak terlaksana). Sesuaikan nominal sesuai kebijakan.',
                'tampil_di_slip' => true,
                'is_aktif'       => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting_potongan')) return;

        DB::table('setting_potongan')->where('kode', 'TIDAK_MENGAJAR')->delete();
        // Kembalikan record 'per_sesi_tidak_mengajar' yang tersisa (jika ada) agar aman
        DB::table('setting_potongan')->where('tipe_pemicu', 'per_sesi_tidak_mengajar')
            ->update(['tipe_pemicu' => 'per_bulan', 'is_aktif' => false]);

        DB::statement("
            ALTER TABLE `setting_potongan`
            MODIFY COLUMN `tipe_pemicu`
            ENUM('per_keterlambatan','per_menit_keterlambatan','per_alfa',
                 'per_bulan','persen_gaji','manual')
            NOT NULL DEFAULT 'per_bulan'
        ");
    }
};
