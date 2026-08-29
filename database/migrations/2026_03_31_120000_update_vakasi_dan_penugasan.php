<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. setting_vakasi: tambah kolom per-individu & lingkup ───────────
        Schema::table('setting_vakasi', function (Blueprint $table) {
            $table->json('tenaga_pendidik_ids')
                ->nullable()
                ->after('jabatan_ids')
                ->comment('Array ID guru yang menerima vakasi ini (null = tidak filter per individu)');

            $table->enum('lingkup', ['semua', 'per_jabatan', 'per_individu', 'custom'])
                ->default('semua')
                ->after('berlaku_untuk_semua')
                ->comment('semua=semua guru | per_jabatan=jabatan tertentu | per_individu=individu | custom=kombinasi jabatan+individu');
        });

        // ── 2. penugasan_tambahan: vakasi & setting override per penerima ────
        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            $table->unsignedBigInteger('setting_vakasi_id')
                ->nullable()
                ->after('status_pengerjaan')
                ->comment('Setting vakasi khusus penerima ini (lebih spesifik dari tugas)');

            $table->decimal('vakasi_override', 12, 2)
                ->nullable()
                ->after('setting_vakasi_id')
                ->comment('Override nominal vakasi untuk penerima ini saja');

            $table->foreign('setting_vakasi_id')
                ->references('id')->on('setting_vakasi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('setting_vakasi', function (Blueprint $table) {
            $table->dropColumn(['tenaga_pendidik_ids', 'lingkup']);
        });

        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            $table->dropForeign(['setting_vakasi_id']);
            $table->dropColumn(['setting_vakasi_id', 'vakasi_override']);
        });
    }
};