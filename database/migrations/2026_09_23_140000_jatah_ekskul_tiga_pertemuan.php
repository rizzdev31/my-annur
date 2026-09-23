<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jatah pertemuan ekstrakurikuler: 3× per bulan (23 Sep 2026).
 *
 * Sebagian ekskul masih tercatat 4× karena salah ketik saat pendataan awal.
 * Seluruh ekskul disamakan ke 3, dan bawaan kolom ikut 3 agar ekskul baru
 * tidak mengulang kekeliruan yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ekstrakurikuler')->update(['pertemuan_per_bulan' => 3]);

        Schema::table('ekstrakurikuler', function (Blueprint $t) {
            $t->unsignedTinyInteger('pertemuan_per_bulan')->default(3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ekstrakurikuler', function (Blueprint $t) {
            $t->unsignedTinyInteger('pertemuan_per_bulan')->default(4)->change();
        });
    }
};
