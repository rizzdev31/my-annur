<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gaji_pokok_individu', function (Blueprint $table) {
            // Tambah jabatan_id — nullable karena data lama tidak per jabatan
            // NULL = override total gaji (semua jabatan)
            // DIISI = override gaji untuk jabatan tertentu saja (rangkap jabatan)
            $table->foreignId('jabatan_id')
                ->nullable()
                ->after('tenaga_pendidik_id')
                ->constrained('jabatan')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gaji_pokok_individu', function (Blueprint $table) {
            $table->dropForeign(['jabatan_id']);
            $table->dropColumn('jabatan_id');
        });
    }
};