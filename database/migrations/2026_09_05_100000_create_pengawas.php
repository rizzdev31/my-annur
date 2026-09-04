<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MONITORING PIMPINAN (Pengawas) — superadmin menunjuk guru tertentu agar dapat
 * memantau aktivitas guru lain LANGSUNG DARI PWA (tanpa akun admin kedua).
 *
 * 4 dimensi izin:
 *   1. siapa pengawasnya          → tenaga_pendidik_id
 *   2. apa yang boleh dilihat     → modul (JSON): absen_harian|absen_mengajar|
 *                                    perizinan|tugas_tambahan|kinerja
 *   3. siapa yang dipantau        → cakupan 'semua' | 'pilih' (+ pivot pengawas_guru)
 *   4. level                      → boleh_setujui_izin (keputusan FINAL, wajib audit
 *                                    & dilarang menyetujui izin diri sendiri)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengawas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
            $t->json('modul')->nullable();                    // daftar modul yang diizinkan
            $t->enum('cakupan', ['semua', 'pilih'])->default('pilih');
            $t->boolean('boleh_setujui_izin')->default(false);
            $t->boolean('is_aktif')->default(true);
            $t->foreignId('ditunjuk_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->string('catatan')->nullable();
            $t->timestamps();

            $t->unique('tenaga_pendidik_id'); // satu baris pengawas per guru
        });

        Schema::create('pengawas_guru', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengawas_id')->constrained('pengawas')->cascadeOnDelete();
            $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['pengawas_id', 'tenaga_pendidik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawas_guru');
        Schema::dropIfExists('pengawas');
    }
};
