<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUKU TAMU — satu link publik per kegiatan.
 *
 * Tamu mengisi sendiri dari ponselnya lewat tautan (bukan PWA, tanpa login),
 * lalu nomor urut kunjungan diberikan sistem. Notulensi kegiatan dikirim ke
 * email tamu menyusul, karena itu email WAJIB (keputusan 9 Okt 2026).
 *
 * Catatan desain:
 *  - `token` dipakai di URL, BUKAN id — id berurutan bisa diduga dan membuka
 *    buku tamu kegiatan lain.
 *  - email SENGAJA tidak unique per kegiatan: satu orang boleh mengisi dua kali
 *    pada acara yang sama (keputusan 9 Okt 2026).
 *  - `(kegiatan_tamu_id, nomor_urut)` unique sebagai jaring terakhir; nomor
 *    urutnya sendiri diambil di dalam transaksi berkunci, karena di acara semua
 *    tamu memindai QR dan menekan kirim hampir bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_tamu', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 180);
            $t->text('deskripsi')->nullable();
            $t->date('tanggal');
            $t->date('tanggal_selesai')->nullable();
            $t->string('lokasi', 180)->nullable();
            $t->string('penyelenggara', 180)->nullable();

            // Tautan publik
            $t->string('token', 48)->unique();
            $t->boolean('is_dibuka')->default(true);
            $t->dateTime('dibuka_sampai')->nullable()
              ->comment('Setelah ini link menolak isian, meski lupa ditutup manual');

            // Notulensi yang dikirim ke tamu
            $t->longText('notulensi')->nullable();
            $t->timestamp('notulensi_dikirim_pada')->nullable();

            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tanggal', 'is_dibuka']);
        });

        Schema::create('tamu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kegiatan_tamu_id')->constrained('kegiatan_tamu')->cascadeOnDelete();
            $t->unsignedInteger('nomor_urut');

            $t->string('nama', 180);
            $t->string('asal', 255)->comment('Alamat rumah atau instansi/perusahaan asal');
            $t->string('pekerjaan', 180)->comment('Pekerjaan / jabatan');
            $t->string('email', 180);
            $t->string('tanda_tangan')->comment('Berkas PNG di storage publik');

            // Jejak pengisian — halaman ini terbuka tanpa login.
            $t->string('ip', 45)->nullable();
            $t->string('perangkat', 255)->nullable();
            $t->timestamp('diisi_pada');

            // Status pengiriman notulensi per tamu
            $t->enum('email_status', ['belum', 'menunggu', 'terkirim', 'gagal'])->default('belum');
            $t->timestamp('email_terkirim_pada')->nullable();
            $t->string('email_error', 500)->nullable();

            $t->timestamps();

            $t->unique(['kegiatan_tamu_id', 'nomor_urut'], 'tamu_nomor_unik');
            $t->index(['kegiatan_tamu_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tamu');
        Schema::dropIfExists('kegiatan_tamu');
    }
};
