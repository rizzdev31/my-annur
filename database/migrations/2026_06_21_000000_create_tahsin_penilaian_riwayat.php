<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat (log) penilaian tahsin — tracking pembelajaran.
 * tahsin_penilaian = snapshot nilai TERKINI per (santri, materi).
 * Tabel ini menyimpan SETIAP input/perubahan nilai (tak pernah dihapus) untuk evaluasi.
 * Aditif — tidak menyentuh tabel lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahsin_penilaian_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('materi_id')->constrained('setting_tahsin_materi')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->decimal('nilai', 4, 1)->nullable();
            $table->boolean('lulus')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('tenaga_pendidik_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
            $table->date('tanggal');
            $table->timestamps();

            $table->index(['santri_id', 'materi_id']);
            $table->index(['santri_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahsin_penilaian_riwayat');
    }
};
