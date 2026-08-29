<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antisipasi lupa/di-luar-lokasi saat CHECK-OUT:
 * - absensi_harian: penanda checkout luar lokasi + alasan wajib (auditable).
 * - setting_lokasi_absensi: toggle apakah checkout luar lokasi diizinkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_harian', function (Blueprint $table) {
            if (!Schema::hasColumn('absensi_harian', 'pulang_luar_lokasi')) {
                $table->boolean('pulang_luar_lokasi')->default(false)->after('lng_pulang');
            }
            if (!Schema::hasColumn('absensi_harian', 'alasan_pulang')) {
                $table->string('alasan_pulang', 255)->nullable()->after('pulang_luar_lokasi');
            }
            if (!Schema::hasColumn('absensi_harian', 'jarak_pulang_meter')) {
                $table->decimal('jarak_pulang_meter', 10, 2)->nullable()->after('alasan_pulang');
            }
        });

        Schema::table('setting_lokasi_absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('setting_lokasi_absensi', 'izinkan_checkout_luar_lokasi')) {
                $table->boolean('izinkan_checkout_luar_lokasi')->default(true)->after('izinkan_izin_aktif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_harian', function (Blueprint $table) {
            $table->dropColumn(['pulang_luar_lokasi', 'alasan_pulang', 'jarak_pulang_meter']);
        });
        Schema::table('setting_lokasi_absensi', function (Blueprint $table) {
            $table->dropColumn('izinkan_checkout_luar_lokasi');
        });
    }
};
