<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KONTROL VAKASI TUGAS TAMBAHAN — hentikan nominal yang bergeser sendiri.
 *
 * Keluhan: "banyak tugas tambahan yang vakasinya direvisi; awalnya tidak ada,
 * tiba-tiba ada vakasi." Diukur di produksi 26 Okt 2026, penyebabnya tiga dan
 * semuanya struktural:
 *
 *  1. Nominal MENGIKUTI `setting_vakasi` yang bisa diedit kapan saja. 11 tugas
 *     bergantung pada setting; begitu nominal settingnya diubah (#4 pada 26 Sep,
 *     #9 pada 29 Sep), nominal seluruh tugas itu berubah SURUT — termasuk tugas
 *     yang sudah dilaporkan guru.
 *  2. Setting yang sudah DINONAKTIFKAN tetap dipakai menghitung gaji: kedua
 *     setting tugas_tambahan di produksi ber-`is_aktif = false`, tetapi masih
 *     memberi Rp 620.000 kepada 11 tugas. Admin yang menonaktifkannya berharap
 *     vakasinya berhenti — tidak berhenti.
 *  3. 21 tugas tidak punya keputusan vakasi sama sekali (override & setting
 *     kosong). Nominalnya 0 hari ini, tapi itu lubang fallback, bukan
 *     keputusan: begitu ada yang memasang setting, 20 di antaranya yang sudah
 *     punya penerima disetujui langsung berubah.
 *
 * Dan tidak ada jejak apa pun: kolom audit di `tugas_tambahan` hanya
 * `dibuat_oleh`, dan `log_aktivitas` nol baris untuk TugasTambahan. Jadi
 * pertanyaan "siapa yang mengubah vakasi ini, kapan, dari berapa" tidak bisa
 * dijawab.
 *
 * Dua kolom + satu tabel:
 *  - `vakasi_dikunci` + penetap & waktunya pada tugas DAN penerima → nominal
 *    yang sudah ditetapkan berhenti mengikuti setting.
 *  - `riwayat_vakasi_tugas` → setiap perubahan tercatat: dari berapa ke berapa,
 *    oleh siapa, kapan, dengan alasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tugas_tambahan', 'penugasan_tambahan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->boolean('vakasi_dikunci')->default(false)->after('vakasi_override')
                  ->comment('Nominal ditetapkan manual; berhenti mengikuti setting vakasi');
                $t->foreignId('vakasi_ditetapkan_oleh')->nullable()->after('vakasi_dikunci')
                  ->constrained('users')->nullOnDelete();
                $t->timestamp('vakasi_ditetapkan_pada')->nullable()->after('vakasi_ditetapkan_oleh');
            });
        }

        Schema::create('riwayat_vakasi_tugas', function (Blueprint $t) {
            $t->id();
            // Salah satu terisi: perubahan di tingkat tugas atau tingkat penerima.
            $t->foreignId('tugas_tambahan_id')->nullable()->constrained('tugas_tambahan')->cascadeOnDelete();
            $t->foreignId('penugasan_id')->nullable()->constrained('penugasan_tambahan')->cascadeOnDelete();

            $t->decimal('nominal_lama', 12, 2)->nullable();
            $t->decimal('nominal_baru', 12, 2)->nullable();
            // Sumber nominal SEBELUM & SESUDAH: override / setting / tidak_ada.
            // Perlu karena nominal bisa sama angkanya tapi berubah sumbernya —
            // dari "mengikuti setting" menjadi "ditetapkan manual".
            $t->string('sumber_lama', 30)->nullable();
            $t->string('sumber_baru', 30)->nullable();
            $t->boolean('dikunci')->default(false);

            $t->string('alasan', 300);
            $t->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tugas_tambahan_id', 'created_at']);
            $t->index(['penugasan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_vakasi_tugas');

        foreach (['tugas_tambahan', 'penugasan_tambahan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->dropForeign(['vakasi_ditetapkan_oleh']);
                $t->dropColumn(['vakasi_dikunci', 'vakasi_ditetapkan_oleh', 'vakasi_ditetapkan_pada']);
            });
        }
    }
};
