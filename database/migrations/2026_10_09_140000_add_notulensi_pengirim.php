<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TAHAP 2 BUKU TAMU — pengiriman email.
 *
 * Tiga hal:
 *  1. Jejak siapa yang menekan "Kirim notulensi". Isinya terbang ke puluhan
 *     alamat luar, jadi penanggung jawabnya harus tercatat.
 *  2. Kolom tersendiri untuk email KONFIRMASI. Tidak boleh memakai
 *     `email_status` yang sudah ada — kolom itu melacak notulensi; kalau
 *     dipakai bersama, konfirmasi yang terkirim akan membuat notulensi
 *     tampak sudah terkirim padahal belum.
 *  3. Status `duplikat`. Satu alamat boleh mengisi dua kali di acara yang sama
 *     (keputusan user), tapi notulensi tidak dikirim dua kali ke alamat yang
 *     sama. Baris kedua ditandai `duplikat` — bukan `terkirim`, supaya laporan
 *     tidak mengaku mengirim sesuatu yang tidak dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_tamu', function (Blueprint $t) {
            $t->foreignId('notulensi_dikirim_oleh')->nullable()->after('notulensi_dikirim_pada')
              ->constrained('users')->nullOnDelete();
        });

        Schema::table('tamu', function (Blueprint $t) {
            $t->timestamp('konfirmasi_terkirim_pada')->nullable()->after('email_error');
            $t->string('konfirmasi_error', 255)->nullable()->after('konfirmasi_terkirim_pada');
        });

        DB::statement("ALTER TABLE tamu MODIFY email_status
            ENUM('belum','menunggu','terkirim','gagal','duplikat') NOT NULL DEFAULT 'belum'");
    }

    public function down(): void
    {
        DB::statement("UPDATE tamu SET email_status = 'belum' WHERE email_status = 'duplikat'");
        DB::statement("ALTER TABLE tamu MODIFY email_status
            ENUM('belum','menunggu','terkirim','gagal') NOT NULL DEFAULT 'belum'");

        Schema::table('tamu', function (Blueprint $t) {
            $t->dropColumn(['konfirmasi_terkirim_pada', 'konfirmasi_error']);
        });

        Schema::table('kegiatan_tamu', function (Blueprint $t) {
            $t->dropForeign(['notulensi_dikirim_oleh']);
            $t->dropColumn('notulensi_dikirim_oleh');
        });
    }
};
