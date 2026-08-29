<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. tugas_jabatan: tambah tipe_pengerjaan ───────────────────────
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (!Schema::hasColumn('tugas_jabatan', 'tipe_pengerjaan')) {
                $table->enum('tipe_pengerjaan', ['mandiri', 'absen_kegiatan'])
                    ->default('mandiri')
                    ->after('frekuensi')
                    ->comment('mandiri=kerjakan sendiri+upload bukti, absen_kegiatan=mengabsen peserta kegiatan');
            }
        });

        // ── 2. tugas_tambahan: tambah tipe_pengerjaan ─────────────────────
        Schema::table('tugas_tambahan', function (Blueprint $table) {
            if (!Schema::hasColumn('tugas_tambahan', 'tipe_pengerjaan')) {
                $table->enum('tipe_pengerjaan', ['mandiri', 'absen_kegiatan'])
                    ->default('mandiri')
                    ->after('wajib_laporan')
                    ->comment('mandiri=kerjakan sendiri+upload bukti, absen_kegiatan=mengabsen peserta kegiatan');
            }
        });

        // ── 3. realisasi_tugas_jabatan: tambah kolom bukti ────────────────
        Schema::table('realisasi_tugas_jabatan', function (Blueprint $table) {
            if (!Schema::hasColumn('realisasi_tugas_jabatan', 'bukti_tipe')) {
                $table->enum('bukti_tipe', ['teks', 'foto', 'link'])
                    ->nullable()
                    ->after('file_bukti')
                    ->comment('Tipe bukti yang diupload guru');
            }
            if (!Schema::hasColumn('realisasi_tugas_jabatan', 'link_bukti')) {
                $table->string('link_bukti')->nullable()
                    ->after('bukti_tipe')
                    ->comment('Link URL sebagai bukti');
            }
            if (!Schema::hasColumn('realisasi_tugas_jabatan', 'teks_bukti')) {
                $table->text('teks_bukti')->nullable()
                    ->after('link_bukti')
                    ->comment('Deskripsi teks sebagai bukti');
            }
        });

        // ── 4. penugasan_tambahan: tambah kolom bukti ─────────────────────
        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            if (!Schema::hasColumn('penugasan_tambahan', 'bukti_tipe')) {
                $table->enum('bukti_tipe', ['teks', 'foto', 'link'])
                    ->nullable()
                    ->after('file_laporan')
                    ->comment('Tipe bukti yang diupload guru');
            }
            if (!Schema::hasColumn('penugasan_tambahan', 'link_bukti')) {
                $table->string('link_bukti')->nullable()
                    ->after('bukti_tipe');
            }
            if (!Schema::hasColumn('penugasan_tambahan', 'teks_bukti')) {
                $table->text('teks_bukti')->nullable()
                    ->after('link_bukti');
            }
        });

        // ── 5. Tabel absensi_kegiatan ──────────────────────────────────────
        if (!Schema::hasTable('absensi_kegiatan')) {
            Schema::create('absensi_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->string('sumber_tipe')
                    ->comment('tugas_jabatan | tugas_tambahan');
                $table->unsignedBigInteger('sumber_id');
                $table->foreignId('realisasi_id')
                    ->nullable()
                    ->constrained('realisasi_tugas_jabatan')->nullOnDelete();
                $table->foreignId('penugasan_id')
                    ->nullable()
                    ->constrained('penugasan_tambahan')->nullOnDelete();
                $table->foreignId('pengabsen_id')
                    ->constrained('tenaga_pendidik');
                $table->string('nama_kegiatan');
                $table->date('tanggal_kegiatan');
                $table->time('jam_mulai')->nullable();
                $table->time('jam_selesai')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('lokasi')->nullable();
                $table->enum('status', ['berlangsung', 'selesai', 'dibatalkan'])
                    ->default('berlangsung');
                $table->foreignId('setting_vakasi_id')
                    ->nullable()->constrained('setting_vakasi');
                $table->decimal('vakasi_per_peserta', 12, 2)->nullable();
                $table->foreignId('dibuat_oleh')
                    ->nullable()->constrained('users');
                $table->timestamps();

                // Nama index pendek (≤64 karakter)
                $table->index(['sumber_tipe', 'sumber_id'], 'idx_absen_kgt_sumber');
                $table->index(['pengabsen_id', 'tanggal_kegiatan'], 'idx_absen_kgt_pengabsen');
            });
        }

        // ── 6. Tabel absensi_kegiatan_peserta ─────────────────────────────
        if (!Schema::hasTable('absensi_kegiatan_peserta')) {
            Schema::create('absensi_kegiatan_peserta', function (Blueprint $table) {
                $table->id();
                $table->foreignId('absensi_kegiatan_id')
                    ->constrained('absensi_kegiatan')->onDelete('cascade');
                $table->foreignId('tenaga_pendidik_id')
                    ->constrained('tenaga_pendidik');
                // Enum final (lihat migrasi fix 2026_05_29): default 'belum'.
                // Ditulis final di sini karena file create ini tersortir setelah
                // migrasi fix (tanpa prefix tanggal), sehingga saat migrate:fresh
                // fix di-skip dan tabel harus sudah benar sejak dibuat.
                $table->enum('status_kehadiran', ['belum', 'hadir', 'terlambat', 'izin', 'alfa'])
                    ->default('belum');
                $table->time('jam_hadir')->nullable();
                $table->text('keterangan')->nullable();
                $table->boolean('vakasi_diberikan')->default(false);
                $table->decimal('nominal_vakasi', 12, 2)->nullable();
                $table->foreignId('dicatat_oleh')
                    ->nullable()->constrained('users');
                $table->timestamps();

                // Nama unique & index pendek (≤64 karakter)
                $table->unique(
                    ['absensi_kegiatan_id', 'tenaga_pendidik_id'],
                    'uq_peserta_kgt'                          // 14 chars ✓
                );
                $table->index(
                    ['tenaga_pendidik_id', 'vakasi_diberikan'],
                    'idx_peserta_kgt_vakasi'                  // 22 chars ✓
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_kegiatan_peserta');
        Schema::dropIfExists('absensi_kegiatan');

        Schema::table('penugasan_tambahan', function (Blueprint $table) {
            $cols = ['bukti_tipe', 'link_bukti', 'teks_bukti'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('penugasan_tambahan', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
        Schema::table('realisasi_tugas_jabatan', function (Blueprint $table) {
            $cols = ['bukti_tipe', 'link_bukti', 'teks_bukti'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('realisasi_tugas_jabatan', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
        Schema::table('tugas_tambahan', function (Blueprint $table) {
            if (Schema::hasColumn('tugas_tambahan', 'tipe_pengerjaan'))
                $table->dropColumn('tipe_pengerjaan');
        });
        Schema::table('tugas_jabatan', function (Blueprint $table) {
            if (Schema::hasColumn('tugas_jabatan', 'tipe_pengerjaan'))
                $table->dropColumn('tipe_pengerjaan');
        });
    }
};