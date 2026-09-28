<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bobot sub-komponen Administrasi: Log Kerja → Laporan Mengajar.
 *
 * Log kerja harian tidak dipakai di lapangan (tabel log_kerja_harian kosong),
 * sehingga sub-skornya selalu netral 100 dan MENOPANG skor administrasi:
 * guru yang tidak pernah mengisi jurnal tetap mendapat 40% bagian administrasi
 * secara gratis. Keputusan pengguna (28 Sep 2026): seluruh bobot log kerja
 * dialihkan ke laporan mengajar, sehingga administrasi murni menilai jurnal
 * mengajar. Log kerja tetap ada di sistem (bobot 0) dan bisa dihidupkan lagi
 * kapan pun dengan mengubah setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('setting_kinerja')->where('bobot_log_kerja', '>', 0)->get() as $s) {
            DB::table('setting_kinerja')->where('id', $s->id)->update([
                'bobot_laporan_mengajar' => (float) $s->bobot_laporan_mengajar + (float) $s->bobot_log_kerja,
                'bobot_log_kerja'        => 0,
                'updated_at'             => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Kembalikan komposisi lama 60/40 untuk setting yang log-nya masih 0.
        DB::table('setting_kinerja')->where('bobot_log_kerja', 0)->update([
            'bobot_laporan_mengajar' => 60,
            'bobot_log_kerja'        => 40,
            'updated_at'             => now(),
        ]);
    }
};
