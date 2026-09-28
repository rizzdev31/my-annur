<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan keadaan skor kinerja SAAT INI sebagai versi pertama di riwayat.
 *
 * Nilai yang sudah tertimpa sebelum fitur riwayat ada memang tidak bisa
 * dipulihkan, tapi keadaan hari ini bisa diamankan supaya tiap perubahan
 * berikutnya punya pembanding ("dari berapa ke berapa").
 */
return new class extends Migration
{
    public function up(): void
    {
        $sudahAda = DB::table('riwayat_rekap_kinerja')->where('sebab', 'awal')->exists();
        if ($sudahAda) return;

        $now = now();
        DB::table('rekap_kinerja_bulanan')->orderBy('id')->chunk(200, function ($rows) use ($now) {
            $baris = [];
            foreach ($rows as $r) {
                $baris[] = [
                    'rekap_kinerja_bulanan_id' => $r->id,
                    'tenaga_pendidik_id'       => $r->tenaga_pendidik_id,
                    'bulan'                    => $r->bulan,
                    'tahun'                    => $r->tahun,
                    'skor_total'               => $r->skor_total,
                    'skor_absensi'             => $r->skor_absensi,
                    'skor_tugas'               => $r->skor_tugas,
                    'skor_administrasi'        => $r->skor_administrasi,
                    'skor_piket'               => $r->skor_piket,
                    'skor_total_baru'          => $r->skor_total,
                    'sudah_dikunci_lama'       => (bool) $r->sudah_dikunci,
                    'catatan_superadmin_lama'  => $r->catatan_superadmin,
                    'data_lama'                => json_encode((array) $r),
                    'sebab'                    => 'awal',
                    'alasan'                   => 'Keadaan tersimpan saat fitur riwayat mulai berlaku',
                    'diubah_oleh'              => null,
                    'setting_kinerja_id'       => $r->setting_kinerja_id,
                    'created_at'               => $r->updated_at ?? $now,
                    'updated_at'               => $now,
                ];
            }
            if ($baris) DB::table('riwayat_rekap_kinerja')->insert($baris);
        });
    }

    public function down(): void
    {
        DB::table('riwayat_rekap_kinerja')->where('sebab', 'awal')->delete();
    }
};
