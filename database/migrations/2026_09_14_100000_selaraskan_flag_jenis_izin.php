<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selaraskan penanda izin dengan JENIS-nya.
 *
 * Form izin umum dulu tidak menyetel `is_datang_terlambat` / `is_sementara`,
 * sehingga guru yang memilih "Izin Datang Terlambat" dari daftar tersimpan
 * sebagai izin SEHARI PENUH. Akibatnya ia ikut hilang dari daftar peserta
 * kegiatan (mis. Sholat Dzuhur) padahal tetap masuk kerja.
 *
 * Penyebabnya sudah ditutup di PengajuanIzinService::buat() — penanda kini
 * diturunkan dari kode jenis. Migration ini merapikan baris lama saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pengajuan_izin') || !Schema::hasTable('setting_jenis_pengajuan')) return;

        foreach ([
            'DATANG_TERLAMBAT' => 'is_datang_terlambat',
            'IZIN_SEMENTARA'   => 'is_sementara',
        ] as $kode => $kolom) {
            $jenisId = DB::table('setting_jenis_pengajuan')->where('kode', $kode)->value('id');
            if (!$jenisId) continue;

            DB::table('pengajuan_izin')
                ->where('setting_jenis_pengajuan_id', $jenisId)
                ->where($kolom, false)
                ->update([$kolom => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai lama memang tidak konsisten dengan jenisnya.
    }
};
