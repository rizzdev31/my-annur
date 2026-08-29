<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam kerja per individu tendik (ADITIF).
 * Nullable: bila tidak diisi → pakai SettingJamKerja default (perilaku lama).
 * Memungkinkan shift berbeda per guru (mis. normal 07:00–15:15 vs lintas 15:15–07:00).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $t) {
            $t->unsignedBigInteger('setting_jam_kerja_id')->nullable()->after('jabatan_id');
            $t->index('setting_jam_kerja_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $t) {
            $t->dropIndex(['setting_jam_kerja_id']);
            $t->dropColumn('setting_jam_kerja_id');
        });
    }
};
