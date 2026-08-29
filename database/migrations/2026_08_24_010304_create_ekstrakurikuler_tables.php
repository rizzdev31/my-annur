<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur Ekstrakurikuler — terpisah dari KBM/JP, vakasi flat per pertemuan.
 * Keputusan: vakasi per pertemuan; penilaian per semester (tahun_ajaran_id);
 * anggota lintas kelas; absensi H/I/S/A.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Master ekskul + jadwalnya sendiri
        Schema::create('ekstrakurikuler', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);
            $t->string('deskripsi', 300)->nullable();
            $t->unsignedBigInteger('pembina_id')->nullable();          // tenaga_pendidik
            $t->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'])->nullable();
            $t->time('jam_mulai')->nullable();
            $t->time('jam_selesai')->nullable();
            $t->string('lokasi', 100)->nullable();
            $t->unsignedBigInteger('tahun_ajaran_id')->nullable();
            $t->unsignedSmallInteger('kuota')->nullable();
            $t->decimal('nominal_vakasi', 12, 2)->nullable();          // override; null = pakai SettingVakasi 'ekstrakurikuler'
            $t->unsignedSmallInteger('batas_isi_hari')->nullable();    // aturan: absensi boleh diisi ≤ N hari dari tanggal (null = bebas)
            $t->boolean('is_aktif')->default(true);
            $t->unsignedBigInteger('dibuat_oleh')->nullable();
            $t->timestamps();
            $t->index(['pembina_id', 'is_aktif']);
        });

        // Anggota (kelompok) — m-n santri ↔ ekskul, lintas kelas
        Schema::create('ekstrakurikuler_santri', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ekstrakurikuler_id');
            $t->unsignedBigInteger('santri_id');
            $t->date('tanggal_masuk')->nullable();
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
            $t->unique(['ekstrakurikuler_id', 'santri_id']);
            $t->index('santri_id');
        });

        // Pertemuan (1 sesi = 1 absensi = sumber vakasi)
        Schema::create('ekstrakurikuler_pertemuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ekstrakurikuler_id');
            $t->date('tanggal');
            $t->time('jam_mulai_aktual')->nullable();
            $t->string('materi', 300)->nullable();
            $t->enum('status', ['berlangsung', 'selesai'])->default('berlangsung');
            $t->unsignedBigInteger('pembina_id')->nullable();          // yang mengisi
            $t->decimal('nominal_vakasi', 12, 2)->default(0);          // snapshot saat selesai
            $t->boolean('vakasi_diberikan')->default(false);
            $t->timestamps();
            $t->index(['ekstrakurikuler_id', 'tanggal']);
            $t->index(['pembina_id', 'status']);
        });

        // Absensi per santri per pertemuan
        Schema::create('ekstrakurikuler_absensi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pertemuan_id');
            $t->unsignedBigInteger('santri_id');
            $t->enum('status', ['hadir', 'izin', 'sakit', 'alpha'])->default('hadir');
            $t->string('keterangan', 150)->nullable();
            $t->timestamps();
            $t->unique(['pertemuan_id', 'santri_id']);
        });

        // Penilaian per semester (keaktifan & perkembangan A/B/C)
        Schema::create('ekstrakurikuler_penilaian', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ekstrakurikuler_id');
            $t->unsignedBigInteger('santri_id');
            $t->unsignedBigInteger('tahun_ajaran_id')->nullable();     // = semester
            $t->enum('keaktifan', ['A', 'B', 'C'])->nullable();
            $t->enum('perkembangan', ['A', 'B', 'C'])->nullable();
            $t->string('catatan', 300)->nullable();
            $t->unsignedBigInteger('dinilai_oleh')->nullable();
            $t->date('tanggal')->nullable();
            $t->timestamps();
            $t->unique(['ekstrakurikuler_id', 'santri_id', 'tahun_ajaran_id'], 'uniq_penilaian_ekskul');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekstrakurikuler_penilaian');
        Schema::dropIfExists('ekstrakurikuler_absensi');
        Schema::dropIfExists('ekstrakurikuler_pertemuan');
        Schema::dropIfExists('ekstrakurikuler_santri');
        Schema::dropIfExists('ekstrakurikuler');
    }
};
