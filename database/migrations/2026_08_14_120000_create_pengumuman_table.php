<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumuman/pamflet yang tampil sebagai pop-up saat guru membuka aplikasi Flutter.
 * Dikelola superadmin lewat web. Aditif — tidak menyentuh tabel lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->string('judul')->nullable();
            $table->string('gambar');                 // path relatif di disk 'public'
            $table->string('link_url')->nullable();   // CTA opsional (dibuka saat pamflet diketuk)
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
