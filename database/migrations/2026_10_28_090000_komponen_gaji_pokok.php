<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KOMPONEN GAJI POKOK PER GURU — rincian yang menyusun gaji pokok seseorang.
 *
 * Keputusan 28 Okt 2026: gaji pokok tiap guru terdiri dari sampai lima komponen
 * mengikuti berkas "data fix gaji.xlsx": jam mengajar, jabatan 1, tunjangan
 * pendidikan, jabatan 2, jabatan 3. Tidak semua guru punya semuanya.
 *
 * ══ MENGAPA PER GURU, BUKAN PER JABATAN ══
 * Gaji pokok sebelumnya dihitung dari `setting_gaji_pokok` yang bernominal
 * PER JABATAN (72 baris), dan `gaji_pokok_individu` kosong sama sekali. Data
 * baru tidak bisa diwakili begitu karena satu nama jabatan bernominal berbeda
 * antar guru — terukur 28 Okt 2026:
 *   "Guru"       Rp 248.000 (Binta) · Rp 0 (5 orang) · Rp 682.000 (Yusuf)
 *   "Musyrif"    Rp 310.000 (3 orang) · Rp 750.000 (Muhammad Hafizh)
 *   "Pengabdian" Rp 500.000 (2 orang) · Rp 350.000 (2 orang)
 *   "Kebersihan" Rp 750.000 (2 orang) · Rp 1.500.000 (Noval)
 *
 * ══ MENGAPA JAM MENGAJAR & TUNJANGAN BUKAN JABATAN PALSU ══
 * `jabatan_aktif` dipakai KinerjaCalculationService::komponenTugas() untuk
 * menentukan target tugas jabatan. Menjadikan "Jam Mengajar" sebagai baris
 * jabatan akan memberi 33 guru target tugas yang tidak pernah ada, dan skor
 * kinerja mereka turun karena target fiktif. Karena itu komponen gaji berdiri
 * sendiri: `label` di sini murni keterangan untuk slip, TIDAK menyentuh jabatan
 * yang menentukan kinerja maupun RBAC.
 *
 * ══ MASA BERLAKU ══
 * `berlaku_mulai` membuat perubahan ini hanya mengenai periode Oktober 2026
 * (26 Sep–25 Okt) ke depan; September tidak perlu diperbaiki. Resolvernya
 * dibuat sadar-tanggal agar menghitung ulang slip lama memakai nominal yang
 * berlaku SAAT ITU, bukan nominal hari ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_gaji_pokok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();

            $t->enum('jenis', ['jam_mengajar', 'jabatan', 'tunjangan_pendidikan'])
              ->comment('jam_mengajar & tunjangan_pendidikan bukan jabatan');
            $t->string('label', 150)->nullable()
              ->comment('Nama jabatan atau keterangan; murni untuk slip, bukan jabatan RBAC/kinerja');
            $t->decimal('nominal', 12, 2)->default(0);

            // Urutan mengikuti kolom berkas asal: jam mengajar, jabatan 1,
            // tunjangan pendidikan, jabatan 2, jabatan 3.
            $t->unsignedTinyInteger('urutan')->default(0);

            $t->date('berlaku_mulai');
            $t->date('berlaku_selesai')->nullable();

            // Guru yang nominalnya belum berasal dari berkas resmi: nominalnya
            // disalin dari yang berlaku sekarang agar gajinya tidak jatuh ke nol,
            // dan ditandai supaya mudah ditemukan untuk disesuaikan.
            $t->boolean('perlu_ditinjau')->default(false);
            $t->string('catatan', 300)->nullable();

            $t->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tenaga_pendidik_id', 'berlaku_mulai'], 'kgp_guru_berlaku_idx');
            $t->index(['berlaku_mulai', 'berlaku_selesai'], 'kgp_rentang_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_gaji_pokok');
    }
};
