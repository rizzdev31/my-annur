<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Setting vakasi ganda (26 Sep 2026).
 *
 * Ada lebih dari satu setting AKTIF berlingkup "semua" untuk aktivitas yang sama:
 *   piket           : Rp 2.000  (berlaku 26 Jun 2026)  &  Rp 20.000 (berlaku 2025)
 *   tugas_tambahan  : Rp 40.000 (berlaku 16 Jun 2026)  &  Rp 20.000 (berlaku 2025)
 *
 * Resolvernya memilih `berlaku_mulai` TERBARU, jadi yang benar-benar dipakai adalah
 * baris terbaru. Baris lama dinonaktifkan agar nilai yang berlaku tidak lagi
 * bergantung pada urutan query — NILAI YANG DIPAKAI TIDAK BERUBAH, hanya
 * kemenduaannya yang dihapus. Setting berlingkup khusus (per jabatan / per guru)
 * tidak disentuh karena justru dimaksudkan menimpa yang umum.
 */
return new class extends Migration
{
    public function up(): void
    {
        $umum = fn ($q) => $q->where('is_aktif', true)
            ->where(fn ($w) => $w->where('berlaku_untuk_semua', true)->orWhere('lingkup', 'semua'))
            ->where(fn ($w) => $w->whereNull('jabatan_ids')->orWhereIn('jabatan_ids', ['[]', 'null']))
            ->where(fn ($w) => $w->whereNull('tenaga_pendidik_ids')->orWhereIn('tenaga_pendidik_ids', ['[]', 'null']));

        $tipe = DB::table('setting_vakasi')->where($umum)
            ->select('tipe_aktivitas')->groupBy('tipe_aktivitas')
            ->havingRaw('COUNT(*) > 1')->pluck('tipe_aktivitas');

        foreach ($tipe as $t) {
            $dipakai = DB::table('setting_vakasi')->where($umum)->where('tipe_aktivitas', $t)
                ->orderByDesc('berlaku_mulai')->orderByDesc('id')->first();
            if (!$dipakai) continue;

            DB::table('setting_vakasi')->where($umum)->where('tipe_aktivitas', $t)
                ->where('id', '!=', $dipakai->id)
                // Tabel ini tidak punya kolom keterangan → cukup dinonaktifkan;
                // jejaknya ada di migration ini.
                ->update(['is_aktif' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Tidak dihidupkan kembali: dua setting aktif berlingkup sama adalah keadaan
        // yang justru ingin dihindari.
    }
};
