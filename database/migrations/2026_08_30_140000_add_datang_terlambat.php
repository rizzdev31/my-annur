<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Izin Datang Terlambat: guru boleh datang sampai jam tertentu tanpa dihitung
 * terlambat. Penanda is_datang_terlambat + jam batas disimpan di kolom jam_mulai
 * (reuse). Check-in <= jam_mulai → HADIR; lewat → terlambat dihitung dari jam_mulai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            if (!Schema::hasColumn('pengajuan_izin', 'is_datang_terlambat')) {
                $table->boolean('is_datang_terlambat')->default(false)->after('is_sementara')
                    ->comment('true = izin datang terlambat (jam batas di jam_mulai)');
            }
        });

        DB::table('setting_jenis_pengajuan')->updateOrInsert(
            ['kode' => 'DATANG_TERLAMBAT'],
            [
                'nama'                          => 'Izin Datang Terlambat',
                'kategori'                      => 'izin',
                'deskripsi'                     => 'Izin datang terlambat pada jam tertentu (perlu persetujuan admin). Jika datang dalam batas jam yang disetujui → tetap dihitung HADIR.',
                'max_hari_per_pengajuan'        => 1,
                'kuota_per_tahun'               => null,
                'min_hari_pengajuan_sebelumnya' => 0,
                'butuh_dokumen'                 => false,
                'auto_approve'                  => false,   // wajib disetujui admin
                'pengaruh_gaji'                 => 'tidak_potong',
                'update_status_kepegawaian'     => false,
                'is_aktif'                      => false,   // aktifkan saat fitur lengkap
                'updated_at'                    => now(),
                'created_at'                    => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            if (Schema::hasColumn('pengajuan_izin', 'is_datang_terlambat')) {
                $table->dropColumn('is_datang_terlambat');
            }
        });
        DB::table('setting_jenis_pengajuan')->where('kode', 'DATANG_TERLAMBAT')->delete();
    }
};
