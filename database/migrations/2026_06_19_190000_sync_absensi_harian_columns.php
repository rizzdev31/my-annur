<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration sinkronisasi absensi_harian untuk API Flutter.
 * Kolom foto_masuk, foto_pulang, lat/lng, validasi_lokasi sudah ada
 * di migration awal (2025_01_01_000010).
 * Migration ini hanya memastikan kolom yang mungkin belum ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_harian', function (Blueprint $table) {

            if (!Schema::hasColumn('absensi_harian', 'validasi_lokasi')) {
                $table->string('validasi_lokasi')
                    ->nullable()
                    ->default('tidak_diperiksa')
                    ->after('keterangan')
                    ->comment('valid_koordinat|valid_wifi|valid_izin|valid_dinas_luar|invalid|tidak_diperiksa');
            }

            if (!Schema::hasColumn('absensi_harian', 'nama_wifi')) {
                $table->string('nama_wifi')->nullable()->after('validasi_lokasi');
            }

            if (!Schema::hasColumn('absensi_harian', 'bssid_wifi')) {
                $table->string('bssid_wifi')->nullable()->after('nama_wifi');
            }

            if (!Schema::hasColumn('absensi_harian', 'jarak_meter')) {
                $table->float('jarak_meter')->nullable()->after('bssid_wifi');
            }

            if (!Schema::hasColumn('absensi_harian', 'setting_lokasi_id')) {
                $table->unsignedBigInteger('setting_lokasi_id')
                    ->nullable()
                    ->after('jarak_meter')
                    ->comment('FK ke setting_lokasi_absensi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_harian', function (Blueprint $table) {
            $cols = ['validasi_lokasi','nama_wifi','bssid_wifi',
                     'jarak_meter','setting_lokasi_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('absensi_harian', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};