<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event notifikasi 'eskalasi.pimpinan' — ringkasan anomali harian yang dikirim
 * ke PENGAWAS (pimpinan) sesuai modul & cakupan yang diberikan superadmin.
 * Penerima diisi oleh command (bukan katalog peran).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;
        if (DB::table('setting_notifikasi')->where('event_kode', 'eskalasi.pimpinan')->exists()) return;

        DB::table('setting_notifikasi')->insert([
            'event_kode'    => 'eskalasi.pimpinan',
            'nama'          => 'Eskalasi ke Pimpinan',
            'kategori'      => 'Monitoring',
            'deskripsi'     => 'Ringkasan anomali (belum absen, sesi mengajar terlewat, izin menunggu, kinerja rendah) untuk pengawas/pimpinan.',
            'wajib'         => false,
            'aktif'         => true,
            'penerima'      => json_encode([]),   // diisi command: pengawas terkait
            'kanal'         => json_encode(['in_app' => true]),
            'reminder'      => null,
            'eskalasi'      => null,
            'maks_per_hari' => 8,                 // 4 jenis + margin
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('setting_notifikasi')) {
            DB::table('setting_notifikasi')->where('event_kode', 'eskalasi.pimpinan')->delete();
        }
    }
};
