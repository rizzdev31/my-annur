<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tandai template shift KHUSUS (asrama/satpam) agar Generate Jam Kerja tak
 * menimpa jamnya dengan template reguler. Setting hasil generate mewarisi flag
 * ini dari template induknya, sehingga guru shift khusus terlindungi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            $table->boolean('is_shift_khusus')->default(false)->after('is_template');
        });

        // Tandai template shift khusus berdasar nama (asrama/satpam/shift/keamanan).
        \Illuminate\Support\Facades\DB::table('setting_jam_kerja')
            ->where(function ($q) {
                foreach (['asrama', 'satpam', 'shift', 'keamanan', 'security'] as $kw) {
                    $q->orWhere('nama', 'like', "%{$kw}%");
                }
            })
            ->update(['is_shift_khusus' => true]);
    }

    public function down(): void
    {
        Schema::table('setting_jam_kerja', function (Blueprint $table) {
            $table->dropColumn('is_shift_khusus');
        });
    }
};
