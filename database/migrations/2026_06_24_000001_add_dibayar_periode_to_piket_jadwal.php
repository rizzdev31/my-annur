<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vakasi piket period-aware (ADITIF):
 *  - dibayar_periode_id : periode penggajian yang membayar hari piket ini.
 * Tujuan: vakasi tiap hari piket dibayar TEPAT SEKALI lintas periode, namun
 * re-generate periode yang sama tetap menghitung harinya (anti-dobel & anti-nol).
 * Kolom lama `vakasi_dibayar` tetap dipertahankan sebagai badge "sudah dibayar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piket_jadwal', function (Blueprint $t) {
            $t->unsignedBigInteger('dibayar_periode_id')->nullable()->after('vakasi_dibayar');
            $t->index('dibayar_periode_id');
        });
    }

    public function down(): void
    {
        Schema::table('piket_jadwal', function (Blueprint $t) {
            $t->dropIndex(['dibayar_periode_id']);
            $t->dropColumn('dibayar_periode_id');
        });
    }
};
