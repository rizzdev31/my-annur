<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log semua aktivitas penting (audit trail)
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')
                  ->comment('Siapa yang melakukan aksi');
            $table->string('aksi')->comment('create_absensi, koreksi_absensi, finalisasi_gaji, dll');
            $table->string('model_type')->nullable()->comment('App\Models\AbsensiHarian');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('data_lama')->nullable()->comment('Snapshot sebelum perubahan');
            $table->json('data_baru')->nullable()->comment('Snapshot setelah perubahan');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at');

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('aksi');
        });

        // Notifikasi untuk guru (dikirim via Flutter push notif)
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('Penerima notif');
            $table->string('judul');
            $table->text('pesan');
            $table->enum('tipe', [
                'absensi',
                'tugas_baru',
                'tugas_update',
                'penggajian',
                'koreksi',
                'pengumuman',
            ]);
            $table->json('data')->nullable()->comment('Payload tambahan untuk deep link Flutter');
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sudah_dibaca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('log_aktivitas');
    }
};