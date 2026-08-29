<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Master setting jenis pengajuan ────────────────────────────────
        Schema::create('setting_jenis_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique()->comment('SAKIT, IZIN, CUTI_TAHUNAN, CUTI_MELAHIRKAN, DINAS_LUAR, IZIN_MENDADAK');
            $table->enum('kategori', ['sakit', 'izin', 'cuti', 'dinas'])
                  ->comment('Kategori besar untuk grouping');
            $table->text('deskripsi')->nullable();

            // Aturan pengajuan
            $table->integer('max_hari_per_pengajuan')->default(1)
                  ->comment('Maksimal hari dalam 1 pengajuan');
            $table->integer('kuota_per_tahun')->nullable()
                  ->comment('Null = tidak ada batas kuota tahunan');
            $table->integer('min_hari_pengajuan_sebelumnya')->default(0)
                  ->comment('Minimal H-berapa harus mengajukan. 0 = bisa mendadak');
            $table->boolean('butuh_dokumen')->default(false);
            $table->string('keterangan_dokumen')->nullable()
                  ->comment('Contoh: Surat dokter, SK Dinas, dll');
            $table->boolean('auto_approve')->default(false)
                  ->comment('true = langsung disetujui tanpa review superadmin');

            // Pengaruh ke gaji
            $table->enum('pengaruh_gaji', [
                'tidak_potong',    // gaji tetap penuh
                'potong_absensi',  // tidak dapat vakasi harian
                'potong_sebagian', // konfigurasi manual
                'potong_penuh',    // tidak dapat gaji sama sekali
            ])->default('potong_absensi');
            $table->decimal('persen_potongan', 5, 2)->default(0)
                  ->comment('Diisi jika pengaruh_gaji = potong_sebagian');

            // Status kepegawaian otomatis
            $table->boolean('update_status_kepegawaian')->default(false)
                  ->comment('Jika true, otomatis ubah status kepegawaian saat disetujui');
            $table->string('status_kepegawaian_tujuan')->nullable()
                  ->comment('Status kepegawaian yang dituju, misal: cuti, cuti_sakit');
            $table->integer('min_hari_untuk_update_status')->default(3)
                  ->comment('Minimal berapa hari agar status kepegawaian ikut diupdate');

            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // ── 2. Pengajuan izin dari tenaga pendidik ───────────────────────────
        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->foreignId('setting_jenis_pengajuan_id')->constrained('setting_jenis_pengajuan');

            // Detail pengajuan
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('jumlah_hari')->default(1)
                  ->comment('Hari kerja efektif, exclude hari libur');
            $table->text('alasan');
            $table->string('file_dokumen')->nullable();
            $table->string('nama_dokumen')->nullable();

            // Status & keputusan
            $table->enum('status', [
                'pending',
                'disetujui',
                'ditolak',
                'dibatalkan',
            ])->default('pending');
            $table->text('catatan_admin')->nullable()
                  ->comment('Catatan superadmin saat approve/reject');
            $table->foreignId('diproses_oleh')->nullable()->constrained('users');
            $table->timestamp('tanggal_keputusan')->nullable();

            // Tracking integrasi
            $table->boolean('absensi_sudah_diupdate')->default(false)
                  ->comment('Flag apakah absensi sudah di-generate otomatis');
            $table->boolean('status_kepegawaian_diupdate')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenaga_pendidik_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });

        // ── 3. Kuota izin tahunan per guru ───────────────────────────────────
        Schema::create('kuota_izin_tahunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->foreignId('setting_jenis_pengajuan_id')->constrained('setting_jenis_pengajuan');
            $table->integer('tahun');
            $table->integer('kuota_total');
            $table->integer('terpakai')->default(0);
            $table->integer('sisa')->virtualAs('kuota_total - terpakai');
            $table->timestamps();

            $table->unique(['tenaga_pendidik_id', 'setting_jenis_pengajuan_id', 'tahun'],
                'unique_kuota_per_tahun');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_izin_tahunan');
        Schema::dropIfExists('pengajuan_izin');
        Schema::dropIfExists('setting_jenis_pengajuan');
    }
};