<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event 'kelas.anggota_berubah': kabari pengampu kelas tahfidz/tahsin saat santri
 * kelasnya bertambah/berkurang (panel Atur Santri, edit santri, naik kelas).
 *
 * Hanya lonceng (tanpa push): bukan hal mendesak, dan satu pengaturan kelas bisa
 * menyentuh beberapa kelas sekaligus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;
        if (DB::table('setting_notifikasi')->where('event_kode', 'kelas.anggota_berubah')->exists()) return;

        DB::table('setting_notifikasi')->insert([
            'event_kode'    => 'kelas.anggota_berubah',
            'nama'          => 'Perubahan Santri Kelas',
            'kategori'      => 'Kelas',
            'deskripsi'     => 'Kabari pengampu kelas tahfidz/tahsin saat santri kelasnya masuk atau keluar.',
            'wajib'         => false,
            'aktif'         => true,
            'penerima'      => json_encode([]), // diisi service: pengampu kelas terdampak
            'kanal'         => json_encode(['in_app' => true]),
            'reminder'      => null,
            'eskalasi'      => null,
            'maks_per_hari' => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('setting_notifikasi')) {
            DB::table('setting_notifikasi')->where('event_kode', 'kelas.anggota_berubah')->delete();
        }
    }
};
