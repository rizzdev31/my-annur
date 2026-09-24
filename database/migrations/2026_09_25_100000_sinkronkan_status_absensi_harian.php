<?php

use App\Models\AbsensiHarian;
use App\Models\PengajuanIzin;
use App\Services\AbsensiKalkulasiService;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bereskan catatan absensi harian yang terlanjur keliru (25 Sep 2026).
 *
 * 1. STATUS TERTIMPA — halaman Absensi Harian admin dulu menghitung ulang tanpa
 *    konteks guru (jam kerja default) lalu menyimpannya, sehingga guru asrama &
 *    satpam tercatat terlambat ratusan menit. Dihitung ulang dengan jam kerja /
 *    shift miliknya pada tanggal itu + izin datang terlambat yang disetujui.
 *
 * 2. IZIN BERBASIS JAM — izin sementara & datang terlambat dulu menimpa hari itu
 *    menjadi "izin sehari penuh" bila disetujui sebelum guru check-in. Barisnya
 *    dikembalikan: kalau ada jam masuk → dihitung ulang, kalau tidak ada jam masuk
 *    sama sekali → baris semu dihapus agar alur absensi normal kembali berlaku.
 *
 * Baris yang sudah dikoreksi manual admin (is_koreksi) tidak disentuh, kecuali
 * baris yang memang dibuat oleh pengajuan izin berbasis jam itu sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mulai = '2026-08-01';
        $akhir = now()->toDateString();

        // ── 1. Izin berbasis jam yang terlanjur menimpa kehadiran ──────────
        $jamBased = PengajuanIzin::with('tenagaPendidik')
            ->where('status', 'disetujui')
            ->where(fn ($q) => $q->where('is_sementara', true)->orWhere('is_datang_terlambat', true))
            ->get();

        foreach ($jamBased as $izin) {
            $baris = AbsensiHarian::where('tenaga_pendidik_id', $izin->tenaga_pendidik_id)
                ->whereBetween('tanggal', [$izin->tanggal_mulai, $izin->tanggal_selesai])
                ->where('keterangan', 'like', "%Pengajuan #{$izin->id}%")
                ->get();

            foreach ($baris as $a) {
                if (!$a->jam_masuk) {          // baris semu: guru tak pernah check-in
                    $a->delete();
                    continue;
                }
                $h = AbsensiKalkulasiService::hitungStatus(
                    $a->jam_masuk, Carbon::parse($a->tanggal)->toDateString(), $izin->tenagaPendidik
                );
                $a->update([
                    'status'          => $h['status'],
                    'menit_terlambat' => $h['menit_terlambat'],
                    'is_koreksi'      => false,
                ]);
            }
        }

        // ── 2. Status yang tertimpa hitungan tanpa konteks guru ────────────
        for ($t = Carbon::parse($mulai); $t->lte(Carbon::parse($akhir)); $t->addDay()) {
            AbsensiKalkulasiService::rekalkuasiHarian($t->toDateString());
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai lama adalah hasil hitungan yang keliru.
    }
};
