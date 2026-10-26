<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SIFAT PEMBELAJARAN — nama keempat di samping reguler, tahfidz, tahsin.
 *
 * Keputusan pesantren 26 Okt 2026: pembelajaran **jam 20.00** bukan reguler,
 * melainkan **pesantren**. Pembelajaran Qur'an (tahfidz & tahsin, jam 18.00)
 * tidak berubah, dan PENGGAJIAN maupun KINERJA tidak berbeda sama sekali —
 * ini murni penamaan agar kategorinya tidak lagi "non-reguler".
 *
 * ══ MENGAPA DI JADWAL, BUKAN DI `mata_pelajaran.tipe` ══
 * Tiga nama lainnya memang hidup di `mata_pelajaran.tipe`, jadi wajar bila
 * yang keempat diletakkan di sana juga. Data melarangnya: ada mapel yang
 * diajar di KEDUA waktu, sehingga satu label per mapel pasti salah di salah
 * satu sisi. Terukur 26 Okt:
 *   Bahasa Arab     jam 20.00 = 18 jadwal · jam lain = 13
 *   Hadits          jam 20.00 =  7 jadwal · jam lain =  9
 *   Muhadharah      jam 20.00 =  8 jadwal · jam lain =  0
 *   Tuhfatul Athfal jam 20.00 =  2 jadwal · jam lain =  0
 * Mengubah tipe Bahasa Arab & Hadits akan ikut melabeli 22 jadwal PAGI
 * sebagai pesantren.
 *
 * Meletakkannya di jadwal juga membuat riaknya NOL: `mata_pelajaran.tipe`
 * tetap apa adanya, sehingga tujuh tempat yang menyaring `tipe = 'reguler'`
 * (menu Mengajar PWA, hitungan dashboard guru, rute notifikasi, tombol absen
 * guru inval, TIPE_BISA_GABUNG, kelayakan sesi ujian, pilihan mapel) tidak
 * berubah perilakunya. Kalau tipe mapel yang diubah, 35 sesi itu akan hilang
 * dari menu absen dan tidak bisa diinval.
 *
 * Backfill: seluruh jadwal jam 20.xx bermapel non-Qur'an (39 baris: 35 aktif
 * + 4 nonaktif; nol jadwal Qur'an di jam itu, sudah diperiksa). Jadwal
 * nonaktif ikut dilabeli agar riwayat & laporan lamanya konsisten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->enum('sifat', ['reguler', 'pesantren'])->default('reguler')->after('jumlah_jp')
              ->comment("Sifat pembelajaran; Qur'an tetap dibaca dari mata_pelajaran.tipe");
        });

        // Patokan JAM, bukan nama mapel: itulah aturan yang disebut pesantren.
        $idQuran = DB::table('mata_pelajaran')->whereIn('tipe', ['tahfidz', 'tahsin'])->pluck('id');

        DB::table('jadwal_mengajar')
            ->whereRaw('HOUR(jam_mulai) = 20')
            ->whereNotIn('mata_pelajaran_id', $idQuran)
            ->update(['sifat' => 'pesantren']);
    }

    public function down(): void
    {
        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->dropColumn('sifat');
        });
    }
};
