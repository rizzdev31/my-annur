<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PENGECUALIAN PENILAIAN — satu penugasan dikeluarkan dari penilaian kinerja
 * atas keputusan superadmin, berikut alasan dan jejak siapa/kapan.
 *
 * Latar: superadmin perlu bisa MENYELESAIKAN tugas tambahan walau pengisiannya
 * belum lengkap (mis. kegiatan sudah berlalu dan tak mungkin diisi lagi).
 * Saat menutup tugas, guru yang belum mengisi tidak selalu bersalah — ada yang
 * lalai, ada yang kegiatannya memang tidak jalan. Karena itu perlakuannya
 * dipilih per penutupan: ditandai gagal, atau dikecualikan dari penilaian.
 *
 * ══ MENGAPA KOLOM SENDIRI, BUKAN MENUMPANG STATUS ══
 * Menandai 'tidak_selesai' sudah berarti GAGAL dan memang menurunkan skor, jadi
 * tidak bisa dipakai untuk "jangan dinilai". Memakai status tugas 'dibatalkan'
 * juga salah arti: tugasnya justru dinyatakan SELESAI, bukan dibatalkan. Dengan
 * kolom sendiri, status_pengerjaan tetap jujur mencatat bahwa guru itu tidak
 * mengisi, sementara penilaian melewatinya — dan alasannya bisa ditagih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan_tambahan', function (Blueprint $t) {
            $t->boolean('dikecualikan_penilaian')->default(false)->after('disetujui')
              ->comment('Dikeluarkan dari penilaian kinerja atas keputusan superadmin');
            $t->string('alasan_pengecualian', 300)->nullable()->after('dikecualikan_penilaian');
            $t->foreignId('dikecualikan_oleh')->nullable()->after('alasan_pengecualian')
              ->constrained('users')->nullOnDelete();
            $t->timestamp('dikecualikan_pada')->nullable()->after('dikecualikan_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_tambahan', function (Blueprint $t) {
            $t->dropForeign(['dikecualikan_oleh']);
            $t->dropColumn([
                'dikecualikan_penilaian',
                'alasan_pengecualian',
                'dikecualikan_oleh',
                'dikecualikan_pada',
            ]);
        });
    }
};
