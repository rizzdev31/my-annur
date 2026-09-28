<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat skor kinerja.
 *
 * `rekap_kinerja_bulanan` hanya menyimpan SATU baris per guru per bulan dan
 * ditimpa setiap kali dihitung ulang (reset, override, bahkan saat halaman
 * detail/raport dibuka). Akibatnya nilai lama hilang tanpa jejak, sehingga
 * admin maupun guru tidak bisa membuktikan skor bulan lalu.
 *
 * Tabel ini menyimpan SALINAN nilai LAMA setiap kali baris rekap berubah,
 * beserta sebab perubahan dan siapa pelakunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            $table->timestamp('dihitung_pada')->nullable()->after('setting_kinerja_id');
            $table->json('faktor_penurunan')->nullable()->after('dihitung_pada');
        });

        Schema::create('riwayat_rekap_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekap_kinerja_bulanan_id')->nullable()
                  ->constrained('rekap_kinerja_bulanan')->nullOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // Nilai LAMA yang digantikan
            $table->decimal('skor_total', 5, 2)->nullable();
            $table->decimal('skor_absensi', 5, 2)->nullable();
            $table->decimal('skor_tugas', 5, 2)->nullable();
            $table->decimal('skor_administrasi', 5, 2)->nullable();
            $table->decimal('skor_piket', 6, 2)->nullable();
            $table->decimal('skor_total_baru', 5, 2)->nullable()
                  ->comment('Skor yang menggantikan — agar selisih terbaca tanpa join');
            $table->boolean('sudah_dikunci_lama')->default(false);
            $table->text('catatan_superadmin_lama')->nullable();
            $table->json('data_lama')->nullable()->comment('Seluruh atribut lama (audit penuh)');

            // Konteks perubahan
            $table->string('sebab', 30)->default('hitung_ulang')
                  ->comment('awal|hitung_ulang|reset|reset_semua|override|catatan');
            $table->text('alasan')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('setting_kinerja_id')->nullable();
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tahun', 'bulan'], 'idx_riwayat_kinerja_guru_periode');
            $table->index(['tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_rekap_kinerja');
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            $table->dropColumn(['dihitung_pada', 'faktor_penurunan']);
        });
    }
};
