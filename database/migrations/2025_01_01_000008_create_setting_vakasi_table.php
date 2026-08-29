<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_vakasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Nama vakasi, misal: Vakasi Hadir Harian');
            $table->enum('tipe_aktivitas', [
                'absen_harian',       // hadir kerja harian
                'absen_mengajar',     // per jam pelajaran mengajar
                'tugas_jabatan',      // tugas rutin per jabatan
                'tugas_tambahan',     // tugas ad-hoc / mendadak
            ]);
            $table->enum('satuan', ['per_hari', 'per_jp', 'per_tugas', 'per_jam'])
                  ->comment('jp=jam pelajaran');
            $table->decimal('nominal', 12, 2)->comment('Nominal vakasi per satuan');
            $table->boolean('berlaku_untuk_semua')->default(false)
                  ->comment('false = hanya jabatan tertentu');
            $table->json('jabatan_ids')->nullable()
                  ->comment('Jabatan yang mendapat vakasi ini. Null jika berlaku_untuk_semua=true');
            $table->date('berlaku_mulai');
            $table->date('berlaku_selesai')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['tipe_aktivitas', 'is_aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_vakasi');
    }
};