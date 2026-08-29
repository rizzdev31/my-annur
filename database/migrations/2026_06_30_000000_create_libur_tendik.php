<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libur individu tenaga pendidik (khusus guru mukim asrama).
 *
 * Jam kerja tetap Senin–Ahad; libur ditetapkan PER TANGGAL per individu dan
 * boleh rolling (Jumat minggu ini, Ahad minggu depan, dst). Aditif & aman.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tenaga_pendidik', 'is_mukim')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->boolean('is_mukim')->default(false)->after('is_aktif');
            });
        }

        if (!Schema::hasTable('libur_tendik')) {
            Schema::create('libur_tendik', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
                $t->date('tanggal');
                // rutin = hasil generator rolling, manual = ditambah satuan, tukar = hasil tukar libur
                $t->enum('tipe', ['rutin', 'manual', 'tukar'])->default('manual');
                $t->string('alasan')->nullable();
                // Jejak tukar libur (opsional)
                $t->foreignId('ditukar_dengan_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
                $t->date('ditukar_tanggal')->nullable();
                $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();

                $t->unique(['tenaga_pendidik_id', 'tanggal']);
                $t->index('tanggal');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('libur_tendik');

        if (Schema::hasColumn('tenaga_pendidik', 'is_mukim')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->dropColumn('is_mukim');
            });
        }
    }
};
