<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Health — pelaporan & pemantauan kesehatan santri.
 *  - smart_health_laporan     : laporan sakit (deskripsi + foto), divalidasi Bagian Kesehatan.
 *  - smart_health_pengecekan  : log pemantauan (Sembuh / Pengecekan Hari 1–3 / Darurat).
 *  + wa_outbox.media_url       : dukungan kirim foto via Fonnte.
 * Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('smart_health_laporan')) {
            Schema::create('smart_health_laporan', function (Blueprint $t) {
                $t->id();
                $t->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
                $t->foreignId('pelapor_tenaga_pendidik_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->string('deskripsi_penyakit');
                $t->string('foto')->nullable();
                $t->enum('status', ['menunggu', 'ditolak', 'dalam_pengecekan', 'selesai'])->default('menunggu');
                $t->enum('kondisi_akhir', ['sembuh', 'izin_pulang', 'darurat'])->nullable();
                $t->foreignId('disetujui_oleh')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->timestamp('disetujui_pada')->nullable();
                $t->string('catatan')->nullable();
                $t->timestamps();

                $t->index(['status', 'created_at']);
                $t->index(['santri_id', 'status']);
            });
        }

        if (!Schema::hasTable('smart_health_pengecekan')) {
            Schema::create('smart_health_pengecekan', function (Blueprint $t) {
                $t->id();
                $t->foreignId('laporan_id')->constrained('smart_health_laporan')->cascadeOnDelete();
                $t->unsignedTinyInteger('hari_ke')->nullable();          // 1..3 utk pengecekan
                $t->enum('keputusan', ['sembuh', 'pengecekan', 'darurat']);
                $t->string('catatan')->nullable();
                $t->foreignId('oleh_tenaga_pendidik_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->date('tanggal');
                $t->timestamps();

                $t->index('laporan_id');
            });
        }

        if (!Schema::hasColumn('wa_outbox', 'media_url')) {
            Schema::table('wa_outbox', function (Blueprint $t) {
                $t->string('media_url')->nullable()->after('pesan'); // foto/gambar utk Fonnte
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_health_pengecekan');
        Schema::dropIfExists('smart_health_laporan');
        if (Schema::hasColumn('wa_outbox', 'media_url')) {
            Schema::table('wa_outbox', function (Blueprint $t) {
                $t->dropColumn('media_url');
            });
        }
    }
};
