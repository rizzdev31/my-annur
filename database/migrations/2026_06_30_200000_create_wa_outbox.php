<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox notifikasi WhatsApp (Fonnte) — pola sama outbox_laporan: idempotent via
 * ref_id, retry via queue, tahan gangguan jaringan. Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wa_outbox')) {
            Schema::create('wa_outbox', function (Blueprint $t) {
                $t->id();
                $t->string('ref_id')->unique();           // anti-dobel (mis. WA-CTRL-{absensi_id})
                $t->string('tujuan', 30)->nullable();      // nomor WA (62xxx)
                $t->foreignId('santri_id')->nullable()->constrained('santri')->nullOnDelete();
                $t->string('jenis', 30);                   // controlling | mengajar | pelanggaran | apresiasi | konselor
                $t->text('pesan');
                $t->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
                $t->json('provider_response')->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->string('error')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();

                $t->index('status');
                $t->index(['santri_id', 'jenis']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_outbox');
    }
};
