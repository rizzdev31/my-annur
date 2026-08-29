<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floor (batas bawah) sub-skor kinerja Guru Piket — agar akumulasi catatan tidak
 * menjatuhkan komponen piket ke 0 secara tidak adil. Diatur admin. Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('setting_kinerja', 'skor_min_piket')) {
            Schema::table('setting_kinerja', function (Blueprint $t) {
                $t->unsignedTinyInteger('skor_min_piket')->default(50)->after('bobot_piket');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('setting_kinerja', 'skor_min_piket')) {
            Schema::table('setting_kinerja', function (Blueprint $t) {
                $t->dropColumn('skor_min_piket');
            });
        }
    }
};
