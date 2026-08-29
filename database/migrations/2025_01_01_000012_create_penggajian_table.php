<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode penggajian (misal: bulan Januari 2025)
        Schema::create('periode_penggajian', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Contoh: Penggajian Januari 2025');
            $table->integer('bulan')->comment('1-12');
            $table->integer('tahun');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', ['draft', 'proses', 'selesai', 'dibayar'])->default('draft');
            $table->timestamp('dikunci_pada')->nullable()
                  ->comment('Setelah dikunci, tidak bisa edit manual');
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->unique(['bulan', 'tahun']);
        });

        // Header penggajian per tenaga pendidik
        Schema::create('penggajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_penggajian_id')->constrained('periode_penggajian');
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->foreignId('jabatan_id')->constrained('jabatan')
                  ->comment('Snapshot jabatan saat penggajian');

            // Komponen utama
            $table->decimal('gaji_pokok', 12, 2)->default(0);

            // Vakasi (tambahan)
            $table->decimal('vakasi_absen_harian', 12, 2)->default(0);
            $table->decimal('vakasi_mengajar', 12, 2)->default(0);
            $table->decimal('vakasi_tugas_jabatan', 12, 2)->default(0);
            $table->decimal('vakasi_tugas_tambahan', 12, 2)->default(0);
            $table->decimal('tunjangan_lainnya', 12, 2)->default(0)
                  ->comment('Bonus atau tunjangan ad-hoc dari superadmin');

            // Potongan
            $table->decimal('potongan_keterlambatan', 12, 2)->default(0);
            $table->decimal('potongan_alfa', 12, 2)->default(0);
            $table->decimal('potongan_tetap', 12, 2)->default(0)
                  ->comment('BPJS dll');
            $table->decimal('potongan_lainnya', 12, 2)->default(0);

            // Total
            $table->decimal('total_pendapatan', 12, 2)->default(0)
                  ->comment('gaji_pokok + semua vakasi + tunjangan_lainnya');
            $table->decimal('total_potongan', 12, 2)->default(0);
            $table->decimal('gaji_bersih', 12, 2)->default(0)
                  ->comment('total_pendapatan - total_potongan');

            // Statistik absensi (snapshot)
            $table->integer('total_hari_kerja')->default(0);
            $table->integer('total_hadir')->default(0);
            $table->integer('total_izin')->default(0);
            $table->integer('total_sakit')->default(0);
            $table->integer('total_alfa')->default(0);
            $table->integer('total_terlambat')->default(0);
            $table->integer('total_jp_mengajar')->default(0);

            $table->enum('status', ['draft', 'final', 'dibayar'])->default('draft');
            $table->timestamp('dibayar_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('ada_koreksi_manual')->default(false)
                  ->comment('Flag jika superadmin override nilai');
            $table->foreignId('difinalisasi_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['periode_penggajian_id', 'tenaga_pendidik_id']);
            $table->index(['status', 'periode_penggajian_id']);
        });

        // Detail breakdown penggajian (audit trail setiap komponen)
        Schema::create('detail_penggajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penggajian_id')->constrained('penggajian')->onDelete('cascade');
            $table->enum('tipe', [
                'gaji_pokok',
                'vakasi_absen',
                'vakasi_mengajar',
                'vakasi_tugas_jabatan',
                'vakasi_tugas_tambahan',
                'tunjangan',
                'potongan_terlambat',
                'potongan_alfa',
                'potongan_bpjs',
                'potongan_lain',
            ]);
            $table->string('keterangan')->comment('Deskripsi detail, misal: Hadir 22 hari × Rp5.000');
            $table->integer('jumlah_satuan')->nullable()->comment('Misal: 22 hari, 30 JP');
            $table->string('satuan')->nullable()->comment('hari/JP/tugas');
            $table->decimal('nilai_per_satuan', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->json('referensi_ids')->nullable()
                  ->comment('Array ID absensi/tugas yang dijadikan dasar hitung');
            $table->timestamps();
        });

        // Koreksi absensi (exception handling utama)
        Schema::create('koreksi_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->date('tanggal');
            $table->enum('tipe_absensi', ['absen_harian', 'absen_mengajar']);
            $table->foreignId('absensi_harian_id')->nullable()->constrained('absensi_harian');
            $table->foreignId('absensi_mengajar_id')->nullable()->constrained('absensi_mengajar');
            $table->string('field_dikoreksi')->comment('Field apa yang dikoreksi, misal: status, jam_masuk');
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru');
            $table->text('alasan')->comment('Wajib isi alasan koreksi');
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('disetujui')
                  ->comment('Untuk audit — superadmin langsung approve');
            $table->foreignId('dikoreksi_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('koreksi_absensi');
        Schema::dropIfExists('detail_penggajian');
        Schema::dropIfExists('penggajian');
        Schema::dropIfExists('periode_penggajian');
    }
};