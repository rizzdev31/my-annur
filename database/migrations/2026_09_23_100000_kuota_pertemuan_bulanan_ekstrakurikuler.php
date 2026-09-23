<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ekstrakurikuler: dari "terikat hari & jam" → "jatah pertemuan per bulan" (23 Sep 2026).
 *
 * Keputusan user: ekskul tidak lagi dikunci pada hari/jam tertentu. Tiap bulan
 * pembina diberi jatah pertemuan (bawaan 4×) dan bebas memilih kapan mengisinya;
 * yang menjadi penanda sah adalah LOKASI pengisian (tetap seperti sebelumnya).
 *
 * `batas_isi_hari` dikosongkan: batas mundur kini cukup "masih di bulan berjalan".
 * Admin tetap boleh mengisinya bila ingin memperketat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekstrakurikuler', function (Blueprint $t) {
            $t->unsignedTinyInteger('pertemuan_per_bulan')->default(4)->after('jam_selesai');
        });

        DB::table('ekstrakurikuler')->update(['batas_isi_hari' => null]);
    }

    public function down(): void
    {
        Schema::table('ekstrakurikuler', fn (Blueprint $t) => $t->dropColumn('pertemuan_per_bulan'));
    }
};
