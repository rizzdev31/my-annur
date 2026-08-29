<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Tahfidz — Tasmi' sebagai Tugas Tambahan ber-vakasi.
 * - setting_tahfidz.vakasi_tasmi : nominal flat vakasi penguji tasmi'.
 * - tugas_tasmi : penunjukan penguji per (santri, juz), terhubung ke
 *   tugas_tambahan + penugasan_tambahan (jalur vakasi payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_tahfidz', function (Blueprint $table) {
            $table->decimal('vakasi_tasmi', 12, 2)->default(0)->after('jp_per_sesi');
        });

        Schema::create('tugas_tasmi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->foreignId('pengampu_id')->constrained('tenaga_pendidik');
            $table->foreignId('penguji_id')->constrained('tenaga_pendidik');
            $table->foreignId('tugas_tambahan_id')->nullable()->constrained('tugas_tambahan')->nullOnDelete();
            $table->foreignId('penugasan_id')->nullable()->constrained('penugasan_tambahan')->nullOnDelete();
            $table->enum('status', ['ditugaskan', 'selesai', 'dibatalkan'])->default('ditugaskan');
            $table->decimal('nilai', 4, 1)->nullable();
            $table->boolean('lulus')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->index(['penguji_id', 'status']);
            $table->index(['santri_id', 'juz']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_tasmi');
        Schema::table('setting_tahfidz', function (Blueprint $table) {
            $table->dropColumn('vakasi_tasmi');
        });
    }
};
