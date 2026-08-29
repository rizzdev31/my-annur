<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi Guru Piket (ADITIF):
 *  - piket_kategori  : rubrik penilaian (apresiasi/catatan) + dimensi (disiplin/tugas/administrasi).
 *  - piket_jadwal    : penunjukan piket per hari (window ikut jam absen masuk–pulang piket).
 *  - piket_penilaian : penilaian piket ke guru (poin snapshot dari kategori).
 * Absensi santri oleh piket REUSE tabel absensi_santri yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piket_kategori', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
            $t->enum('jenis', ['apresiasi', 'catatan']);              // + / -
            $t->enum('dimensi', ['disiplin', 'tugas', 'administrasi']); // untuk pengelompokan laporan
            $t->decimal('poin', 5, 2)->default(0);                    // besar poin (selalu positif; jenis menentukan +/-)
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
        });

        Schema::create('piket_jadwal', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal');
            $t->unsignedBigInteger('tenaga_pendidik_id');            // guru yang bertugas piket
            $t->unsignedBigInteger('ditunjuk_oleh')->nullable();    // user (admin) penunjuk
            $t->text('catatan_harian')->nullable();                  // laporan harian (default "semua aman")
            $t->boolean('vakasi_dibayar')->default(false);
            $t->timestamps();

            $t->unique(['tanggal', 'tenaga_pendidik_id']);           // 1 guru 1 penugasan/hari
            $t->index('tenaga_pendidik_id');
        });

        Schema::create('piket_penilaian', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('piket_jadwal_id');
            $t->unsignedBigInteger('guru_dinilai_id');               // tenaga_pendidik yang dinilai
            $t->unsignedBigInteger('kategori_id');
            $t->enum('jenis', ['apresiasi', 'catatan']);             // snapshot
            $t->enum('dimensi', ['disiplin', 'tugas', 'administrasi']); // snapshot
            $t->decimal('poin', 5, 2);                               // snapshot (besar poin)
            $t->text('catatan')->nullable();
            $t->string('bukti_foto')->nullable();
            $t->unsignedBigInteger('jadwal_mengajar_id')->nullable();
            $t->unsignedBigInteger('absensi_mengajar_id')->nullable();
            $t->enum('status_sanggah', ['-', 'diajukan', 'diterima', 'ditolak'])->default('-');
            $t->timestamps();

            $t->index('piket_jadwal_id');
            $t->index(['guru_dinilai_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_penilaian');
        Schema::dropIfExists('piket_jadwal');
        Schema::dropIfExists('piket_kategori');
    }
};
