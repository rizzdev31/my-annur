<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Tahsin (Fase 4C):
 * - kelas.jenis tambah 'tahsin' + kelas.level_tahsin (kelas per level)
 * - setting_tahsin_materi : daftar materi terstruktur per level (1..5)
 * - tahsin_penilaian      : nilai per materi per santri (wajib, ambang lulus dari setting)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tambah jenis 'tahsin' ke enum kelas (aditif, default tetap sekolah)
        DB::statement("ALTER TABLE `kelas` MODIFY COLUMN `jenis` ENUM('sekolah','tahfidz','tahsin') NOT NULL DEFAULT 'sekolah'");

        Schema::table('kelas', function (Blueprint $table) {
            $table->unsignedTinyInteger('level_tahsin')->nullable()->after('jenis')
                  ->comment('Level kelas tahsin (1..5), khusus jenis=tahsin');
        });

        Schema::create('setting_tahsin_materi', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');
            $table->unsignedInteger('urutan')->default(0);
            $table->string('nama');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['level', 'is_aktif']);
        });

        Schema::create('tahsin_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_mengajar_id')->nullable()->constrained('absensi_mengajar')->nullOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->unsignedTinyInteger('level');
            $table->foreignId('materi_id')->constrained('setting_tahsin_materi')->cascadeOnDelete();
            $table->decimal('nilai', 4, 1)->nullable();
            $table->boolean('lulus')->nullable();
            $table->string('catatan')->nullable();
            $table->date('tanggal');
            $table->timestamps();

            $table->unique(['santri_id', 'materi_id']); // 1 hasil per materi per santri (dapat diperbarui)
            $table->index(['santri_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahsin_penilaian');
        Schema::dropIfExists('setting_tahsin_materi');
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('level_tahsin');
        });
        DB::statement("ALTER TABLE `kelas` MODIFY COLUMN `jenis` ENUM('sekolah','tahfidz') NOT NULL DEFAULT 'sekolah'");
    }
};
