<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tahfidz — program Qur'an santri (tahsin | tahfidz).
 * tahsin yang lulus level 5 → pindah ke program tahfidz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            $table->enum('program_quran', ['tahsin', 'tahfidz'])->nullable()->after('tahsin_level')
                  ->comment('Program Al-Quran santri: tahsin atau tahfidz');
        });
    }

    public function down(): void
    {
        Schema::table('santri', function (Blueprint $table) {
            $table->dropColumn('program_quran');
        });
    }
};
