<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Controlling (Smart Habbit) — absensi kegiatan santri terjadwal per periode 1 bulan.
 * Santri TIDAK login; absen via scan barcode (NIP) oleh petugas/device. Jawaban: hadir/telat/alpha.
 * Telat dikirim ke RamahAnak via outbox. Semua aditif & aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Periode 1 bulan
        Schema::create('controlling_periode', function (Blueprint $t) {
            $t->id();
            $t->string('nama');                       // mis. "Juni 2026"
            $t->unsignedTinyInteger('bulan');         // 1..12
            $t->unsignedSmallInteger('tahun');
            $t->boolean('is_aktif')->default(false);
            $t->timestamps();
            $t->index(['bulan', 'tahun']);
        });

        // Master kegiatan
        Schema::create('controlling_kegiatan', function (Blueprint $t) {
            $t->id();
            $t->string('nama');                       // mis. "Sholat Subuh", "Absen Masuk", "Kajian Ahad"
            $t->enum('jenis', ['harian', 'kajian'])->default('harian');
            $t->string('keterangan')->nullable();
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
        });

        // Jadwal kegiatan per periode (hari + window jam)
        Schema::create('controlling_jadwal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('periode_id')->constrained('controlling_periode')->cascadeOnDelete();
            $t->foreignId('kegiatan_id')->constrained('controlling_kegiatan')->cascadeOnDelete();
            $t->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad']);
            $t->time('jam_mulai');
            $t->time('jam_selesai');
            $t->unsignedSmallInteger('ambang_telat_menit')->default(0); // Hadir bila scan ≤ jam_mulai+ambang; else Telat
            $t->boolean('is_aktif')->default(true);
            $t->timestamps();
            $t->index(['periode_id', 'hari']);
        });

        // Hasil absensi per santri per jadwal per tanggal
        Schema::create('controlling_absensi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('periode_id')->constrained('controlling_periode')->cascadeOnDelete();
            $t->foreignId('jadwal_id')->constrained('controlling_jadwal')->cascadeOnDelete();
            $t->foreignId('kegiatan_id')->constrained('controlling_kegiatan')->cascadeOnDelete();
            $t->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $t->date('tanggal');
            $t->enum('status', ['hadir', 'telat', 'alpha']);
            $t->time('jam_scan')->nullable();          // null untuk alpha (auto)
            $t->string('petugas')->nullable();         // siapa/perangkat yang scan
            $t->string('keterangan')->nullable();
            $t->boolean('dikirim_outbox')->default(false); // jejak telat→RamahAnak
            $t->timestamps();
            $t->unique(['jadwal_id', 'santri_id', 'tanggal']); // 1 santri 1 kegiatan/hari
            $t->index(['santri_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlling_absensi');
        Schema::dropIfExists('controlling_jadwal');
        Schema::dropIfExists('controlling_kegiatan');
        Schema::dropIfExists('controlling_periode');
    }
};
