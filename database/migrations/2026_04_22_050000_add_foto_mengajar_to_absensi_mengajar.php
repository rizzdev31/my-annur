<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            // Foto dokumentasi mengajar dari Flutter
            if (!Schema::hasColumn('absensi_mengajar', 'foto_mengajar')) {
                $table->string('foto_mengajar')->nullable()
                    ->after('materi')
                    ->comment('Foto dokumentasi saat mengajar, diupload via Flutter');
            }
            // Flag: guru sudah membuka link jurnal sebelum absen
            if (!Schema::hasColumn('absensi_mengajar', 'sudah_buka_jurnal')) {
                $table->boolean('sudah_buka_jurnal')->default(false)
                    ->after('foto_mengajar')
                    ->comment('Guru wajib membuka link jurnal sebelum bisa absen mengajar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            $table->dropColumn(['foto_mengajar', 'sudah_buka_jurnal']);
        });
    }
};