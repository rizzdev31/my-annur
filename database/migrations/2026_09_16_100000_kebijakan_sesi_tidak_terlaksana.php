<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kebijakan sesi mengajar tidak terlaksana (16 Sep 2026):
 *   JP tidak diberikan + tercatat di kinerja, TANPA potongan gaji per sesi.
 *
 * 1. Event notifikasi 'mengajar.tidak_terlaksana' untuk guru piket & guru ybs.
 *    Kanal push ikut dinyalakan: event ini terikat waktu (piket hanya bisa
 *    menolong di hari yang sama), sama alasannya dengan 5 event push lain.
 * 2. Setting potongan bertipe per_sesi_tidak_mengajar dinonaktifkan. Barisnya
 *    tidak dihapus agar jejak kebijakan lama tetap terbaca; perhitungan payroll
 *    juga sudah tidak mengenali tipe ini, jadi mengaktifkannya ulang tidak
 *    berdampak apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setting_notifikasi')
            && !DB::table('setting_notifikasi')->where('event_kode', 'mengajar.tidak_terlaksana')->exists()) {
            DB::table('setting_notifikasi')->insert([
                'event_kode'    => 'mengajar.tidak_terlaksana',
                'nama'          => 'Sesi Mengajar Tidak Terlaksana',
                'kategori'      => 'Mengajar',
                'deskripsi'     => 'Kabari guru piket bertugas & guru ybs saat sesi lewat batas waktu tanpa absen dan jurnal.',
                'wajib'         => true,
                'aktif'         => true,
                'penerima'      => json_encode([]), // diisi command: piket bertugas + guru ybs
                'kanal'         => json_encode(['in_app' => true, 'push' => true]),
                'reminder'      => null,
                'eskalasi'      => null,
                'maks_per_hari' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        if (Schema::hasTable('setting_potongan')) {
            DB::table('setting_potongan')->where('tipe_pemicu', 'per_sesi_tidak_mengajar')
                ->update(['is_aktif' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('setting_notifikasi')) {
            DB::table('setting_notifikasi')->where('event_kode', 'mengajar.tidak_terlaksana')->delete();
        }
        // Setting potongan sengaja tidak diaktifkan ulang: payroll tidak lagi
        // mengenali tipenya, jadi mengembalikan flag saja akan menyesatkan.
    }
};
