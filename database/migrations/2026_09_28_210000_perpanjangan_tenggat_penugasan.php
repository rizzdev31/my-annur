<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenggat pengisian tugas tambahan: perpanjangan waktu per PENERIMA.
 *
 * Masalah sebelumnya: tugas yang tenggatnya sudah lewat tetap muncul di daftar
 * tugas aktif PWA dan masih bisa diisi kapan saja — laporan bulan lampau bisa
 * masuk hari ini lalu dibayar pada periode gaji yang berbeda. Sementara di sisi
 * lain, tugas yang BELUM jatuh tempo sudah memotong skor kinerja.
 *
 * Kini setiap penerima punya tenggat efektif = perpanjangan (bila diberikan)
 * atau tanggal_selesai tugas. Admin memutuskan per orang: memberi waktu tambahan,
 * atau menandai tidak terlaksana (status_pengerjaan 'tidak_selesai' yang sudah ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            $table->date('tenggat_perpanjangan')->nullable()->after('status_pengerjaan')
                  ->comment('Batas pengisian tambahan khusus penerima ini');
            $table->string('alasan_perpanjangan', 300)->nullable()->after('tenggat_perpanjangan');
            $table->foreignId('diperpanjang_oleh')->nullable()->after('alasan_perpanjangan')
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable()->after('diperpanjang_oleh')
                  ->comment('Kapan admin memutuskan tidak terlaksana / memberi perpanjangan');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diperpanjang_oleh');
            $table->dropColumn(['tenggat_perpanjangan', 'alasan_perpanjangan', 'diputuskan_pada']);
        });
    }
};
