<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASNIF = ujian kenaikan level Tahsin (analog Tasmi' pada Tahfidz).
 * Rubrik: Pemahaman Materi, Kelancaran, Fashohah, Makhorijul Huruf (1-10);
 * nilai akhir = rata-rata, lulus bila >= 8 → santri naik level.
 * Fitur TERPISAH dari tasmi' (tidak menyentuh tabel tugas_tasmi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_tasnif', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('santri_id');
            $t->unsignedTinyInteger('level');              // level yang diujikan (untuk naik ke level+1)
            $t->unsignedBigInteger('pengampu_id')->nullable();
            $t->unsignedBigInteger('penguji_id')->nullable();
            $t->unsignedBigInteger('tugas_tambahan_id')->nullable();
            $t->unsignedBigInteger('penugasan_id')->nullable();
            $t->enum('status', ['ditugaskan', 'selesai'])->default('ditugaskan');
            $t->decimal('nilai', 4, 2)->nullable();        // rata-rata 4 rubrik
            $t->decimal('nilai_pemahaman_materi', 4, 2)->nullable();
            $t->decimal('nilai_kelancaran', 4, 2)->nullable();
            $t->decimal('nilai_fashohah', 4, 2)->nullable();
            $t->decimal('nilai_makhorijul_huruf', 4, 2)->nullable();
            $t->boolean('lulus')->nullable();
            $t->string('catatan', 300)->nullable();
            $t->timestamps();

            $t->index(['santri_id', 'level']);
            $t->index(['penguji_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_tasnif');
    }
};
