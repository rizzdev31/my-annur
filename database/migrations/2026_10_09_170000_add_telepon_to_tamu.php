<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP/WhatsApp tamu.
 *
 * Nullable dan TIDAK wajib: tautan kegiatan yang sudah beredar tetap sama, dan
 * tamu yang enggan memberi nomor tetap bisa mencatatkan kehadiran — nomor hanya
 * pelengkap, sedangkan email yang menjadi tumpuan pengiriman notulensi.
 *
 * Disimpan ternormalkan (hanya angka, berawalan kode negara) supaya tautan
 * wa.me di halaman admin selalu bisa dibentuk tanpa menebak format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tamu', function (Blueprint $t) {
            $t->string('telepon', 24)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('tamu', function (Blueprint $t) {
            $t->dropColumn('telepon');
        });
    }
};
