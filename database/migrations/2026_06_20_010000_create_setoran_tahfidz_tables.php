<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tahfidz — Fase 4B: Setoran & tracking hafalan.
 * - setoran_tahfidz : log tiap setoran (ziyadah / murojaah wajib / tambahan / tasmi)
 * - hafalan_santri  : ringkasan per santri (total ayat, gerbang murojaah, posisi terakhir)
 * - hafalan_juz     : progres per juz (untuk deteksi juz selesai → gerbang tasmi')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_mengajar_id')->nullable()
                  ->constrained('absensi_mengajar')->nullOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->enum('jenis', ['ziyadah', 'murojaah_wajib', 'murojaah_tambahan', 'tasmi']);
            $table->unsignedSmallInteger('surah_mulai');
            $table->unsignedSmallInteger('ayat_mulai');
            $table->unsignedSmallInteger('surah_selesai');
            $table->unsignedSmallInteger('ayat_selesai');
            $table->unsignedSmallInteger('jumlah_ayat')->default(0);
            $table->unsignedTinyInteger('juz_mulai')->nullable();
            $table->unsignedTinyInteger('juz_selesai')->nullable();
            $table->decimal('nilai', 4, 1)->nullable();
            $table->boolean('lulus')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->index(['santri_id', 'jenis']);
            $table->index('tanggal');
        });

        Schema::create('hafalan_santri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->unique()->constrained('santri')->cascadeOnDelete();
            $table->unsignedInteger('total_ayat')->default(0);
            $table->boolean('perlu_murojaah')->default(false)
                  ->comment('Gerbang: true = wajib murojaah dulu sebelum ziyadah baru');
            $table->unsignedSmallInteger('last_surah')->nullable();
            $table->unsignedSmallInteger('last_ayat_mulai')->nullable();
            $table->unsignedSmallInteger('last_ayat_selesai')->nullable();
            $table->unsignedTinyInteger('last_juz')->nullable();
            $table->timestamps();
        });

        Schema::create('hafalan_juz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->unsignedSmallInteger('ayat_terkumpul')->default(0);
            $table->unsignedSmallInteger('jumlah_ayat_juz');
            $table->enum('status', ['berjalan', 'selesai', 'tasmi_lulus'])->default('berjalan')
                  ->comment('selesai = juz penuh, wajib tasmi; tasmi_lulus = boleh lanjut');
            $table->timestamps();

            $table->unique(['santri_id', 'juz']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hafalan_juz');
        Schema::dropIfExists('hafalan_santri');
        Schema::dropIfExists('setoran_tahfidz');
    }
};
