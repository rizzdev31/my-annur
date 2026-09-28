<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian piket: dari "jumlah poin" menjadi "rasio berbatas".
 *
 * Masalah desain lama: penyesuaian = Σpoin apresiasi − Σpoin catatan, dengan
 * poin kegiatan wajib ±1 per kejadian. Karena kegiatan wajib bisa 40+ kali
 * sebulan, satu "penunjang" berayun −42..+33 — lebih lebar daripada seluruh
 * komponen inti, dan tidak sebanding antar guru (kesempatan 10 vs 42 kali).
 * Selain itu `bobot_piket` yang tampil di setting TIDAK pernah dipakai rumus.
 *
 * Desain baru (keputusan pengguna 28 Sep 2026):
 *   - Kedisiplinan kegiatan wajib dinilai dari PERSENTASE kehadiran bulan itu
 *     (band), bukan jumlah kejadian → sebanding antar guru.
 *   - Catatan/apresiasi guru piket ±1 per kejadian, berbatas sendiri.
 *   - Keduanya berbatas, totalnya pun berbatas → benar-benar penyesuaian.
 *   - Berlaku hanya bila kesempatannya cukup (sampel minimum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting_kinerja', function (Blueprint $table) {
            $table->decimal('maks_adj_kegiatan', 5, 2)->default(6)->after('skor_min_piket')
                  ->comment('Batas penyesuaian dari kedisiplinan kegiatan wajib (±)');
            $table->decimal('maks_adj_piket', 5, 2)->default(4)->after('maks_adj_kegiatan')
                  ->comment('Batas penyesuaian dari catatan/apresiasi guru piket (±)');
            $table->decimal('maks_adj_total', 5, 2)->default(10)->after('maks_adj_piket')
                  ->comment('Batas gabungan kedua penyesuaian (±)');
            $table->unsignedSmallInteger('min_kesempatan_kegiatan')->default(5)->after('maks_adj_total')
                  ->comment('Kegiatan wajib minimum agar rasionya dinilai');
            // Ambang band kehadiran kegiatan wajib (persen)
            $table->unsignedTinyInteger('ambang_kegiatan_baik')->default(90)->after('min_kesempatan_kegiatan');
            $table->unsignedTinyInteger('ambang_kegiatan_cukup')->default(75)->after('ambang_kegiatan_baik');
            $table->unsignedTinyInteger('ambang_kegiatan_netral')->default(60)->after('ambang_kegiatan_cukup');
            $table->unsignedTinyInteger('ambang_kegiatan_kurang')->default(40)->after('ambang_kegiatan_netral');
        });

        // bobot_piket tidak lagi bermakna (piket bukan komponen berbobot) → 0
        // supaya setting tidak menampilkan angka yang tak dipakai rumus.
        DB::table('setting_kinerja')->update(['bobot_piket' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('setting_kinerja', function (Blueprint $table) {
            $table->dropColumn([
                'maks_adj_kegiatan', 'maks_adj_piket', 'maks_adj_total', 'min_kesempatan_kegiatan',
                'ambang_kegiatan_baik', 'ambang_kegiatan_cukup', 'ambang_kegiatan_netral', 'ambang_kegiatan_kurang',
            ]);
        });
        DB::table('setting_kinerja')->update(['bobot_piket' => 15]);
    }
};
