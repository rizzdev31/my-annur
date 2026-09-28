<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan jendela tanggal yang dinilai pada tiap rekap kinerja.
 *
 * Sejak kinerja mengikuti jendela PERIODE PENGGAJIAN (mis. 25 Agustus–25
 * September untuk label "September"), label bulan saja tidak lagi cukup untuk
 * menjelaskan angkanya. Dua kolom ini membuat setiap skor bisa dipertanggung-
 * jawabkan: "dinilai dari tanggal ini sampai tanggal itu".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            $table->date('dinilai_dari')->nullable()->after('dihitung_pada');
            $table->date('dinilai_sampai')->nullable()->after('dinilai_dari');
        });
    }

    public function down(): void
    {
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            $table->dropColumn(['dinilai_dari', 'dinilai_sampai']);
        });
    }
};
