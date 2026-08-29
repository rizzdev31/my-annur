<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Absensi harian kerja
        Schema::create('absensi_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->string('foto_masuk')->nullable()->comment('Foto selfie absen masuk dari Flutter');
            $table->string('foto_pulang')->nullable();
            $table->decimal('lat_masuk', 10, 8)->nullable()->comment('Koordinat GPS masuk');
            $table->decimal('lng_masuk', 11, 8)->nullable();
            $table->decimal('lat_pulang', 10, 8)->nullable();
            $table->decimal('lng_pulang', 11, 8)->nullable();
            $table->enum('status', [
                'hadir',
                'terlambat',
                'izin',
                'sakit',
                'alfa',
                'libur',
                'dinas_luar',
            ])->default('hadir');
            $table->integer('menit_terlambat')->default(0);
            $table->text('keterangan')->nullable();
            $table->boolean('is_koreksi')->default(false)
                  ->comment('Ditandai jika data ini hasil koreksi manual superadmin');
            $table->foreignId('dikoreksi_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['tenaga_pendidik_id', 'tanggal'], 'unique_absen_harian');
            $table->index(['tanggal', 'status']);
        });

        // Absensi mengajar per sesi/jadwal
        Schema::create('absensi_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_mengajar_id')->constrained('jadwal_mengajar');
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->time('jam_mulai_aktual')->nullable()->comment('Jam mulai mengajar aktual (dari absen)');
            $table->time('jam_selesai_aktual')->nullable();
            $table->integer('jp_terlaksana')->default(0)
                  ->comment('Jumlah JP yang benar-benar terlaksana');
            $table->enum('status', [
                'terlaksana',
                'tidak_terlaksana',
                'pengganti',
                'libur',
            ])->default('terlaksana');
            $table->foreignId('digantikan_oleh')->nullable()->constrained('tenaga_pendidik')
                  ->comment('Jika ada guru pengganti');
            $table->text('materi')->nullable()->comment('Materi yang diajarkan hari itu');
            $table->text('keterangan')->nullable();
            $table->boolean('is_koreksi')->default(false);
            $table->foreignId('dikoreksi_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['jadwal_mengajar_id', 'tanggal'], 'unique_absen_mengajar');
            $table->index(['tenaga_pendidik_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_mengajar');
        Schema::dropIfExists('absensi_harian');
    }
};