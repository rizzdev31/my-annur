<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sakelar "wajib absen harian" per tenaga pendidik.
 *
 * Guru yang hanya mengajar pada sesi tertentu (mis. pembina ekstrakurikuler)
 * tidak perlu check-in harian; kewajibannya cukup absen per sesi mengajar.
 * Sebelum ini satu-satunya cara adalah mengakali jam kerja (semua hari
 * dinonaktifkan) — rapuh karena guru tanpa setting sendiri otomatis jatuh ke
 * jam kerja default dan langsung dialfakan tiap hari.
 *
 * Default TRUE agar perilaku semua guru yang sudah berjalan tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            $table->boolean('wajib_absen_harian')->default(true)->after('is_mukim');
        });
    }

    public function down(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            $table->dropColumn('wajib_absen_harian');
        });
    }
};
