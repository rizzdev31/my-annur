<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->enum('kategori', ['umum', 'agama', 'pesantren', 'ekstrakurikuler']);
            $table->string('tingkat')->nullable()->comment('Kelas/tingkatan, misal: VII, VIII, Ula, Wustho');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jadwal_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran');
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran');
            $table->enum('hari', ['senin','selasa','rabu','kamis','jumat','sabtu','ahad']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->integer('jumlah_jp')->comment('Jumlah jam pelajaran dalam sesi ini');
            $table->string('kelas')->comment('Nama kelas/rombel, misal: VII-A, Wustho-1');
            $table->string('ruangan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tahun_ajaran_id']);
            $table->index(['hari', 'jam_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_mengajar');
        Schema::dropIfExists('mata_pelajaran');
    }
};