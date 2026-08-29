<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan surah selesai dari ziyadah terakhir agar murojaah wajib bisa
 * auto-mengikuti rentang penuh hafalan baru terakhir (lintas surah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hafalan_santri', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_surah_selesai')->nullable()->after('last_surah');
        });
    }

    public function down(): void
    {
        Schema::table('hafalan_santri', function (Blueprint $table) {
            $table->dropColumn('last_surah_selesai');
        });
    }
};
