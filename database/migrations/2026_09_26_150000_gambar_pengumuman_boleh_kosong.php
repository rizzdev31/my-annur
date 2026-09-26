<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pengumuman.gambar` semula WAJIB karena pengumuman dulu selalu berupa pamflet.
 * Sejak pengumuman bisa berupa berkas PDF (mis. notulensi rapat), kolom ini harus
 * boleh kosong — kalau tidak, penerbitan notulensi gagal dengan
 * "Field 'gambar' doesn't have a default value".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengumuman', function (Blueprint $t) {
            $t->string('gambar', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pengumuman', function (Blueprint $t) {
            $t->string('gambar', 255)->nullable(false)->change();
        });
    }
};
