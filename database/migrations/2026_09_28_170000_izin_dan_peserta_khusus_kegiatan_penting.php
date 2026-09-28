<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan wajib guru: status IZIN + peserta khusus per kegiatan.
 *
 * (1) Dulu status hanya hadir/tidak_hadir, jadi guru yang berhalangan sah
 *     tercatat "tidak hadir" dan rasio kedisiplinannya terpotong. Status `izin`
 *     bersifat NETRAL: dikeluarkan dari penyebut rasio (tidak menolong, tidak
 *     menghukum). Default kolom dihapus supaya tidak ada tanda absen yang lahir
 *     dari data kosong — status harus lahir dari keputusan guru piket.
 *
 * (2) Sasaran mukim/non_mukim dulu saringan mutlak, padahal ada guru mukim yang
 *     ikut kegiatan non-mukim (dan sebaliknya guru yang tidak seharusnya wajib).
 *     Tabel peserta khusus menambah/mengecualikan guru tertentu per kegiatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE absensi_kegiatan_penting
            MODIFY status ENUM('hadir','tidak_hadir','izin') NULL DEFAULT NULL");

        Schema::table('absensi_kegiatan_penting', function (Blueprint $table) {
            // piket = ditandai guru piket · perizinan = ikut izin yang sudah
            // disetujui · ad_hoc = peserta di luar daftar wajib yang ikut hadir.
            $table->enum('sumber', ['piket', 'perizinan', 'ad_hoc'])->nullable()->after('status');
        });

        Schema::create('kegiatan_penting_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_penting_id')->constrained('kegiatan_penting')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
            // tambahan     = wajib ikut walau di luar sasaran
            // dikecualikan = tidak wajib walau termasuk sasaran
            $table->enum('mode', ['tambahan', 'dikecualikan']);
            $table->string('catatan', 200)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kegiatan_penting_id', 'tenaga_pendidik_id'], 'uniq_kegiatan_peserta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_penting_peserta');
        Schema::table('absensi_kegiatan_penting', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
        DB::statement("UPDATE absensi_kegiatan_penting SET status = 'tidak_hadir' WHERE status IS NULL OR status = 'izin'");
        DB::statement("ALTER TABLE absensi_kegiatan_penting
            MODIFY status ENUM('hadir','tidak_hadir') NOT NULL DEFAULT 'tidak_hadir'");
    }
};
