<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selaraskan mata pelajaran jadwal dengan JENIS kelasnya.
 *
 * "Tahfidz Putra 4" (kelas tahfidz) terlanjur punya 8 slot jadwal bermapel
 * TAHSIN, sehingga kelasnya muncul di menu Tahsin dan santrinya diarahkan ke
 * penilaian tahsin — bukan setoran hafalan. Penyebabnya sudah ditutup di
 * TahfidzController::generateJadwal (mapel kini wajib setipe dengan kelas);
 * migration ini merapikan baris lama.
 *
 * Sengaja hanya bertindak bila tipe yang benar punya TEPAT SATU mata pelajaran
 * aktif — kalau ambigu, lebih baik dibiarkan untuk diputuskan admin daripada
 * menebak mapel yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['jadwal_mengajar', 'kelas', 'mata_pelajaran'] as $t) {
            if (!Schema::hasTable($t)) return;
        }

        foreach (['tahfidz', 'tahsin'] as $jenis) {
            $mapel = DB::table('mata_pelajaran')->where('tipe', $jenis)->pluck('id');
            if ($mapel->count() !== 1) continue;      // ambigu → jangan menebak

            $kelasIds = DB::table('kelas')->where('jenis', $jenis)->pluck('id');
            if ($kelasIds->isEmpty()) continue;

            // Jadwal kelas ini yang mapelnya BUKAN tipe yang benar.
            DB::table('jadwal_mengajar')
                ->whereIn('kelas_id', $kelasIds)
                ->whereIn('mata_pelajaran_id', function ($q) use ($jenis) {
                    $q->select('id')->from('mata_pelajaran')
                      ->whereIn('tipe', ['tahfidz', 'tahsin'])->where('tipe', '!=', $jenis);
                })
                ->update(['mata_pelajaran_id' => $mapel->first(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai lama memang tidak konsisten dengan jenis kelas.
    }
};
