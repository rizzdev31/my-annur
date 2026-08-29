<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (!Schema::hasColumn('tugas_jabatan', 'wajib_laporan')) {
                $table->boolean('wajib_laporan')->default(false)->after('frekuensi');
            }
            if (!Schema::hasColumn('tugas_jabatan', 'setting_vakasi_id')) {
                $table->foreignId('setting_vakasi_id')
                      ->nullable()
                      ->after('wajib_laporan')
                      ->constrained('setting_vakasi')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (Schema::hasColumn('tugas_jabatan', 'wajib_laporan')) {
                $table->dropColumn('wajib_laporan');
            }
            if (Schema::hasColumn('tugas_jabatan', 'setting_vakasi_id')) {
                $table->dropForeign(['setting_vakasi_id']);
                $table->dropColumn('setting_vakasi_id');
            }
        });
    }
};