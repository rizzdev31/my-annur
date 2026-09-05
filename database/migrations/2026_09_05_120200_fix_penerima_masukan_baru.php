<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi penerima event 'masukan.baru'.
 *
 * Seed awal memakai token 'super_admin' yang TIDAK dikenal
 * NotifikasiService::resolvePenerima() (token yang benar: 'admin'), sehingga
 * daftar penerima kosong dan notifikasi masukan baru tidak pernah terkirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;

        DB::table('setting_notifikasi')
            ->where('event_kode', 'masukan.baru')
            ->update(['penerima' => json_encode(['admin']), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai lama memang tidak berfungsi.
    }
};
