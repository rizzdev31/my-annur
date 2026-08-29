<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perpindahan kelas santri — additive.
 * kelas_santri jadi HISTORI: tiap baris = 1 masa keanggotaan santri di 1 kelas (1 tahun ajaran).
 *  - is_aktif = keanggotaan SEKARANG (per jenis kelas: sekolah/tahfidz/tahsin bisa aktif bersamaan)
 *  - tanggal_keluar = saat santri naik/pindah kelas (baris lama TETAP disimpan → histori)
 *  - tahun_ajaran_id = denormalisasi dari kelas (query histori cepat & anti-ambigu)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_santri', function (Blueprint $t) {
            $t->date('tanggal_keluar')->nullable()->after('tanggal_masuk');
            $t->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('tanggal_keluar');
            $t->string('keterangan', 150)->nullable()->after('tahun_ajaran_id');
            $t->index(['santri_id', 'is_aktif']);
            $t->index('tahun_ajaran_id');
        });

        // Backfill tahun_ajaran_id dari kelas untuk baris lama.
        DB::statement('UPDATE kelas_santri ks JOIN kelas k ON k.id = ks.kelas_id
            SET ks.tahun_ajaran_id = k.tahun_ajaran_id
            WHERE ks.tahun_ajaran_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('kelas_santri', function (Blueprint $t) {
            $t->dropIndex(['santri_id', 'is_aktif']);
            $t->dropIndex(['tahun_ajaran_id']);
            $t->dropColumn(['tanggal_keluar', 'tahun_ajaran_id', 'keterangan']);
        });
    }
};
