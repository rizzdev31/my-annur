<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Penyesuaian Liburan — potongan/penyesuaian manual khusus periode liburan.
     *
     * Konteks: saat liburan panjang (mis. libur akhir tahun/Ramadhan), pesantren
     * mungkin memberlakukan kebijakan gaji berbeda — sebagian guru tetap gaji penuh,
     * sebagian dipotong sesuai kesepakatan. Karena ini KEBIJAKAN per-kasus (tidak bisa
     * di-otomatisasi), admin menginput potongan liburan secara MANUAL per guru dengan
     * keterangan transparan yang tampil di slip gaji.
     *
     * - potongan_liburan   : nominal potongan (Rp), default 0 = tidak ada penyesuaian
     * - keterangan_liburan : alasan transparan (tampil di slip), mis.
     *                        "Libur akhir tahun 10 hari — gaji 50%"
     */
    public function up(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->decimal('potongan_liburan', 12, 2)->default(0)->after('potongan_lainnya');
            $table->text('keterangan_liburan')->nullable()->after('potongan_liburan');
        });

        // Tambah tipe 'penyesuaian_liburan' ke ENUM detail_penggajian
        DB::statement("
            ALTER TABLE `detail_penggajian`
            MODIFY COLUMN `tipe`
            ENUM(
                'gaji_pokok','vakasi_absen','vakasi_mengajar',
                'vakasi_tugas_jabatan','vakasi_tugas_tambahan','vakasi_peserta_kegiatan',
                'tunjangan',
                'potongan_terlambat','potongan_alfa','potongan_bpjs','potongan_lain',
                'penyesuaian_liburan','lainnya'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropColumn(['potongan_liburan', 'keterangan_liburan']);
        });

        DB::statement("
            UPDATE `detail_penggajian` SET `tipe` = 'potongan_lain'
            WHERE `tipe` = 'penyesuaian_liburan'
        ");

        DB::statement("
            ALTER TABLE `detail_penggajian`
            MODIFY COLUMN `tipe`
            ENUM(
                'gaji_pokok','vakasi_absen','vakasi_mengajar',
                'vakasi_tugas_jabatan','vakasi_tugas_tambahan','vakasi_peserta_kegiatan',
                'tunjangan',
                'potongan_terlambat','potongan_alfa','potongan_bpjs','potongan_lain',
                'lainnya'
            ) NOT NULL
        ");
    }
};
