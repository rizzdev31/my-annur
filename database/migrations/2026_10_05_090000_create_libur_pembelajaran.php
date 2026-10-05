<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBUR PEMBELAJARAN — pembelajaran diliburkan karena ada kegiatan, sementara
 * ORANGNYA TETAP MASUK.
 *
 * Sengaja TIDAK memakai tabel `hari_libur`. `HariLibur::isLibur()` dibaca ~20
 * tempat yang semuanya berarti "libur sehari penuh untuk semua orang": gerbang
 * absen masuk guru, jumlah hari kerja penggajian, auto-alfa, kegiatan wajib
 * piket, controlling, eskalasi, monitoring, dan 3 laporan. Menitipkan libur
 * yang hanya meliburkan pembelajaran di sana akan membebaskan guru dari absen
 * harian dan mengubah hari kerja gaji — bukan yang dimaksud.
 *
 * Konsep ini hanya dibaca sisi PEMBELAJARAN (LiburMengajarService dan yang
 * memanggilnya), sehingga gaji & absensi harian tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libur_pembelajaran', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 150)->comment('Nama kegiatan penggantinya');
            $t->date('tanggal');
            $t->date('tanggal_selesai')->nullable();

            // semua = seluruh pembelajaran; kelas = kelas terpilih; sesi = jadwal terpilih
            $t->enum('cakupan', ['semua', 'kelas', 'sesi'])->default('semua');
            $t->json('jenis_kelas')->nullable()
              ->comment('Batasi jenis kelas (sekolah/pesantren/tahfidz/tahsin); null = semua');
            // Kegiatan pagi tidak boleh meliburkan halaqoh malam.
            $t->time('jam_mulai')->nullable();
            $t->time('jam_selesai')->nullable();

            $t->string('materi_jurnal', 255)->nullable()->comment('Teks jurnal; default = nama kegiatan');
            $t->boolean('hitung_jp')->default(true)->comment('JP tetap diberikan (seperti libur biasa)');
            $t->boolean('isi_absensi_santri')->default(true);
            $t->string('status_santri', 10)->default('hadir')->comment('Status awal santri; izin/sakit tetap menang');

            $t->text('keterangan')->nullable();
            $t->boolean('is_aktif')->default(true);
            $t->timestamp('dibatalkan_pada')->nullable();
            $t->string('alasan_pembatalan')->nullable();
            $t->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tanggal', 'is_aktif']);
        });

        Schema::create('libur_pembelajaran_kelas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('libur_pembelajaran_id')->constrained('libur_pembelajaran')->cascadeOnDelete();
            $t->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $t->unique(['libur_pembelajaran_id', 'kelas_id'], 'lp_kelas_unik');
        });

        Schema::create('libur_pembelajaran_jadwal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('libur_pembelajaran_id')->constrained('libur_pembelajaran')->cascadeOnDelete();
            $t->foreignId('jadwal_mengajar_id')->constrained('jadwal_mengajar')->cascadeOnDelete();
            $t->unique(['libur_pembelajaran_id', 'jadwal_mengajar_id'], 'lp_jadwal_unik');
        });

        // Penanda sumber: tanpa ini pembatalan kegiatan tidak mungkin bersih —
        // baris buatan sistem tak bisa dibedakan dari pekerjaan guru.
        Schema::table('absensi_mengajar', function (Blueprint $t) {
            $t->foreignId('libur_pembelajaran_id')->nullable()->after('dikoreksi_oleh')
              ->constrained('libur_pembelajaran')->nullOnDelete();
            $t->string('status_sebelum', 20)->nullable()->after('libur_pembelajaran_id')
              ->comment('Status sebelum diliburkan, untuk pemulihan saat kegiatan dibatalkan');
        });

        // Kehadiran saat kegiatan bukan kehadiran pembelajaran — laporan harus
        // bisa memisahkan keduanya.
        Schema::table('absensi_santri', function (Blueprint $t) {
            $t->enum('sumber', ['guru', 'kegiatan'])->default('guru')->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_santri', fn (Blueprint $t) => $t->dropColumn('sumber'));
        Schema::table('absensi_mengajar', function (Blueprint $t) {
            $t->dropForeign(['libur_pembelajaran_id']);
            $t->dropColumn(['libur_pembelajaran_id', 'status_sebelum']);
        });
        Schema::dropIfExists('libur_pembelajaran_jadwal');
        Schema::dropIfExists('libur_pembelajaran_kelas');
        Schema::dropIfExists('libur_pembelajaran');
    }
};
