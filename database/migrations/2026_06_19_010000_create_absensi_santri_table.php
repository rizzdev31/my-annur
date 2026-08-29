<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Education — Fase 3: Absensi Santri (jurnal mengajar sekolah)
 * Menempel pada record AbsensiMengajar yang sudah ada (1 sesi mengajar guru),
 * sehingga JP/vakasi tetap lewat jalur payroll lama tanpa perubahan.
 * Status hanya 3 (hadir/telat/alpha) sesuai keputusan — sumber data ramahanak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_santri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_mengajar_id')->constrained('absensi_mengajar')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'telat', 'alpha'])->default('hadir');
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['absensi_mengajar_id', 'santri_id']);
            $table->index('santri_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_santri');
    }
};
