<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override kegiatan penting dari sisi JABATAN: jabatan dengan wajib_kegiatan=false
 * (mis. Satpam, Petugas Kebersihan) dikecualikan dari absensi kegiatan penting,
 * sehingga kinerja piket mereka tidak terpotong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jabatan') && !Schema::hasColumn('jabatan', 'wajib_kegiatan')) {
            Schema::table('jabatan', function (Blueprint $table) {
                $table->boolean('wajib_kegiatan')->default(true)->after('tipe');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jabatan') && Schema::hasColumn('jabatan', 'wajib_kegiatan')) {
            Schema::table('jabatan', fn (Blueprint $table) => $table->dropColumn('wajib_kegiatan'));
        }
    }
};
