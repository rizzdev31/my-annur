<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_kinerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->default('Setting Kinerja Default');
            $table->boolean('is_aktif')->default(true);
            $table->boolean('is_default')->default(false);

            // ═══════════════════════════════════════════════════
            // KOMPONEN 1: ABSENSI (default bobot 50%)
            // ═══════════════════════════════════════════════════
            $table->decimal('bobot_absensi', 5, 2)->default(50.00)
                ->comment('Bobot total komponen absensi (sum 3 komponen = 100)');

            // Sub-bobot dalam komponen absensi (total = 100)
            $table->decimal('bobot_absensi_harian',   5, 2)->default(70.00)
                ->comment('Sub-bobot kehadiran harian (dari 100% bobot_absensi)');
            $table->decimal('bobot_absensi_mengajar', 5, 2)->default(30.00)
                ->comment('Sub-bobot laporan mengajar (dari 100% bobot_absensi)');

            // Nilai per status kehadiran — bisa diatur admin (0-100)
            // Rumus skor harian: sum(nilai_status × jumlah) / (hari_kerja × 100) × 100
            $table->decimal('nilai_hadir',     5, 2)->default(100.00)
                ->comment('Nilai per hari status hadir');
            $table->decimal('nilai_terlambat', 5, 2)->default(75.00)
                ->comment('Nilai per hari status terlambat');
            $table->decimal('nilai_izin',      5, 2)->default(70.00)
                ->comment('Nilai per hari status izin/izin_sakit');
            $table->decimal('nilai_sakit',     5, 2)->default(80.00)
                ->comment('Nilai per hari status sakit');
            $table->decimal('nilai_dinas_luar',5, 2)->default(100.00)
                ->comment('Nilai per hari status dinas_luar');
            $table->decimal('nilai_alfa',      5, 2)->default(0.00)
                ->comment('Nilai per hari status alfa (tidak hadir tanpa keterangan)');
            // libur = dikecualikan dari hari kerja, tidak masuk perhitungan

            // Penalty tambahan keterlambatan (opsional di atas nilai_terlambat)
            $table->boolean('hitung_penalty_terlambat')->default(false)
                ->comment('Jika true, terlambat kena penalty tambahan di atas nilai_terlambat');
            $table->integer('toleransi_terlambat_menit')->default(0)
                ->comment('Terlambat <= ini tidak kena penalty');
            $table->decimal('penalty_per_terlambat', 5, 2)->default(5.00)
                ->comment('Pengurangan skor per kejadian terlambat (di atas nilai_terlambat)');
            $table->decimal('max_penalty_terlambat', 5, 2)->default(20.00)
                ->comment('Maksimal total penalty terlambat sebulan');

            // ═══════════════════════════════════════════════════
            // KOMPONEN 2: TUGAS (default bobot 30%)
            // ═══════════════════════════════════════════════════
            $table->decimal('bobot_tugas', 5, 2)->default(30.00)
                ->comment('Bobot total komponen tugas');

            // Sub-bobot dalam komponen tugas (total = 100)
            $table->decimal('bobot_tugas_tambahan', 5, 2)->default(60.00)
                ->comment('Sub-bobot penugasan tambahan dari admin/jabatan');
            $table->decimal('bobot_tugas_jabatan',  5, 2)->default(40.00)
                ->comment('Sub-bobot realisasi tugas jabatan');

            // Perilaku jika tidak ada tugas di bulan tsb
            $table->enum('jika_tidak_ada_tugas', ['sempurna', 'nol', 'skip'])
                ->default('sempurna')
                ->comment('sempurna=100, nol=0, skip=tidak dihitung');

            // ═══════════════════════════════════════════════════
            // KOMPONEN 3: ADMINISTRASI (default bobot 20%)
            // ═══════════════════════════════════════════════════
            $table->decimal('bobot_administrasi', 5, 2)->default(20.00)
                ->comment('Bobot total komponen administrasi');

            // Sub-bobot dalam komponen administrasi (total = 100)
            $table->decimal('bobot_laporan_mengajar', 5, 2)->default(60.00)
                ->comment('Sub-bobot: absen mengajar + isi materi per sesi');
            $table->decimal('bobot_log_kerja',        5, 2)->default(40.00)
                ->comment('Sub-bobot: log kerja harian yang di-submit');

            // Cara hitung laporan mengajar:
            // skor = (sesi_dilaporkan_dengan_materi / sesi_jadwal_aktif) × 100
            // sesi_jadwal_aktif = semua jadwal mengajar yang aktif hari itu
            $table->decimal('target_log_per_hari', 3, 1)->default(1.0)
                ->comment('Target submit log kerja harian per hari kerja');

            // ═══════════════════════════════════════════════════
            // GRADE (bisa diubah admin)
            // ═══════════════════════════════════════════════════
            $table->decimal('grade_a', 5, 2)->default(90.00);
            $table->decimal('grade_b', 5, 2)->default(75.00);
            $table->decimal('grade_c', 5, 2)->default(60.00);
            $table->decimal('grade_d', 5, 2)->default(40.00);

            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Update rekap_kinerja_bulanan: tambah kolom detail ────────────────
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            $cols = [
                // Skor per komponen
                'skor_absensi'             => 'decimal:5,2',
                'skor_tugas'               => 'decimal:5,2',
                'skor_administrasi'        => 'decimal:5,2',
                'setting_kinerja_id'       => 'bigint',
                // Data mentah absensi
                'total_hadir'              => 'integer',
                'total_terlambat'          => 'integer',
                'total_izin'               => 'integer',
                'total_sakit'              => 'integer',
                'total_alfa'               => 'integer',
                'total_dinas_luar'         => 'integer',
                'total_hari_kerja'         => 'integer',
                // Data mentah mengajar
                'total_sesi_jadwal'        => 'integer',
                'total_sesi_terlaksana'    => 'integer',
                'total_sesi_dilaporkan'    => 'integer',
                'total_jp_jadwal'          => 'integer',
                'total_jp_terlaksana'      => 'integer',
                // Data mentah tugas
                'total_realisasi_jabatan'  => 'integer',
                'total_realisasi_disetujui'=> 'integer',
            ];

            foreach ($cols as $col => $type) {
                if (!Schema::hasColumn('rekap_kinerja_bulanan', $col)) {
                    if ($type === 'bigint') {
                        $table->unsignedBigInteger($col)->nullable();
                    } elseif ($type === 'decimal:5,2') {
                        $table->decimal($col, 5, 2)->default(0);
                    } else {
                        $table->integer($col)->default(0);
                    }
                }
            }
        });

        // Insert setting default
        DB::table('setting_kinerja')->insert([
            'nama'                    => 'Setting Kinerja Standar',
            'is_aktif'                => true,
            'is_default'              => true,
            'bobot_absensi'           => 50.00,
            'bobot_absensi_harian'    => 70.00,
            'bobot_absensi_mengajar'  => 30.00,
            'nilai_hadir'             => 100.00,
            'nilai_terlambat'         => 75.00,
            'nilai_izin'              => 70.00,
            'nilai_sakit'             => 80.00,
            'nilai_dinas_luar'        => 100.00,
            'nilai_alfa'              => 0.00,
            'hitung_penalty_terlambat'=> false,
            'toleransi_terlambat_menit' => 0,
            'penalty_per_terlambat'   => 5.00,
            'max_penalty_terlambat'   => 20.00,
            'bobot_tugas'             => 30.00,
            'bobot_tugas_tambahan'    => 60.00,
            'bobot_tugas_jabatan'     => 40.00,
            'jika_tidak_ada_tugas'    => 'sempurna',
            'bobot_administrasi'      => 20.00,
            'bobot_laporan_mengajar'  => 60.00,
            'bobot_log_kerja'         => 40.00,
            'target_log_per_hari'     => 1.0,
            'grade_a'                 => 90.00,
            'grade_b'                 => 75.00,
            'grade_c'                 => 60.00,
            'grade_d'                 => 40.00,
            'keterangan'              => 'Hadir=100, Terlambat=75, Izin=70, Sakit=80, Dinas Luar=100, Alfa=0',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('rekap_kinerja_bulanan', function (Blueprint $table) {
            foreach (['skor_absensi','skor_tugas','skor_administrasi','setting_kinerja_id',
                      'total_hadir','total_terlambat','total_izin','total_sakit','total_alfa',
                      'total_dinas_luar','total_hari_kerja','total_sesi_jadwal',
                      'total_sesi_terlaksana','total_sesi_dilaporkan',
                      'total_jp_jadwal','total_jp_terlaksana',
                      'total_realisasi_jabatan','total_realisasi_disetujui'] as $c) {
                if (Schema::hasColumn('rekap_kinerja_bulanan', $c)) $table->dropColumn($c);
            }
        });
        Schema::dropIfExists('setting_kinerja');
    }
};