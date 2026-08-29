<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubrik penilaian tasmi' (KHUSUS TASMI'): Kelancaran, Makhorijul Huruf, Tajwid, Fashohah.
 * Masing-masing 1-10. Nilai akhir = rata-rata 4 rubrik (disimpan di kolom `nilai`),
 * lulus bila rata-rata >= 8. Additive & nullable — data lama (nilai tunggal) tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas_tasmi', function (Blueprint $table) {
            $table->decimal('nilai_kelancaran', 4, 2)->nullable()->after('nilai');
            $table->decimal('nilai_makhorijul_huruf', 4, 2)->nullable()->after('nilai_kelancaran');
            $table->decimal('nilai_tajwid', 4, 2)->nullable()->after('nilai_makhorijul_huruf');
            $table->decimal('nilai_fashohah', 4, 2)->nullable()->after('nilai_tajwid');
        });
    }

    public function down(): void
    {
        Schema::table('tugas_tasmi', function (Blueprint $table) {
            $table->dropColumn(['nilai_kelancaran', 'nilai_makhorijul_huruf', 'nilai_tajwid', 'nilai_fashohah']);
        });
    }
};
