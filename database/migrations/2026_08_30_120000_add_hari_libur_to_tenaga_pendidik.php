<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hari libur mingguan tetap per guru (mis. ["sabtu"] atau ["jumat","sabtu"]).
 * Dipakai mode generate "Dari Hari Libur Guru": kerja semua hari template
 * KECUALI hari libur ini. Null/[] = tidak ada libur mingguan tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            if (!Schema::hasColumn('tenaga_pendidik', 'hari_libur')) {
                $table->json('hari_libur')->nullable()->after('is_mukim')
                    ->comment('Hari libur mingguan tetap, mis. ["sabtu"]');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            if (Schema::hasColumn('tenaga_pendidik', 'hari_libur')) {
                $table->dropColumn('hari_libur');
            }
        });
    }
};
