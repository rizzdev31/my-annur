<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama deskriptif kelas (mis. "Ibnu Sina", "Lubna") — dikelola Smart (master),
 * dikirim ke RamahAnak saat sync santri. `nama` tetap = kode kelas ("VII A").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $t) {
            $t->string('nama_deskriptif', 100)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $t) {
            $t->dropColumn('nama_deskriptif');
        });
    }
};
