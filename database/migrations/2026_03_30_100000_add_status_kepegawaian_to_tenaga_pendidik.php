<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            // Status kepegawaian lebih detail
            $table->enum('status_kepegawaian', [
                'aktif',
                'cuti',
                'cuti_sakit',
                'nonaktif_sementara',
                'resign',
                'pensiun',
                'meninggal',
            ])->default('aktif')->after('is_aktif');

            $table->date('tanggal_nonaktif')->nullable()
                  ->after('tanggal_keluar')
                  ->comment('Tanggal efektif nonaktif/resign/cuti');
            $table->text('alasan_nonaktif')->nullable()
                  ->after('tanggal_nonaktif')
                  ->comment('Alasan perubahan status kepegawaian');
            $table->foreignId('dinonaktifkan_oleh')->nullable()
                  ->after('alasan_nonaktif')
                  ->constrained('users')
                  ->comment('Superadmin yang melakukan perubahan status');
        });

        // Log riwayat perubahan status kepegawaian
        Schema::create('riwayat_status_kepegawaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->enum('status_lama', [
                'aktif', 'cuti', 'cuti_sakit',
                'nonaktif_sementara', 'resign', 'pensiun', 'meninggal',
            ]);
            $table->enum('status_baru', [
                'aktif', 'cuti', 'cuti_sakit',
                'nonaktif_sementara', 'resign', 'pensiun', 'meninggal',
            ]);
            $table->date('tanggal_efektif');
            $table->date('tanggal_kembali')->nullable()
                  ->comment('Diisi jika status bersifat sementara (cuti, nonaktif_sementara)');
            $table->text('alasan')->nullable();
            $table->string('dokumen_pendukung')->nullable()
                  ->comment('Path file surat resign/cuti/dll');
            $table->foreignId('dicatat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status_kepegawaian');
        Schema::table('tenaga_pendidik', function (Blueprint $table) {
            $table->dropForeign(['dinonaktifkan_oleh']);
            $table->dropColumn([
                'status_kepegawaian',
                'tanggal_nonaktif',
                'alasan_nonaktif',
                'dinonaktifkan_oleh',
            ]);
        });
    }
};