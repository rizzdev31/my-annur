<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perluas kode modul kasar lama pada `peran_modul` menjadi kode granular baru
 * (lihat config/modul.php). Idempoten & aman: peran yang punya kode kasar tetap
 * memegang semua fitur yang sama, kini sebagai kode granular satu-satu.
 */
return new class extends Migration
{
    /** Kode kasar lama → kumpulan kode granular yang harus ada bila kode lama dipegang. */
    private array $map = [
        'absensi'         => ['absensi', 'monitoring'],
        'tugas'           => ['tugas_jabatan', 'tugas_tambahan', 'absensi_kegiatan', 'lembur'],
        'penggajian'      => ['gaji_periode', 'gaji_data', 'gaji_laporan', 'kalender_libur'],
        'smart_education' => ['se_santri', 'se_kelas', 'se_ekskul', 'se_jurnal', 'se_tahfidz', 'se_tahsin', 'se_laporan'],
    ];

    public function up(): void
    {
        foreach ($this->map as $lama => $granular) {
            $peranIds = DB::table('peran_modul')->where('modul', $lama)->pluck('peran_id')->unique();
            foreach ($peranIds as $pid) {
                foreach ($granular as $kode) {
                    DB::table('peran_modul')->insertOrIgnore([
                        'peran_id'   => $pid,
                        'modul'      => $kode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Bersihkan kode yang sudah tak ada di config (mis. smart_education/penggajian/tugas).
        $valid = array_keys(config('modul.daftar', []));
        if ($valid) {
            DB::table('peran_modul')->whereNotIn('modul', $valid)->delete();
        }
    }

    public function down(): void
    {
        // Additive/one-way: tidak menciutkan kembali kode granular ke kode kasar.
    }
};
