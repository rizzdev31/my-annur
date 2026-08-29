<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah komponen ke-4 "Penilaian Piket" ke setting kinerja (ADITIF).
 * Default 0 = NETRAL (tidak memengaruhi skor) sampai fitur piket diaktifkan
 * dan bobot disetel mis. Absensi 35 / Tugas 30 / Administrasi 20 / Piket 15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_kinerja', function (Blueprint $t) {
            $t->decimal('bobot_piket', 5, 2)->default(0)->after('bobot_administrasi')
                ->comment('Bobot komponen penilaian piket (0 = netral)');
        });
    }

    public function down(): void
    {
        Schema::table('setting_kinerja', fn(Blueprint $t) => $t->dropColumn('bobot_piket'));
    }
};
