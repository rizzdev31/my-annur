<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OTOMATISASI TUGAS TAMBAHAN DARI DINAS LUAR.
 *
 * Dinas luar adalah satu-satunya pengajuan yang isinya memang PEKERJAAN: guru
 * pergi atas perintah pesantren dan mengerjakan sesuatu di sana. Sakit, izin,
 * dan cuti tidak demikian. Karena itu otomatisasi ini dibatasi pada kategori
 * `dinas`, lewat sakelar per jenis supaya admin tetap bisa mematikannya tanpa
 * ubahan kode — dan supaya tidak ada yang kaget bila kelak jenis dinas lain
 * ditambahkan.
 *
 * Tiga kolom:
 *  - `setting_jenis_pengajuan.buat_tugas_tambahan` — sakelarnya;
 *  - `pengajuan_izin.tugas_tambahan_id` & `penugasan_id` — tautan ke tugas yang
 *    terbit, supaya otomatisasinya IDEMPOTEN (satu pengajuan paling banyak satu
 *    tugas, berapa kali pun prosesnya terulang) dan bisa dibatalkan kembali.
 *  - enum `setting_vakasi.tipe_aktivitas` + `dinas_luar`, agar nominalnya diatur
 *    di Setting Vakasi seperti tasmi'/tasnif/jaga ujian, bukan ditanam di kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
            $t->boolean('buat_tugas_tambahan')->default(false)->after('pengaruh_gaji')
              ->comment('Terbitkan tugas tambahan otomatis saat pengajuan disetujui');
        });

        Schema::table('pengajuan_izin', function (Blueprint $t) {
            $t->foreignId('tugas_tambahan_id')->nullable()->after('status_kepegawaian_diupdate')
              ->constrained('tugas_tambahan')->nullOnDelete();
            $t->foreignId('penugasan_id')->nullable()->after('tugas_tambahan_id')
              ->constrained('penugasan_tambahan')->nullOnDelete();
        });

        DB::statement("ALTER TABLE setting_vakasi MODIFY tipe_aktivitas
            ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur',
                 'piket','tasmi','tasnif','ekstrakurikuler','jaga_ujian','dinas_luar') NOT NULL");

        // Nyalakan untuk jenis berkategori dinas — itulah permintaannya.
        DB::table('setting_jenis_pengajuan')->where('kategori', 'dinas')
            ->update(['buat_tugas_tambahan' => true]);
    }

    public function down(): void
    {
        Schema::table('pengajuan_izin', function (Blueprint $t) {
            $t->dropForeign(['tugas_tambahan_id']);
            $t->dropForeign(['penugasan_id']);
            $t->dropColumn(['tugas_tambahan_id', 'penugasan_id']);
        });

        Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
            $t->dropColumn('buat_tugas_tambahan');
        });

        DB::statement("DELETE FROM setting_vakasi WHERE tipe_aktivitas = 'dinas_luar'");
        DB::statement("ALTER TABLE setting_vakasi MODIFY tipe_aktivitas
            ENUM('absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan','lembur',
                 'piket','tasmi','tasnif','ekstrakurikuler','jaga_ujian') NOT NULL");
    }
};
