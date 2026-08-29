<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fitur LEMBUR (per jabatan, vakasi tersendiri).
 *
 * - setting_vakasi : tambah tipe 'lembur' + min_durasi_menit + batas_grace_menit
 * - lembur         : event/batch lembur (metadata bersama, ditulis sekali)
 * - lembur_peserta : state per guru + bukti (foto/deskripsi/GPS)
 * - penggajian     : kolom vakasi_lembur
 * - detail_penggajian : tipe 'vakasi_lembur'
 *
 * Alur: ajukan/kolektif → admin approve (set jam_mulai+durasi, pilih SettingVakasi)
 *       → user upload bukti (GPS ketat, dalam jendela waktu) → sah → dibayar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. setting_vakasi: tipe 'lembur' + parameter lembur ───────────────
        DB::statement("
            ALTER TABLE setting_vakasi
            MODIFY COLUMN tipe_aktivitas ENUM(
                'absen_harian',
                'absen_mengajar',
                'tugas_jabatan',
                'tugas_tambahan',
                'lembur'
            ) NOT NULL
        ");

        Schema::table('setting_vakasi', function (Blueprint $table) {
            if (!Schema::hasColumn('setting_vakasi', 'min_durasi_menit')) {
                $table->integer('min_durasi_menit')->nullable()->after('nominal')
                      ->comment('Khusus lembur: minimal menit kerja agar berhak flat vakasi');
            }
            if (!Schema::hasColumn('setting_vakasi', 'batas_grace_menit')) {
                $table->integer('batas_grace_menit')->default(60)->after('min_durasi_menit')
                      ->comment('Khusus lembur: tenggang upload bukti setelah jam selesai (menit)');
            }
        });

        // ── 2. lembur (event/batch) ───────────────────────────────────────────
        Schema::create('lembur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_id')->constrained('jabatan')
                  ->comment('Lembur per jabatan');
            $table->string('judul');
            $table->text('deskripsi')->nullable();

            $table->date('tanggal');
            // Diisi admin saat approve (mandiri) atau saat buat (kolektif)
            $table->time('jam_mulai')->nullable();
            $table->integer('durasi_menit')->nullable()
                  ->comment('Durasi lembur; jam_selesai = jam_mulai + durasi');
            $table->time('jam_selesai')->nullable()
                  ->comment('Dihitung otomatis dari jam_mulai + durasi (opsi A)');

            // Tarif — dipilih dari SettingVakasi lembur (tanpa override)
            $table->foreignId('setting_vakasi_id')->nullable()
                  ->constrained('setting_vakasi')->nullOnDelete();
            $table->decimal('nominal_vakasi', 12, 2)->nullable()
                  ->comment('Snapshot nominal flat dari setting');
            $table->integer('min_durasi_menit')->nullable()
                  ->comment('Snapshot minimal menit dari setting');
            $table->integer('batas_grace_menit')->default(60)
                  ->comment('Snapshot tenggang upload dari setting');

            $table->enum('sumber_input', ['mandiri', 'kolektif'])->default('mandiri');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'dibatalkan'])
                  ->default('diajukan');

            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')
                  ->comment('User pengaju (mandiri); null jika dibuat admin kolektif');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users');
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index('jabatan_id');
            $table->index(['tanggal', 'status']);
        });

        // ── 3. lembur_peserta (state per guru + bukti) ────────────────────────
        Schema::create('lembur_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembur_id')->constrained('lembur')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');

            $table->enum('status', ['menunggu_bukti', 'sah', 'ditolak', 'dibatalkan'])
                  ->default('menunggu_bukti');

            // Bukti
            $table->text('deskripsi')->nullable()->comment('Deskripsi bukti dari user');
            $table->string('foto_bukti')->nullable();
            $table->timestamp('diunggah_pada')->nullable();

            // Geo (reuse LokasiAbsensiService, geofence ketat)
            $table->decimal('latitude', 10, 7)->nullable()->comment('Koordinat mentah → pin map');
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('validasi_lokasi', [
                'valid_koordinat', 'valid_wifi', 'invalid', 'bypass_admin', 'tidak_diperiksa',
            ])->nullable();
            $table->decimal('jarak_meter', 8, 2)->nullable();
            $table->unsignedBigInteger('setting_lokasi_id')->nullable();
            $table->string('nama_wifi')->nullable();
            $table->string('bssid_wifi')->nullable();

            // Vakasi (snapshot per peserta) — selalu = nominal setting (tanpa override)
            $table->decimal('nominal_vakasi', 12, 2)->default(0);

            // Pengesahan
            $table->enum('metode_pengesahan', ['otomatis', 'manual_admin'])->nullable();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users');
            $table->timestamp('disahkan_pada')->nullable();
            $table->string('alasan_pengesahan')->nullable()
                  ->comment('Wajib saat approve manual (user lupa bukti)');

            $table->timestamps();

            $table->foreign('setting_lokasi_id')
                  ->references('id')->on('setting_lokasi_absensi')->nullOnDelete();

            $table->unique(['lembur_id', 'tenaga_pendidik_id']);
            $table->index(['tenaga_pendidik_id', 'status']);
        });

        // ── 4. penggajian: kolom vakasi_lembur ────────────────────────────────
        Schema::table('penggajian', function (Blueprint $table) {
            if (!Schema::hasColumn('penggajian', 'vakasi_lembur')) {
                $table->decimal('vakasi_lembur', 12, 2)->default(0)
                      ->after('vakasi_peserta_kegiatan')
                      ->comment('Total vakasi lembur yang sah pada periode');
            }
        });

        // ── 5. detail_penggajian: tipe 'vakasi_lembur' ────────────────────────
        DB::statement("
            ALTER TABLE detail_penggajian
            MODIFY COLUMN tipe ENUM(
                'gaji_pokok',
                'vakasi_absen',
                'vakasi_mengajar',
                'vakasi_tugas_jabatan',
                'vakasi_tugas_tambahan',
                'vakasi_peserta_kegiatan',
                'vakasi_lembur',
                'tunjangan',
                'potongan_terlambat',
                'potongan_alfa',
                'potongan_bpjs',
                'potongan_lain',
                'penyesuaian_liburan',
                'lainnya'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // Kembalikan enum detail_penggajian tanpa vakasi_lembur
        DB::statement("
            ALTER TABLE detail_penggajian
            MODIFY COLUMN tipe ENUM(
                'gaji_pokok',
                'vakasi_absen',
                'vakasi_mengajar',
                'vakasi_tugas_jabatan',
                'vakasi_tugas_tambahan',
                'vakasi_peserta_kegiatan',
                'tunjangan',
                'potongan_terlambat',
                'potongan_alfa',
                'potongan_bpjs',
                'potongan_lain',
                'penyesuaian_liburan',
                'lainnya'
            ) NOT NULL
        ");

        Schema::table('penggajian', function (Blueprint $table) {
            if (Schema::hasColumn('penggajian', 'vakasi_lembur')) {
                $table->dropColumn('vakasi_lembur');
            }
        });

        Schema::dropIfExists('lembur_peserta');
        Schema::dropIfExists('lembur');

        Schema::table('setting_vakasi', function (Blueprint $table) {
            foreach (['min_durasi_menit', 'batas_grace_menit'] as $col) {
                if (Schema::hasColumn('setting_vakasi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::statement("
            ALTER TABLE setting_vakasi
            MODIFY COLUMN tipe_aktivitas ENUM(
                'absen_harian',
                'absen_mengajar',
                'tugas_jabatan',
                'tugas_tambahan'
            ) NOT NULL
        ");
    }
};
