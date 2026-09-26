<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notulensi kegiatan (PDF) + pengumuman boleh lebih dari satu (26 Sep 2026).
 *
 * 1. Guru yang mendapat tugas "absen kegiatan" kini mengunggah NOTULENSI berkas PDF
 *    pada kegiatannya. Satu kegiatan = satu notulensi (bisa diganti selama belum
 *    dijadikan pengumuman).
 *
 * 2. Pengumuman dulu dipaksa TUNGGAL: store()/update() menonaktifkan semua
 *    pengumuman lain begitu satu diaktifkan, dan API hanya mengembalikan satu
 *    (yang terbaru). Sekarang boleh banyak sekaligus, dan isinya bisa berupa
 *    PDF (mis. notulensi rapat) — bukan hanya pamflet gambar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_kegiatan', function (Blueprint $t) {
            $t->string('notulensi_file', 255)->nullable()->after('deskripsi');
            $t->string('notulensi_nama', 255)->nullable()->after('notulensi_file');
            $t->timestamp('notulensi_diunggah_pada')->nullable()->after('notulensi_nama');
            $t->unsignedBigInteger('notulensi_oleh')->nullable()->after('notulensi_diunggah_pada');
            $t->unsignedBigInteger('pengumuman_id')->nullable()->after('notulensi_oleh');
        });

        Schema::table('pengumuman', function (Blueprint $t) {
            // 'gambar' = pamflet seperti sebelumnya, 'pdf' = berkas (notulensi/surat).
            $t->string('tipe', 20)->default('gambar')->after('judul');
            $t->string('file', 255)->nullable()->after('gambar');
            $t->string('nama_file', 255)->nullable()->after('file');
            $t->text('isi')->nullable()->after('nama_file');
            $t->unsignedInteger('urutan')->default(0)->after('aktif');
            $t->string('sumber_tipe', 30)->nullable()->after('urutan');
            $t->unsignedBigInteger('sumber_id')->nullable()->after('sumber_tipe');
        });

        // Pengumuman lama semuanya pamflet gambar.
        DB::table('pengumuman')->whereNull('tipe')->update(['tipe' => 'gambar']);
    }

    public function down(): void
    {
        Schema::table('absensi_kegiatan', fn (Blueprint $t) => $t->dropColumn([
            'notulensi_file', 'notulensi_nama', 'notulensi_diunggah_pada', 'notulensi_oleh', 'pengumuman_id',
        ]));
        Schema::table('pengumuman', fn (Blueprint $t) => $t->dropColumn([
            'tipe', 'file', 'nama_file', 'isi', 'urutan', 'sumber_tipe', 'sumber_id',
        ]));
    }
};
