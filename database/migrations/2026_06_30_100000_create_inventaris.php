<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaris sekolah (benda, ruang, bangunan, dll) + peminjaman/pemakaian oleh guru.
 * Pemakaian berbasis slot waktu (tanggal + jam mulai–selesai) dengan persetujuan
 * superadmin. Anti double-booking & anti pengajuan kembar di service layer. Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('inventaris')) {
            Schema::create('inventaris', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();
                $t->string('nama');
                $t->enum('kategori', ['ruang', 'bangunan', 'alat', 'elektronik', 'kendaraan', 'lainnya'])
                    ->default('lainnya');
                $t->string('lokasi')->nullable();
                $t->unsignedInteger('jumlah_total')->default(1); // kapasitas paralel (ruang/bangunan = 1)
                $t->string('satuan', 30)->default('unit');
                $t->enum('kondisi', ['baik', 'perlu_perbaikan', 'rusak'])->default('baik');
                $t->boolean('perlu_persetujuan')->default(true); // false → auto-disetujui
                $t->boolean('is_aktif')->default(true);
                $t->text('keterangan')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('peminjaman_inventaris')) {
            Schema::create('peminjaman_inventaris', function (Blueprint $t) {
                $t->id();
                $t->foreignId('inventaris_id')->constrained('inventaris')->cascadeOnDelete();
                $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
                $t->unsignedInteger('jumlah')->default(1);
                $t->string('keperluan');
                $t->date('tanggal');
                $t->time('jam_mulai');
                $t->time('jam_selesai');
                $t->enum('status', ['diajukan', 'disetujui', 'ditolak', 'selesai', 'dibatalkan'])
                    ->default('diajukan');
                $t->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->string('catatan_admin')->nullable();
                $t->timestamp('diputuskan_pada')->nullable();
                $t->timestamps();

                // Lookup cepat untuk deteksi bentrok & rekap.
                $t->index(['inventaris_id', 'tanggal', 'status']);
                $t->index(['tenaga_pendidik_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_inventaris');
        Schema::dropIfExists('inventaris');
    }
};
