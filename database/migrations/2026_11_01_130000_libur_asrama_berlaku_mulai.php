<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BATAS AWAL BERLAKUNYA SEBUAH JENIS PENGAJUAN.
 *
 * Libur asrama tidak boleh menyentuh slip gaji yang sudah ada: ia berlaku
 * mulai periode November 2026 (26 Okt – 25 Nov) dan tidak ke belakang.
 *
 * Kenapa ini harus jadi pagar, bukan kesepakatan lisan: hari libur individu
 * MENGURANGI hari kerja di `PayrollCalculationService`, jadi satu baris libur
 * yang jatuh di dalam periode Oktober (26 Sep – 25 Okt) akan menggeser angka
 * 52 slip Oktober begitu periodenya digenerate ulang. Pengajuan sendiri
 * memakai `min_hari_pengajuan_sebelumnya = 0` sehingga tanggal lampau tidak
 * tertolak dengan sendirinya.
 *
 * Dibuat umum (kolom pada jenis pengajuan, bukan konstanta di kode) supaya
 * jenis lain yang menyentuh uang bisa memakai pagar yang sama, dan supaya
 * tanggalnya bisa digeser dari layar tanpa deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('setting_jenis_pengajuan', 'berlaku_mulai')) {
            Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
                $t->date('berlaku_mulai')->nullable()->after('maks_per_pekan')
                    ->comment('Jenis ini tidak boleh dipakai untuk tanggal sebelum ini');
            });
        }

        // Awal periode penggajian November 2026 — diambil dari datanya, bukan
        // ditulis tangan, supaya tetap benar bila rentang periodenya berbeda.
        $awalNovember = DB::table('periode_penggajian')
            ->where('bulan', 11)->where('tahun', 2026)
            ->value('tanggal_mulai');

        DB::table('setting_jenis_pengajuan')
            ->where('kode', 'LIBUR_ASRAMA')
            ->update([
                'berlaku_mulai' => $awalNovember
                    ? \Carbon\Carbon::parse($awalNovember)->toDateString()
                    : '2026-10-26',
                'updated_at'    => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('setting_jenis_pengajuan', 'berlaku_mulai')) {
            Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
                $t->dropColumn('berlaku_mulai');
            });
        }
    }
};
