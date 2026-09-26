<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen gaji yang selama ini tidak punya tempat (26 Sep 2026).
 *
 *  - potongan_guru            : potongan per-guru (voucher/simpanan/LAZISMU) hanya
 *                               dijumlahkan ke total_potongan. Akibatnya semua kolom
 *                               potongan tampil 0 padahal totalnya Rp 11,6 juta —
 *                               mustahil ditelusuri dari tabel maupun kartu rincian.
 *  - vakasi_ekstrakurikuler   : sudah dihitung & masuk total pendapatan, tapi tak
 *                               tersimpan per komponen. Ekskul baru mulai jalan.
 *  - potongan_tidak_terbayar  : bila potongan melebihi pendapatan, gaji bersih
 *                               dipangkas ke 0 dan sisanya hilang tanpa jejak
 *                               (Ahmad Khobir: pokok 124rb, potongan 175rb → 51rb hilang).
 *
 * Nilai ENUM rincian slip juga ditambah agar vakasi piket / ekskul / potongan
 * per-guru tidak lagi menumpuk sebagai "lainnya".
 */
return new class extends Migration
{
    private const ENUM_LAMA = "'gaji_pokok','vakasi_absen','vakasi_mengajar','vakasi_tugas_jabatan',"
        . "'vakasi_tugas_tambahan','vakasi_peserta_kegiatan','vakasi_lembur','tunjangan',"
        . "'potongan_terlambat','potongan_alfa','potongan_bpjs','potongan_lain',"
        . "'penyesuaian_liburan','lainnya'";

    private const ENUM_BARU = self::ENUM_LAMA . ",'vakasi_piket','vakasi_ekstrakurikuler','potongan_guru'";

    public function up(): void
    {
        Schema::table('penggajian', function (Blueprint $t) {
            $t->decimal('vakasi_ekstrakurikuler', 12, 2)->default(0)->after('vakasi_piket');
            $t->decimal('potongan_guru', 12, 2)->default(0)->after('potongan_lainnya');
            $t->decimal('potongan_tidak_terbayar', 12, 2)->default(0)->after('potongan_liburan');
        });

        DB::statement("ALTER TABLE detail_penggajian MODIFY tipe ENUM(" . self::ENUM_BARU . ") NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE detail_penggajian SET tipe = 'lainnya' "
            . "WHERE tipe IN ('vakasi_piket','vakasi_ekstrakurikuler','potongan_guru')");
        DB::statement("ALTER TABLE detail_penggajian MODIFY tipe ENUM(" . self::ENUM_LAMA . ") NOT NULL");

        Schema::table('penggajian', fn (Blueprint $t) => $t->dropColumn([
            'vakasi_ekstrakurikuler', 'potongan_guru', 'potongan_tidak_terbayar',
        ]));
    }
};
