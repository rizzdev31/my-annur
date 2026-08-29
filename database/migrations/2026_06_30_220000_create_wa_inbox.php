<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kotak masuk WhatsApp (balasan wali) via webhook incoming Fonnte.
 * Fondasi untuk fitur dua-arah selanjutnya. Aditif.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wa_inbox')) {
            Schema::create('wa_inbox', function (Blueprint $t) {
                $t->id();
                $t->string('device', 30)->nullable();       // nomor device penerima (bot)
                $t->string('pengirim', 30);                  // nomor WA pengirim (wali)
                $t->string('nama')->nullable();              // nama profil WA pengirim
                $t->foreignId('santri_id')->nullable()->constrained('santri')->nullOnDelete();
                $t->text('pesan')->nullable();
                $t->string('media_url')->nullable();
                $t->json('raw')->nullable();                 // payload penuh (untuk fitur lanjutan)
                $t->boolean('dibaca')->default(false);
                $t->timestamp('diterima_pada')->nullable();
                $t->timestamps();

                $t->index('pengirim');
                $t->index(['dibaca', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_inbox');
    }
};
