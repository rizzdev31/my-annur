<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan');
            $table->string('kode_jabatan')->unique()->comment('Contoh: KPS, BEND, GURU, WALI');
            $table->text('deskripsi')->nullable();
            $table->enum('tipe', ['struktural', 'fungsional', 'mengajar'])
                  ->default('fungsional')
                  ->comment('struktural=kepala/sekretaris, fungsional=admin/staff, mengajar=guru murni');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan');
    }
};