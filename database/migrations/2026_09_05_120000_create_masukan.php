<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saran & Masukan — kanal keluhan/usulan dari pengguna sistem (guru di PWA)
 * ke superadmin, berbentuk PERCAKAPAN sederhana + lampiran foto bug.
 *
 * Dua tabel:
 *  - `masukan`       : utas (thread) — satu keluhan/usulan, punya status & pemilik.
 *  - `masukan_pesan` : pesan di dalam utas — dari guru, admin, ATAU bot.
 *
 * SIAP UNTUK CHATBOT (Gemini) tanpa migrasi ulang:
 *  - `pengirim_tipe` sudah memuat 'bot' sejak awal, jadi jawaban otomatis
 *    tinggal disisipkan sebagai pesan biasa dan langsung tampil di UI.
 *  - `meta` (JSON per pesan) menampung jejak model: nama model, token,
 *    keyakinan, atau rujukan sumber jawaban.
 *  - `modul` di utas dipakai triase manual sekarang, dan nanti bisa jadi
 *    label rute untuk bot memilih konteks/pengetahuan yang tepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masukan', function (Blueprint $table) {
            $table->id();

            // Pelapor. Sengaja ke `users` (bukan tenaga_pendidik) agar admin pun
            // bisa membuat utas, dan agar tetap valid bila pelapor bukan guru.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('kategori', ['bug', 'saran', 'pertanyaan', 'lainnya'])->default('saran');
            $table->string('judul', 150);

            // Bagian aplikasi yang dikeluhkan (mis. 'absen-mengajar'). Untuk triase,
            // dan kelak jadi petunjuk konteks bagi bot.
            $table->string('modul', 60)->nullable();

            $table->enum('status', ['baru', 'diproses', 'selesai', 'ditolak'])->default('baru');
            $table->enum('prioritas', ['rendah', 'normal', 'tinggi'])->default('normal');

            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();
            $table->text('catatan_admin')->nullable();   // ringkasan tindak lanjut

            // Penanda belum dibaca per sisi — agar lonceng/badge tidak perlu
            // menghitung ulang seluruh pesan setiap kali daftar dibuka.
            $table->boolean('belum_dibaca_admin')->default(true);
            $table->boolean('belum_dibaca_user')->default(false);
            $table->timestamp('pesan_terakhir_pada')->nullable();

            $table->timestamps();

            $table->index(['status', 'pesan_terakhir_pada']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('masukan_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('masukan_id')->constrained('masukan')->cascadeOnDelete();

            // 'bot' sudah disediakan sejak awal — lihat catatan di atas.
            $table->enum('pengirim_tipe', ['guru', 'admin', 'bot']);
            // NULL untuk pesan bot (tidak ada manusia di baliknya).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('isi');
            $table->json('lampiran')->nullable();   // array path foto di disk 'public'
            $table->json('meta')->nullable();       // jejak model bot / data tambahan

            $table->timestamps();

            $table->index(['masukan_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masukan_pesan');
        Schema::dropIfExists('masukan');
    }
};
