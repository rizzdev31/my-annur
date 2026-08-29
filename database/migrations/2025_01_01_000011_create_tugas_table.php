<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Definisi tugas jabatan (template tugas per jabatan)
        Schema::create('tugas_jabatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_id')->constrained('jabatan');
            $table->string('nama_tugas');
            $table->text('deskripsi')->nullable();
            $table->enum('frekuensi', ['harian', 'mingguan', 'bulanan', 'insidental'])
                  ->comment('Seberapa sering tugas ini dilakukan');
            $table->foreignId('setting_vakasi_id')->nullable()->constrained('setting_vakasi')
                  ->comment('Vakasi yang berlaku untuk tugas ini');
            $table->boolean('wajib_laporan')->default(false)
                  ->comment('Apakah harus upload bukti/laporan');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Tugas tambahan ad-hoc (dibuat superadmin, ditugaskan ke individu/grup)
        Schema::create('tugas_tambahan', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->enum('tipe', ['individu', 'jabatan', 'semua'])
                  ->comment('Target penerima tugas');
            $table->foreignId('setting_vakasi_id')->nullable()->constrained('setting_vakasi');
            $table->decimal('vakasi_override', 12, 2)->nullable()
                  ->comment('Override vakasi khusus untuk tugas ini, abaikan setting_vakasi');
            $table->boolean('wajib_laporan')->default(false);
            $table->enum('status', ['draft', 'aktif', 'selesai', 'dibatalkan'])->default('aktif');
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();
        });

        // Penugasan: siapa saja yang mendapat tugas_tambahan
        Schema::create('penugasan_tambahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_tambahan_id')->constrained('tugas_tambahan')->onDelete('cascade');
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->enum('status_pengerjaan', ['belum', 'sedang', 'selesai', 'tidak_selesai'])
                  ->default('belum');
            $table->text('laporan')->nullable()->comment('Laporan hasil pengerjaan dari guru');
            $table->string('file_laporan')->nullable();
            $table->timestamp('dikerjakan_pada')->nullable();
            $table->timestamp('dilaporkan_pada')->nullable();
            $table->boolean('disetujui')->nullable()->comment('Null=belum diverifikasi, true=approved');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users');
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();

            $table->unique(['tugas_tambahan_id', 'tenaga_pendidik_id']);
        });

        // Rekap realisasi tugas jabatan (bukti pelaksanaan tugas rutin)
        Schema::create('realisasi_tugas_jabatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_jabatan_id')->constrained('tugas_jabatan');
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->string('file_bukti')->nullable();
            $table->boolean('disetujui')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_tugas_jabatan');
        Schema::dropIfExists('penugasan_tambahan');
        Schema::dropIfExists('tugas_tambahan');
        Schema::dropIfExists('tugas_jabatan');
    }
};