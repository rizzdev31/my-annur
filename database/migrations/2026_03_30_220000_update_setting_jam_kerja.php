<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom jadwal_per_hari (JSON) ke setting_jam_kerja
        // Kolom lama (jam_masuk, jam_pulang, hari_kerja) dipertahankan untuk backward compat
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            $table->json('jadwal_per_hari')->nullable()->after('hari_kerja')
                ->comment('JSON: {"senin":{"aktif":true,"jam_masuk":"07:30","jam_pulang":"14:30","toleransi":15,"lintas_hari":false},...}');
            $table->boolean('gunakan_jadwal_per_hari')->default(false)->after('jadwal_per_hari')
                ->comment('True = pakai jadwal_per_hari, False = pakai jam_masuk/jam_pulang lama');
        });
    }

    public function down(): void
    {
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            $table->dropColumn(['jadwal_per_hari', 'gunakan_jadwal_per_hari']);
        });
    }
};