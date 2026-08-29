<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah event notifikasi 'kegiatan.reminder' (pengingat guru piket saat jam
 * Kegiatan Penting). Penerima diisi command (guru piket bertugas hari itu).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;
        if (DB::table('setting_notifikasi')->where('event_kode', 'kegiatan.reminder')->exists()) return;

        DB::table('setting_notifikasi')->insert([
            'event_kode'    => 'kegiatan.reminder',
            'nama'          => 'Pengingat Kegiatan Penting',
            'kategori'      => 'Kegiatan',
            'deskripsi'     => 'Ingatkan guru piket mencatat kehadiran saat jam kegiatan penting (mis. Sholat Dzuhur).',
            'wajib'         => true,
            'aktif'         => true,
            'penerima'      => json_encode([]), // diisi command: guru piket bertugas
            'kanal'         => json_encode(['in_app' => true]),
            'reminder'      => json_encode(['sebelum_menit' => 5, 'ulang_menit' => 20, 'batas_menit' => 60]),
            'eskalasi'      => null,
            'maks_per_hari' => 4,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('setting_notifikasi')) {
            DB::table('setting_notifikasi')->where('event_kode', 'kegiatan.reminder')->delete();
        }
    }
};
