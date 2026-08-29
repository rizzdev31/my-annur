<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox — antrian kiriman laporan ke RamahAnak (Smart Habbit).
 * Aditif & aman; tidak menyentuh tabel berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_laporan', function (Blueprint $t) {
            $t->id();
            $t->enum('jenis', ['pelanggaran', 'apresiasi', 'konselor', 'telat']);
            $t->json('payload');                                   // body yang dikirim ke RamahAnak
            $t->string('ref_id')->unique();                        // idempotency
            $t->enum('status', ['pending', 'sent', 'failed', 'duplicate'])->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->json('response')->nullable();                      // respons terakhir RamahAnak
            $t->string('error')->nullable();
            $t->unsignedBigInteger('ramahanak_laporan_id')->nullable();
            $t->string('actor')->nullable();                       // siapa tendik pengirim (jejak)
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_laporan');
    }
};
