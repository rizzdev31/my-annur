<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hari_libur', function (Blueprint $table) {
            // Sumber hari libur
            $table->enum('sumber', ['nasional', 'pesantren', 'darurat'])
                ->default('pesantren')
                ->after('tipe')
                ->comment('nasional=dari daftar libur nasional Indonesia, pesantren=kebijakan pesantren, darurat=mendadak');

            // Status aktif — untuk toggle hari libur nasional
            $table->boolean('is_aktif')
                ->default(true)
                ->after('sumber')
                ->comment('Nonaktifkan hari libur nasional jika pesantren tetap masuk');

            // Untuk libur darurat — rollback tracking
            $table->boolean('is_darurat')
                ->default(false)
                ->after('is_aktif');

            $table->timestamp('dibatalkan_pada')->nullable()->after('is_darurat');
            $table->text('alasan_pembatalan')->nullable()->after('dibatalkan_pada');
            $table->foreignId('dibatalkan_oleh')->nullable()
                ->after('alasan_pembatalan')
                ->constrained('users')->nullOnDelete();

            // Berapa absensi yang otomatis diupdate saat libur darurat
            $table->integer('absensi_terdampak')->default(0)->after('dibatalkan_oleh');

            $table->index(['tanggal', 'is_aktif']);
            $table->index(['sumber', 'is_aktif']);
        });

        // Migrasi data lama: tipe → sumber
        DB::statement("UPDATE hari_libur SET sumber = tipe WHERE tipe IN ('nasional','pesantren','darurat')");
        DB::statement("UPDATE hari_libur SET is_darurat = 1 WHERE tipe = 'darurat'");
    }

    public function down(): void
    {
        Schema::table('hari_libur', function (Blueprint $table) {
            $table->dropForeign(['dibatalkan_oleh']);
            $table->dropColumn([
                'sumber', 'is_aktif', 'is_darurat',
                'dibatalkan_pada', 'alasan_pembatalan',
                'dibatalkan_oleh', 'absensi_terdampak',
            ]);
        });
    }
};