<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan hari libur oleh guru (PWA). Guru MENGAJUKAN → admin menyetujui
 * (menyalin ke hari_libur). Terpisah dari hari_libur (yang aktif/disetujui).
 * Null = tidak ada pengajuan tertunda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            if (!Schema::hasColumn('tenaga_pendidik', 'hari_libur_diajukan')) {
                $table->json('hari_libur_diajukan')->nullable()->after('hari_libur')
                    ->comment('Usulan hari libur dari guru (menunggu persetujuan admin)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            if (Schema::hasColumn('tenaga_pendidik', 'hari_libur_diajukan')) {
                $table->dropColumn('hari_libur_diajukan');
            }
        });
    }
};
