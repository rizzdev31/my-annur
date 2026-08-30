<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generate Jam Kerja per-guru dari jadwal mengajar — fondasi.
 *
 * Membedakan SettingJamKerja:
 *   - is_template = true  → template waktu (bersama, tampil di daftar setting)
 *   - is_template = false → hasil generate PER-GURU (tersembunyi dari daftar template)
 * induk_template_id → jejak template sumber (untuk generate ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            if (!Schema::hasColumn('setting_jam_kerja', 'is_template')) {
                $table->boolean('is_template')->default(true)->after('is_aktif')
                    ->comment('true=template waktu (bersama); false=hasil generate per-guru');
            }
            if (!Schema::hasColumn('setting_jam_kerja', 'induk_template_id')) {
                $table->unsignedBigInteger('induk_template_id')->nullable()->after('is_template')
                    ->comment('Template sumber saat generate per-guru');
            }
            if (!Schema::hasColumn('setting_jam_kerja', 'tenaga_pendidik_id')) {
                $table->unsignedBigInteger('tenaga_pendidik_id')->nullable()->after('induk_template_id')
                    ->comment('Pemilik setting per-guru (null utk template)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            foreach (['is_template', 'induk_template_id', 'tenaga_pendidik_id'] as $col) {
                if (Schema::hasColumn('setting_jam_kerja', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
