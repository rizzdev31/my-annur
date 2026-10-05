<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak koreksi roster kegiatan.
 *
 * Roster hari kegiatan ditulis sistem (semua hadir), jadi santri yang tidak ikut
 * tetap tercatat hadir. Guru pendamping boleh mengoreksinya — dan karena koreksi
 * itu bisa menandai santri ALPHA, harus jelas siapa yang menandai dan kapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_santri', function (Blueprint $t) {
            $t->foreignId('dikoreksi_oleh')->nullable()->after('sumber')
              ->constrained('users')->nullOnDelete();
            $t->timestamp('dikoreksi_pada')->nullable()->after('dikoreksi_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_santri', function (Blueprint $t) {
            $t->dropForeign(['dikoreksi_oleh']);
            $t->dropColumn(['dikoreksi_oleh', 'dikoreksi_pada']);
        });
    }
};
