<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC web admin: peran (fleksibel, dibuat superadmin) berisi modul (tetap, kode)
 * dan diberikan ke akun. Semua ADDITIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 40)->unique();
            $t->string('nama', 60);
            $t->string('deskripsi')->nullable();
            $t->boolean('is_bawaan')->default(false); // seed → tak boleh dihapus
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
        });

        Schema::create('peran_modul', function (Blueprint $t) {
            $t->id();
            $t->foreignId('peran_id')->constrained('peran')->cascadeOnDelete();
            $t->string('modul', 40); // kode modul dari config/modul.php
            $t->timestamps();
            $t->unique(['peran_id', 'modul']);
        });

        Schema::create('user_peran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('peran_id')->constrained('peran')->cascadeOnDelete();
            $t->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'peran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_peran');
        Schema::dropIfExists('peran_modul');
        Schema::dropIfExists('peran');
    }
};
