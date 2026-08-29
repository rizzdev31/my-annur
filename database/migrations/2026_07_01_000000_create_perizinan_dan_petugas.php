<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perizinan Santri + delegasi peran petugas.
 *  - petugas_peran : superadmin menunjuk guru sbg petugas (perizinan/kesehatan).
 *                    Dipakai ulang oleh Smart Health nanti.
 *  - izin_santri   : pengajuan izin santri (syar'i / non-syar'i), disetujui petugas.
 * Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('petugas_peran')) {
            Schema::create('petugas_peran', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
                $t->enum('peran', ['perizinan', 'kesehatan']);
                $t->boolean('is_aktif')->default(true);
                $t->foreignId('ditunjuk_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();

                $t->unique(['tenaga_pendidik_id', 'peran']); // 1 guru 1 baris per peran
                $t->index(['peran', 'is_aktif']);
            });
        }

        if (!Schema::hasTable('izin_santri')) {
            Schema::create('izin_santri', function (Blueprint $t) {
                $t->id();
                $t->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $t->enum('jenis', ['syari', 'non_syari']);
                $t->string('alasan');
                $t->date('tanggal_mulai');
                $t->date('tanggal_selesai');
                $t->string('lampiran')->nullable();        // foto/surat (opsional)
                $t->enum('status', ['diajukan', 'disetujui', 'ditolak', 'selesai', 'dibatalkan'])
                    ->default('diajukan');
                // Pengaju: sementara guru (petugas). Disiapkan utk santri (akun menyusul).
                $t->enum('pengaju_tipe', ['guru', 'santri'])->default('guru');
                $t->foreignId('diajukan_oleh')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->foreignId('disetujui_oleh')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->string('catatan_petugas')->nullable(); // alasan tolak / catatan
                $t->timestamp('diputuskan_pada')->nullable();
                // Sumber Smart Health (bila izin pulang otomatis dari kesehatan) — dipakai nanti.
                $t->string('sumber')->nullable();          // mis. 'smart_health'
                $t->timestamps();

                $t->index(['santri_id', 'status']);
                $t->index(['status', 'tanggal_mulai']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_santri');
        Schema::dropIfExists('petugas_peran');
    }
};
