<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Update tugas_jabatan — jadi pure template kategori ─────────────
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            // Hapus kolom vakasi & wajib (tidak relevan lagi)
            if (Schema::hasColumn('tugas_jabatan', 'setting_vakasi_id')) {
                $table->dropForeign(['setting_vakasi_id']);
                $table->dropColumn('setting_vakasi_id');
            }
            if (Schema::hasColumn('tugas_jabatan', 'wajib_laporan')) {
                $table->dropColumn('wajib_laporan');
            }
            // Tambah field yang lebih relevan
            $table->string('icon')->nullable()->after('deskripsi')
                  ->comment('Emoji atau kode icon untuk UI');
            $table->integer('estimasi_durasi_menit')->nullable()->after('icon')
                  ->comment('Estimasi berapa menit per eksekusi');
            $table->integer('urutan')->default(0)->after('estimasi_durasi_menit')
                  ->comment('Urutan tampil di list');
        });

        // ── 2. Log kerja harian — input mandiri guru ──────────────────────────
        Schema::create('log_kerja_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->string('judul');
            $table->text('deskripsi')->nullable();

            // Referensi ke template tugas jabatan (opsional)
            $table->foreignId('tugas_jabatan_id')->nullable()->constrained('tugas_jabatan')
                  ->comment('Null jika tugas bebas, isi jika dari template');
            $table->string('kategori_custom')->nullable()
                  ->comment('Kategori manual jika tidak dari template');

            // Detail pekerjaan
            $table->integer('durasi_menit')->nullable()
                  ->comment('Berapa menit dikerjakan');
            $table->string('file_bukti')->nullable();

            // Status verifikasi
            $table->enum('status', ['draft', 'submitted', 'diverifikasi', 'ditolak'])
                  ->default('submitted');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users');
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tanggal']);
            $table->index(['tanggal', 'status']);
        });

        // ── 3. Rekap kinerja bulanan — dikalkulasi otomatis ───────────────────
        Schema::create('rekap_kinerja_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // Statistik log kerja
            $table->unsignedInteger('total_log_submitted')->default(0);
            $table->unsignedInteger('total_log_diverifikasi')->default(0);
            $table->unsignedInteger('total_durasi_menit')->default(0)
                  ->comment('Total menit kerja yang dilaporkan');

            // Statistik penugasan (dari tugas_tambahan)
            $table->unsignedInteger('total_penugasan_diterima')->default(0);
            $table->unsignedInteger('total_penugasan_selesai')->default(0);

            // Skor efisiensi (0-100)
            $table->decimal('skor_keaktifan', 5, 2)->default(0)
                  ->comment('Log harian / hari kerja aktif × 100');
            $table->decimal('skor_penugasan', 5, 2)->default(0)
                  ->comment('Penugasan selesai / penugasan masuk × 100');
            $table->decimal('skor_total', 5, 2)->default(0)
                  ->comment('Rata-rata weighted dari kedua skor');

            // Catatan superadmin
            $table->text('catatan_superadmin')->nullable();
            $table->foreignId('dikaji_oleh')->nullable()->constrained('users');
            $table->timestamp('dikaji_pada')->nullable();

            // Flag sudah dikalkulasi
            $table->boolean('sudah_dikunci')->default(false)
                  ->comment('True jika periode sudah dikunci, tidak bisa direcalculate');

            $table->timestamps();

            $table->unique(['tenaga_pendidik_id', 'bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_kinerja_bulanan');
        Schema::dropIfExists('log_kerja_harian');
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            $table->dropColumn(['icon', 'estimasi_durasi_menit', 'urutan']);
            $table->foreignId('setting_vakasi_id')->nullable()->constrained('setting_vakasi');
            $table->boolean('wajib_laporan')->default(false);
        });
    }
};