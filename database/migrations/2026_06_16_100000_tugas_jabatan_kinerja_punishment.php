<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tugas Jabatan → murni kinerja (tanpa vakasi) + opsi verifikasi + disiplin harian,
 * serta fitur PUNISHMENT KINERJA (potongan / evaluasi / peringatan / pencopotan).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── tugas_jabatan: opsi perlu verifikasi ──────────────────────────────
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (!Schema::hasColumn('tugas_jabatan', 'perlu_verifikasi')) {
                $table->boolean('perlu_verifikasi')->default(true)->after('tipe_pengerjaan')
                      ->comment('true=realisasi butuh approve admin, false=auto-sah');
            }
        });

        // ── realisasi_tugas_jabatan: tanda telat (deadline jam masuk) ─────────
        Schema::table('realisasi_tugas_jabatan', function (Blueprint $table) {
            if (!Schema::hasColumn('realisasi_tugas_jabatan', 'terlambat')) {
                $table->boolean('terlambat')->default(false)->after('disetujui')
                      ->comment('true jika dikirim setelah jam masuk (disiplin)');
            }
        });

        // ── punishment_kinerja ────────────────────────────────────────────────
        Schema::create('punishment_kinerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->enum('jenis', ['potongan', 'evaluasi', 'peringatan', 'pencopotan']);
            $table->decimal('nominal', 12, 2)->nullable()
                  ->comment('Khusus jenis potongan → masuk potongan penggajian periode tsb');
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatan')
                  ->comment('Khusus pencopotan: jabatan yang dicabut');
            $table->decimal('skor_kinerja', 5, 2)->nullable()
                  ->comment('Snapshot skor total kinerja saat punishment diberikan');
            $table->text('catatan');
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'bulan', 'tahun']);
            $table->index(['bulan', 'tahun', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punishment_kinerja');
        Schema::table('realisasi_tugas_jabatan', function (Blueprint $table) {
            if (Schema::hasColumn('realisasi_tugas_jabatan', 'terlambat')) {
                $table->dropColumn('terlambat');
            }
        });
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (Schema::hasColumn('tugas_jabatan', 'perlu_verifikasi')) {
                $table->dropColumn('perlu_verifikasi');
            }
        });
    }
};
