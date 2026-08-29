<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan template Bot WhatsApp (singleton) — bisa diedit superadmin via UI.
 * Nama bot, salam, footer, dan body template per jenis (placeholder didukung).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('setting_wa')) {
            Schema::create('setting_wa', function (Blueprint $t) {
                $t->id();
                $t->string('nama_bot')->default('PPM An-Nur');
                $t->boolean('pakai_salam')->default(true);
                $t->text('footer')->nullable();
                $t->text('tpl_controlling')->nullable();
                $t->text('tpl_mengajar')->nullable();
                $t->text('tpl_pelanggaran')->nullable();
                $t->text('tpl_apresiasi')->nullable();
                $t->text('tpl_konselor')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_wa');
    }
};
