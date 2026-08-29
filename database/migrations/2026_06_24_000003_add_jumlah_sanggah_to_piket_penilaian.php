<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batasi spam sanggah: hitung berapa kali sebuah penilaian disanggah.
 * Maksimal 2x pengajuan (1 awal + 1 ulang setelah ditolak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piket_penilaian', function (Blueprint $t) {
            $t->unsignedTinyInteger('jumlah_sanggah')->default(0)->after('status_sanggah');
        });
    }

    public function down(): void
    {
        Schema::table('piket_penilaian', fn(Blueprint $t) => $t->dropColumn('jumlah_sanggah'));
    }
};
