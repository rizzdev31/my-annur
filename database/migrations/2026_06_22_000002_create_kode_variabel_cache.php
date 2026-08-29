<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache kode variabel (pelanggaran/apresiasi/konselor) hasil sinkron dari RamahAnak.
 * Dipakai form Smart Eksekusi agar tendik memilih kode yang valid (tanpa hardcode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kode_variabel_cache', function (Blueprint $t) {
            $t->id();
            $t->enum('jenis', ['pelanggaran', 'apresiasi', 'konselor']);
            $t->string('kode');
            $t->string('kategori')->nullable();
            $t->integer('poin')->nullable();
            $t->string('label')->nullable();
            $t->timestamps();

            $t->unique(['jenis', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_variabel_cache');
    }
};
