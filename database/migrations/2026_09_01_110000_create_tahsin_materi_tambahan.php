<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi Tambahan Tahsin — PELENGKAP JURNAL harian, di luar materi wajib.
 * TIDAK dihitung untuk kelulusan/naik level (levelSelesai hanya materi wajib
 * SettingTahsinMateri). Append-only: boleh banyak entri per santri per hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahsin_materi_tambahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
            $table->foreignId('absensi_mengajar_id')->nullable()->constrained('absensi_mengajar')->nullOnDelete();
            $table->string('nama_materi');
            $table->decimal('nilai', 4, 2)->nullable();
            $table->string('catatan', 300)->nullable();
            $table->date('tanggal');
            $table->timestamps();

            $table->index(['santri_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahsin_materi_tambahan');
    }
};
