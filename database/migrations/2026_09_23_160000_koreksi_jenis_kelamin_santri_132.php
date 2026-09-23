<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi salah input jenis kelamin — Madinatul Busro (23 Sep 2026).
 *
 * Santri #132 tercatat 'L' padahal perempuan (dikonfirmasi admin). Ia anggota
 * aktif kelas "Tahsin Level 5" bersama tiga santri putri; satu data keliru ini
 * membuat kelas terbaca CAMPUR (3P : 1L = 75%, di bawah ambang 80%) sehingga
 * seluruh 8 sesinya kehilangan calon guru merangkap — satu-satunya kelas Quran
 * yang begitu dari 152 sesi.
 *
 * Perubahan disinkronkan ke RamahAnak (Smart = master) lewat SantriSyncService.
 */
return new class extends Migration
{
    private const SANTRI_ID = 132;
    private const NIS       = '76291840';

    public function up(): void
    {
        $diubah = DB::table('santri')
            ->where('id', self::SANTRI_ID)
            ->where('nip', self::NIS)
            ->where('jenis_kelamin', 'L')
            ->update(['jenis_kelamin' => 'P', 'updated_at' => now()]);

        if ($diubah) {
            app(\App\Services\SantriSyncService::class)->sync(self::SANTRI_ID);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: 'L' adalah data yang keliru, bukan keadaan sah.
    }
};
