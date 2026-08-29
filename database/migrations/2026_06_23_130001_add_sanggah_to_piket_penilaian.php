<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak sanggah & audit untuk penilaian piket (ADITIF).
 * status_sanggah sudah ada; tambahkan alasan + jejak peninjauan oleh admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piket_penilaian', function (Blueprint $t) {
            $t->text('alasan_sanggah')->nullable()->after('status_sanggah');
            $t->unsignedBigInteger('ditinjau_oleh')->nullable()->after('alasan_sanggah'); // user (admin)
            $t->timestamp('ditinjau_pada')->nullable()->after('ditinjau_oleh');
            $t->text('catatan_tinjauan')->nullable()->after('ditinjau_pada');
        });
    }

    public function down(): void
    {
        Schema::table('piket_penilaian', fn(Blueprint $t) =>
            $t->dropColumn(['alasan_sanggah', 'ditinjau_oleh', 'ditinjau_pada', 'catatan_tinjauan']));
    }
};
