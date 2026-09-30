<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran yang memegang kode modul LAMA (satu kode membungkus banyak laporan)
 * di-expand ke kode granular yang baru, supaya hak akses yang sudah diberikan
 * tidak hilang saat katalog modul dipecah.
 *
 * Pola ini sama dengan migration 2026_08_24_* (expand_peran_modul_granular).
 */
return new class extends Migration
{
    /** kode lama → daftar kode baru */
    private array $peta = [
        'gaji_laporan' => [
            'laporan_ringkasan', 'laporan_kehadiran', 'laporan_absensi', 'laporan_mengajar',
            'laporan_pengganti', 'laporan_penggajian', 'laporan_vakasi', 'laporan_guru',
        ],
        'se_laporan' => [
            'se_laporan_index', 'se_laporan_tahfidz', 'se_laporan_tahsin',
            'se_laporan_kehadiran_santri', 'se_laporan_mengajar_quran',
        ],
    ];

    public function up(): void
    {
        foreach ($this->peta as $lama => $baruList) {
            $peranIds = DB::table('peran_modul')->where('modul', $lama)->pluck('peran_id')->unique();
            if ($peranIds->isEmpty()) continue;

            foreach ($peranIds as $peranId) {
                foreach ($baruList as $baru) {
                    $sudah = DB::table('peran_modul')
                        ->where('peran_id', $peranId)->where('modul', $baru)->exists();
                    if (!$sudah) {
                        DB::table('peran_modul')->insert([
                            'peran_id'   => $peranId,
                            'modul'      => $baru,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Kode lama tidak ada lagi di katalog; biarkan barisnya sebagai jejak
            // (Peran::daftarModul() sudah menyaring kode yang tidak dikenal).
        }
    }

    public function down(): void
    {
        foreach ($this->peta as $baruList) {
            DB::table('peran_modul')->whereIn('modul', $baruList)->delete();
        }
    }
};
