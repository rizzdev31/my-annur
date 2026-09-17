<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penyesuaian khusus kelas "Tahsin Level 5" (id 26, pengampu Khoirun Nisa') — 17 Sep 2026.
 *
 * Admin menata ulang kelas ini menjadi Level 5. Tiga santri masih tercatat Level 4
 * (semua materi Level 4 sudah lulus, ujian tasnif Level 4 masih menunggu dinilai),
 * sehingga di PWA mereka masih melihat materi Level 4. Atas permintaan admin, ketiganya
 * dinaikkan ke Level 5. Tasnif Level 4 yang tertunda tetap berlaku (penguji tetap menilai
 * & mendapat vakasi); TasnifService tidak menaikkan level lagi bila santri sudah di atasnya.
 *
 * Penjaga: hanya bila kelas 26 masih Level 5, santri masih anggota aktifnya, dan levelnya masih 4.
 */
return new class extends Migration
{
    private const KELAS_ID = 26;
    private const SANTRI   = [24, 26, 132]; // Mikayla Nur Azalea, Non Alifah Ramadhani, Madinatul Busro

    public function up(): void
    {
        if ((int) DB::table('kelas')->where('id', self::KELAS_ID)->value('level_tahsin') !== 5) return;

        $anggota = DB::table('kelas_santri')->where('kelas_id', self::KELAS_ID)->where('is_aktif', true)
            ->whereIn('santri_id', self::SANTRI)->pluck('santri_id');

        DB::table('santri')->whereIn('id', $anggota)->where('tahsin_level', 4)
            ->update(['tahsin_level' => 5, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis: bisa saja santri sudah mulai dinilai di Level 5.
    }
};
