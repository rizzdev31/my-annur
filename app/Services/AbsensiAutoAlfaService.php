<?php

namespace App\Services;

use App\Models\TenagaPendidik;
use App\Models\AbsensiHarian;
use App\Models\HariLibur;
use App\Models\LiburTendik;
use Carbon\Carbon;

/**
 * Auto-Alfa Absensi Harian.
 *
 * Tandai status 'alfa' bagi tenaga pendidik AKTIF yang tidak check-in sampai
 * shift kerjanya BERAKHIR. Sepenuhnya OVERNIGHT-AWARE: shift lintas hari
 * (mis. 15:15 → 07:00) baru dianggap berakhir keesokan harinya, jadi alfa
 * tidak dibuat prematur dan tanggal kerjanya tetap benar.
 *
 * Idempotent & aman:
 *   • lewati hari libur nasional/pesantren/darurat,
 *   • lewati hari libur mingguan tiap tendik (per jadwal jam kerjanya),
 *   • lewati bila SUDAH ada baris absensi hari itu (hadir/terlambat/izin/sakit/
 *     dinas/libur/alfa) — termasuk izin yang sudah materialized saat disetujui.
 */
class AbsensiAutoAlfaService
{
    /**
     * @param string|null $tanggal Batasi ke satu TANGGAL KERJA (YYYY-MM-DD).
     *                             Null = proses kemarin & hari ini (mencakup shift
     *                             normal yang berakhir hari ini dan shift lintas
     *                             hari yang dimulai kemarin lalu berakhir hari ini).
     * @return int jumlah baris alfa yang dibuat
     */
    public function tandai(?string $tanggal = null): int
    {
        $now = TimezoneHelper::now();

        $tanggalList = $tanggal
            ? [$tanggal]
            : [$now->copy()->subDay()->toDateString(), $now->toDateString()];

        // Hari libur (nasional/pesantren/darurat) untuk rentang yang diproses.
        $liburSet = HariLibur::tanggalSetDalamRentang(min($tanggalList), max($tanggalList));

        $gurus = TenagaPendidik::where('is_aktif', true)
            ->where(fn($q) => $q->where('status_kepegawaian', 'aktif')->orWhereNull('status_kepegawaian'))
            ->get();

        $dibuat = 0;

        foreach ($tanggalList as $tgl) {
            if (isset($liburSet[$tgl])) continue; // libur → tidak ada kewajiban absen

            $tglCarbon = Carbon::parse($tgl, TimezoneHelper::TZ);
            $namaHari  = TimezoneHelper::namaHariDB($tglCarbon);

            foreach ($gurus as $guru) {
                $jamKerja = $guru->jamKerjaAktif();
                if (!$jamKerja) continue;

                $jadwal = $jamKerja->getJamUntukHari($namaHari);
                if (!$jadwal) continue; // hari libur mingguan tendik ini → bukan alfa

                // Libur individu (guru mukim) pada tanggal ini → bukan alfa.
                if (LiburTendik::isLibur($guru->id, $tgl)) continue;

                // Akhir shift (overnight-aware): lintas hari → berakhir keesokan harinya.
                $end = Carbon::parse("$tgl {$jadwal['jam_pulang']}", TimezoneHelper::TZ);
                if ($jadwal['lintas_hari'] ?? false) $end->addDay();

                // Shift belum berakhir → masih boleh check-in, belum boleh alfa.
                if ($now->lte($end)) continue;

                // Sudah ada baris absensi untuk tanggal kerja ini → jangan ganggu.
                $ada = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
                    ->whereDate('tanggal', $tgl)->exists();
                if ($ada) continue;

                AbsensiHarian::create([
                    'tenaga_pendidik_id' => $guru->id,
                    'tanggal'            => $tgl,
                    'status'             => 'alfa',
                    'menit_terlambat'    => 0,
                    'keterangan'         => 'Auto: tidak absen sampai jam kerja berakhir.',
                ]);
                $dibuat++;
            }
        }

        return $dibuat;
    }
}
