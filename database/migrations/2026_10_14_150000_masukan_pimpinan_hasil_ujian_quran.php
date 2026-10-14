<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masukan pimpinan atas hasil tasmi'/tasnif.
 *
 * Sebelumnya hasil ujian hanya punya `catatan` dari PENGUJI, dan superadmin
 * tidak punya tempat untuk menanggapi — padahal dialah yang memantau mutu
 * penilaian. Kolom ini terpisah dari `catatan` supaya jelas siapa yang
 * berbicara: catatan = penguji saat menilai, masukan = pimpinan sesudahnya.
 * Keduanya tampil di halaman nilai sertifikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tugas_tasmi', 'tugas_tasnif'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->text('masukan_pimpinan')->nullable()->after('catatan');
                $t->foreignId('masukan_oleh')->nullable()->after('masukan_pimpinan')
                  ->constrained('users')->nullOnDelete();
                $t->timestamp('masukan_pada')->nullable()->after('masukan_oleh');
            });
        }
    }

    public function down(): void
    {
        foreach (['tugas_tasmi', 'tugas_tasnif'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) {
                $t->dropForeign(['masukan_oleh']);
                $t->dropColumn(['masukan_pimpinan', 'masukan_oleh', 'masukan_pada']);
            });
        }
    }
};
