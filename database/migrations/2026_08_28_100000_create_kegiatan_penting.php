<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan Penting Guru (di-track guru piket) — mis. Sholat Dzuhur.
 * - kegiatan_penting: master (nama, sasaran mukim/non_mukim/semua, jam acuan, poin kinerja).
 * - absensi_kegiatan_penting: catatan kehadiran guru per kegiatan per hari (hadir/tidak_hadir).
 * Sasaran memakai tenaga_pendidik.jenis_guru (mukim=pesantren, non_mukim=sekolah).
 * Tanpa vakasi (vakasi hanya utk guru piket via mekanisme piket yang ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kegiatan_penting')) {
            Schema::create('kegiatan_penting', function (Blueprint $table) {
                $table->id();
                $table->string('nama');                                   // "Sholat Dzuhur Berjamaah"
                $table->enum('sasaran', ['mukim', 'non_mukim', 'semua'])->default('semua');
                $table->time('jam');                                      // jam acuan (mis. 12:00)
                $table->unsignedSmallInteger('poin_hadir')->default(1);   // + apresiasi kinerja piket
                $table->unsignedSmallInteger('poin_absen')->default(1);   // − catatan kinerja piket
                $table->boolean('is_aktif')->default(true);
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('absensi_kegiatan_penting')) {
            Schema::create('absensi_kegiatan_penting', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kegiatan_penting_id')->constrained('kegiatan_penting')->cascadeOnDelete();
                $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
                $table->date('tanggal');
                $table->enum('status', ['hadir', 'tidak_hadir'])->default('tidak_hadir');
                $table->time('jam_hadir')->nullable();
                $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete(); // guru piket
                $table->string('keterangan')->nullable();
                $table->timestamps();

                $table->unique(['kegiatan_penting_id', 'tenaga_pendidik_id', 'tanggal'], 'uq_absen_kegiatan_penting');
                $table->index(['tanggal', 'kegiatan_penting_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_kegiatan_penting');
        Schema::dropIfExists('kegiatan_penting');
    }
};
