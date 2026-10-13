<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TAHFIDZ/TAHSIN — gerbang tasmi' dari CAKUPAN, bukan penghitung + override admin.
 *
 * Masalah yang diperbaiki (terukur di produksi 14 Okt 2026): `ayat_terkumpul`
 * adalah penghitung yang ditambah setiap setoran lulus, sehingga setoran yang
 * TUMPANG TINDIH terhitung dua kali — 1.981 ayat ganda dari 530 setoran. Pada
 * 17 baris penghitungnya penuh padahal cakupan nyatanya kurang 1–104 ayat,
 * sehingga tombol tasmi' muncul (bahkan sudah ditandai lulus) untuk juz yang
 * belum khatam.
 *
 * Perbaikannya: cakupan dihitung dari himpunan ayat (riwayat setoran + baseline
 * sinkronisasi), dan `ayat_terkumpul` DITULIS dari hasilnya, tidak ditambah.
 *
 * `ayat_seed` dibutuhkan agar perhitungan itu tidak berputar: bagian yang
 * berasal dari sinkronisasi awal tidak punya rentang surah/ayat, dan dulu
 * disimpulkan dari `ayat_terkumpul − cakupan_setoran`. Begitu `ayat_terkumpul`
 * menjadi turunan, kesimpulan itu memakan dirinya sendiri. Jadi nilainya
 * dibekukan sekali di sini, dengan rumus yang sama seperti `lubangJuz()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hafalan_juz', function (Blueprint $t) {
            $t->unsignedSmallInteger('ayat_seed')->default(0)->after('jumlah_ayat_juz')
              ->comment('Ayat dari sinkronisasi awal (dihitung dari awal juz), bukan dari setoran');
            // Override admin: status yang dikunci tidak ikut disesuaikan ulang
            // oleh penghitungan otomatis, supaya keputusan manusia menang.
            $t->boolean('status_dikunci')->default(false)->after('status');
            $t->foreignId('dikoreksi_oleh')->nullable()->after('status_dikunci')
              ->constrained('users')->nullOnDelete();
            $t->timestamp('dikoreksi_pada')->nullable()->after('dikoreksi_oleh');
            $t->string('alasan_koreksi', 300)->nullable()->after('dikoreksi_pada');
        });

        // Jejak override pencapaian — terpisah dari koreksi_absensi karena
        // subjeknya santri & juz/level, bukan absensi guru.
        Schema::create('koreksi_pencapaian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $t->enum('tipe', ['tahfidz', 'tahsin']);
            $t->unsignedTinyInteger('juz')->nullable();
            $t->unsignedTinyInteger('level')->nullable();
            $t->string('field', 40);
            $t->string('nilai_lama', 120)->nullable();
            $t->string('nilai_baru', 120)->nullable();
            $t->string('alasan', 300);
            $t->foreignId('dikoreksi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['santri_id', 'tipe']);
        });

        // Pembatalan penunjukan penguji — tugas_tasmi sudah punya 'dibatalkan',
        // tugas_tasnif belum.
        DB::statement("ALTER TABLE tugas_tasnif MODIFY status
            ENUM('ditugaskan','selesai','dibatalkan') NOT NULL DEFAULT 'ditugaskan'");

        foreach (['tugas_tasmi', 'tugas_tasnif'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('dibatalkan_pada')->nullable();
                $t->string('alasan_batal', 300)->nullable();
            });
        }

        $this->bekukanSeed();
    }

    /**
     * Isi `ayat_seed` sekali dengan rumus lama: bagian ayat_terkumpul yang TIDAK
     * dijelaskan oleh rentang setoran ziyadah lulus. Setelah ini, nilainya tidak
     * pernah disimpulkan lagi — hanya diubah admin lewat sinkronisasi awal.
     */
    private function bekukanSeed(): void
    {
        $quran = app(\App\Services\QuranReferenceService::class);

        $setoran = DB::table('setoran_tahfidz')
            ->where('jenis', 'ziyadah')->where('lulus', true)
            ->get(['santri_id', 'surah_mulai', 'ayat_mulai', 'surah_selesai', 'ayat_selesai'])
            ->groupBy('santri_id');

        foreach (DB::table('hafalan_juz')->get() as $hj) {
            [$sM, $aM, $sS, $aS] = $quran->juzRange((int) $hj->juz);
            $awal  = $quran->posisiAbsolut($sM, $aM);
            $akhir = $quran->posisiAbsolut($sS, $aS);

            $dariSetoran = 0;
            foreach ($setoran->get($hj->santri_id, collect()) as $t) {
                $a = max($awal,  $quran->posisiAbsolut((int) $t->surah_mulai, (int) $t->ayat_mulai));
                $b = min($akhir, $quran->posisiAbsolut((int) $t->surah_selesai, (int) $t->ayat_selesai));
                if ($a > $b) continue;
                $dariSetoran += ($b - $a + 1);
            }

            $seed = max(0, (int) $hj->ayat_terkumpul - $dariSetoran);
            $seed = min($seed, $akhir - $awal + 1);

            DB::table('hafalan_juz')->where('id', $hj->id)->update(['ayat_seed' => $seed]);
        }
    }

    public function down(): void
    {
        foreach (['tugas_tasmi', 'tugas_tasnif'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->dropForeign(['dibatalkan_oleh']);
                $t->dropColumn(['dibatalkan_oleh', 'dibatalkan_pada', 'alasan_batal']);
            });
        }

        DB::statement("UPDATE tugas_tasnif SET status = 'ditugaskan' WHERE status = 'dibatalkan'");
        DB::statement("ALTER TABLE tugas_tasnif MODIFY status
            ENUM('ditugaskan','selesai') NOT NULL DEFAULT 'ditugaskan'");

        Schema::dropIfExists('koreksi_pencapaian');

        Schema::table('hafalan_juz', function (Blueprint $t) {
            $t->dropForeign(['dikoreksi_oleh']);
            $t->dropColumn(['ayat_seed', 'status_dikunci', 'dikoreksi_oleh',
                            'dikoreksi_pada', 'alasan_koreksi']);
        });
    }
};
