<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_jam_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Contoh: Jam Kerja Normal Harian');
            $table->time('jam_masuk')->comment('Jam masuk standar, misal 07:00');
            $table->time('jam_pulang')->comment('Jam pulang standar, misal 14:00');
            $table->integer('toleransi_terlambat')->default(15)->comment('Menit toleransi keterlambatan');
            $table->json('hari_kerja')
                  ->comment('Array hari: ["senin","selasa","rabu","kamis","jumat","sabtu"]');
            $table->integer('total_jam_kerja_sehari')->comment('Dalam menit');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Setting toleransi absen mengajar
        Schema::create('setting_absen_mengajar', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Contoh: Setting JP Standar');
            $table->integer('durasi_jp_menit')->default(45)->comment('Durasi 1 jam pelajaran dalam menit');
            $table->integer('toleransi_checkin_menit')->default(10)
                  ->comment('Toleransi absen masuk mengajar sebelum/sesudah jadwal');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_absen_mengajar');
        Schema::dropIfExists('setting_jam_kerja');
    }
};