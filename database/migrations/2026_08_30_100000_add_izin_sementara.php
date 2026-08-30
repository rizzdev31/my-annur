<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Izin Sementara (Tahap 1 — fondasi).
 *
 * Menambah dukungan izin BERBASIS JAM (partial-day) di tabel pengajuan_izin:
 *   - jam_mulai / jam_selesai : window di dalam satu hari (null = izin sehari penuh, perilaku lama)
 *   - is_sementara            : penanda cepat izin sementara
 *
 * Kategori TIDAK ditambah ke enum (hindari ALTER enum). Pembeda = flag is_sementara.
 * Jenis "Izin Sementara" di-seed di sini (idempoten) agar tersedia tanpa db:seed ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            if (!Schema::hasColumn('pengajuan_izin', 'jam_mulai')) {
                $table->time('jam_mulai')->nullable()->after('tanggal_selesai')
                    ->comment('Izin sementara: awal window (null = izin sehari penuh)');
            }
            if (!Schema::hasColumn('pengajuan_izin', 'jam_selesai')) {
                $table->time('jam_selesai')->nullable()->after('jam_mulai')
                    ->comment('Izin sementara: akhir window');
            }
            if (!Schema::hasColumn('pengajuan_izin', 'is_sementara')) {
                $table->boolean('is_sementara')->default(false)->after('jam_selesai')
                    ->comment('true = izin berbasis jam (partial-day), guru tetap hadir');
            }
        });

        // Seed jenis "Izin Sementara" (idempoten) — kategori tetap 'izin'.
        DB::table('setting_jenis_pengajuan')->updateOrInsert(
            ['kode' => 'IZIN_SEMENTARA'],
            [
                'nama'                          => 'Izin Sementara',
                'kategori'                      => 'izin',
                'deskripsi'                     => 'Izin meninggalkan tempat sementara di tengah jam kerja (berbasis jam). Guru tetap hadir; sesi mengajar yang beririsan dialihkan ke pengganti.',
                'max_hari_per_pengajuan'        => 1,
                'kuota_per_tahun'               => null,
                'min_hari_pengajuan_sebelumnya' => 0,
                'butuh_dokumen'                 => false,
                'auto_approve'                  => true,
                'pengaruh_gaji'                 => 'tidak_potong',
                'update_status_kepegawaian'     => false,
                // NONAKTIF dulu (Tahap 1) → tak muncul sbg pilihan izin sampai alur
                // lengkap (Tahap 3). Aktifkan via migration/seed saat feature siap.
                'is_aktif'                      => false,
                'updated_at'                    => now(),
                'created_at'                    => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            foreach (['jam_mulai', 'jam_selesai', 'is_sementara'] as $col) {
                if (Schema::hasColumn('pengajuan_izin', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::table('setting_jenis_pengajuan')->where('kode', 'IZIN_SEMENTARA')->delete();
    }
};
