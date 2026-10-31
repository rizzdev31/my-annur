<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak perubahan level tahsin seorang santri.
 *
 * Sebelum ini level tahsin berubah TANPA jejak: `santri.tahsin_level` hanya
 * ditimpa, entah oleh penempatan awal, oleh tasnif yang lulus, atau oleh
 * perpindahan kelas. Ketika 13 santri levelnya kacau pada 17 Sep 2026, satu-
 * satunya cara menelusurinya adalah membaca cap waktu penilaian dan menebak —
 * koreksinya pun harus ditulis tangan di migration.
 *
 * Tabel ini membuat pertanyaan "kenapa level santri ini 4?" bisa dijawab.
 * Sengaja tanpa foreign key ke kelas: kelas boleh dihapus, jejaknya tidak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_level_tahsin', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $t->unsignedTinyInteger('level_dari')->nullable()->comment('null = belum punya level');
            $t->unsignedTinyInteger('level_ke');
            // 'penempatan' = santri baru/belum berprogres ditempatkan mengikuti kelas
            // 'naik_kelas'  = pindah ke kelas tahsin berlevel lebih tinggi
            // 'tasnif'      = lulus ujian tasnif
            // 'manual'      = diubah langsung oleh admin
            $t->string('sebab', 24);
            $t->unsignedBigInteger('kelas_id')->nullable();
            $t->string('kelas_nama', 120)->nullable()->comment('disalin: kelas boleh dihapus');
            $t->string('catatan', 300)->nullable();
            $t->foreignId('oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['santri_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_level_tahsin');
    }
};
