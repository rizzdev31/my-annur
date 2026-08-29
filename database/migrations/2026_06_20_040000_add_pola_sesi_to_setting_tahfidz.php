<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Tahfidz — pola sesi & jam untuk generator jadwal.
 * Default sesuai pola pesantren: Sen sore; Sel–Kam pagi+sore; Jum pagi; Sab–Ahad libur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_tahfidz', function (Blueprint $table) {
            $table->time('jam_pagi_mulai')->default('05:00:00');
            $table->time('jam_pagi_selesai')->default('05:45:00');
            $table->time('jam_sore_mulai')->default('15:30:00');
            $table->time('jam_sore_selesai')->default('16:15:00');
            $table->unsignedTinyInteger('jp_per_sesi')->default(1);
            $table->json('pola_jadwal')->nullable();
        });

        DB::table('setting_tahfidz')->update([
            'pola_jadwal' => json_encode([
                'senin'  => ['sore'],
                'selasa' => ['pagi', 'sore'],
                'rabu'   => ['pagi', 'sore'],
                'kamis'  => ['pagi', 'sore'],
                'jumat'  => ['pagi'],
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('setting_tahfidz', function (Blueprint $table) {
            $table->dropColumn([
                'jam_pagi_mulai', 'jam_pagi_selesai', 'jam_sore_mulai',
                'jam_sore_selesai', 'jp_per_sesi', 'pola_jadwal',
            ]);
        });
    }
};
