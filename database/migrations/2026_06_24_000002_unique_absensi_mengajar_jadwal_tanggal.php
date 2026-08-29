<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cegah duplikat sesi: 1 jadwal mengajar hanya boleh 1 absensi per tanggal.
 * Menutup celah race saat guru & guru piket mengisi bersamaan (handoff).
 * Aman: sudah diverifikasi 0 baris duplikat sebelum penerapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $t) {
            $t->unique(['jadwal_mengajar_id', 'tanggal'], 'absensi_mengajar_jadwal_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $t) {
            $t->dropUnique('absensi_mengajar_jadwal_tanggal_unique');
        });
    }
};
