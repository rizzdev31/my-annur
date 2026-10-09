<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor HP/WhatsApp tamu.
 *
 * Kolomnya NULLABLE meskipun pengisiannya wajib (keputusan user): baris yang
 * sudah terisi sebelum kebijakan ini memang tidak punya nomor, dan tidak boleh
 * dipalsukan hanya demi memenuhi skema. Kewajibannya ditegakkan di
 * BukuTamuService::simpanTamu() — satu-satunya pintu penulisan buku tamu.
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
