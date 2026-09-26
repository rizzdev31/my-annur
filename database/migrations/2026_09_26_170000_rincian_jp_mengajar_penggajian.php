<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `total_jp_mengajar` hanya berisi JP yang DIBAYAR vakasi, yaitu JP mengajar
 * pengganti (kebijakan: mengajar jadwal sendiri sudah masuk gaji pokok).
 * Akibatnya slip guru yang mengajar jadwalnya sendiri sepanjang bulan
 * menampilkan "0 JP" — terlihat seperti tidak pernah mengajar.
 *
 * Kolom rincian ini menyimpan JP yang benar-benar diampu, tanpa mengubah arti
 * `total_jp_mengajar` yang sudah dipakai perhitungan vakasi. NULL = periode
 * lama yang belum di-generate ulang (slip menghitungnya langsung dari absensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->integer('total_jp_sendiri')->nullable()->after('total_jp_mengajar');
            $table->integer('total_jp_pengganti')->nullable()->after('total_jp_sendiri');
            $table->integer('total_jp_libur_izin')->nullable()->after('total_jp_pengganti');
        });
    }

    public function down(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropColumn(['total_jp_sendiri', 'total_jp_pengganti', 'total_jp_libur_izin']);
        });
    }
};
