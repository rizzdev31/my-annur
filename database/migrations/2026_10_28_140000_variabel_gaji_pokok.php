<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VARIABEL GAJI POKOK — katalog nominal baku untuk mengisi komponen per guru.
 *
 * Keputusan 28 Okt 2026: setelah gaji pokok jadi komponen per guru
 * (lihat migration 2026_10_28_090000), penetapannya terasa repetitif karena
 * banyak label bernominal sama. Terukur dari "data fix gaji.xlsx":
 *   Wali Asrama   Rp 186.000  dipakai 8 orang
 *   Wali Kelas    Rp 248.000  6 orang
 *   Musyrifah     Rp 310.000  4 orang
 *   Keamanan      Rp 806.000  3 orang
 * 34 dari 38 label jabatan nominalnya SERAGAM, jadi layak jadi variabel.
 *
 * ══ MENGAPA TABEL BARU, BUKAN `setting_gaji_pokok` ══
 * `setting_gaji_pokok` bernominal PER JABATAN (jabatan_id), sedangkan variabel
 * di sini berkunci JENIS + LABEL. Label komponen sengaja tidak terikat jabatan
 * RBAC/kinerja, dan empat label Excel bernominal berbeda antar guru
 * (Guru, Musyrif, Pengabdian, Kebersihan) sehingga tidak boleh punya satu
 * nominal baku. Memakai tabel lama berarti memaksa dua makna berbeda ke satu
 * kolom, dan merusak jalur lama yang masih dipakai untuk tanggal lampau.
 *
 * ══ YANG TIDAK MASUK KATALOG ══
 * Jam mengajar punya 18 nilai berbeda (Rp 93.000–992.000) karena mengikuti JP
 * masing-masing orang — tidak ada nominal baku yang bermakna. Tunjangan
 * pendidikan punya 9 nilai (Rp 90.000–135.000) yang berperilaku seperti
 * TINGKATAN, jadi ia masuk katalog sebagai pilihan.
 *
 * Katalog ini hanya MENGISI formulir; yang dibayar tetap nominal pada
 * komponen_gaji_pokok milik guru. Mengubah variabel TIDAK menggeser gaji
 * siapa pun — pelajaran dari setting vakasi yang dulu bergeser surut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variabel_gaji_pokok', function (Blueprint $t) {
            $t->id();
            $t->enum('jenis', ['jabatan', 'tunjangan_pendidikan'])
              ->comment('jam_mengajar tidak berkatalog: 18 nilai, mengikuti JP per orang');
            $t->string('label', 150);
            $t->decimal('nominal', 12, 2)->default(0);
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->boolean('is_aktif')->default(true);
            $t->string('catatan', 300)->nullable();
            $t->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            // Satu label per jenis: katalog ganda hanya membingungkan pengisi.
            $t->unique(['jenis', 'label'], 'vgp_jenis_label_unik');
            $t->index(['jenis', 'is_aktif'], 'vgp_jenis_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variabel_gaji_pokok');
    }
};
