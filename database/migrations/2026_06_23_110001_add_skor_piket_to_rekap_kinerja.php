<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan skor komponen Piket pada rekap kinerja bulanan (ADITIF, default 100 = baseline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $t) {
            $t->decimal('skor_piket', 5, 2)->default(100)->after('skor_administrasi')
                ->comment('Skor komponen piket (mulai 100, ± dari penilaian piket)');
        });
    }

    public function down(): void
    {
        Schema::table('rekap_kinerja_bulanan', fn(Blueprint $t) => $t->dropColumn('skor_piket'));
    }
};
