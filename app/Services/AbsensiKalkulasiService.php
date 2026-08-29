<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\SettingJamKerja;
use App\Services\TimezoneHelper;
use Carbon\Carbon;

/**
 * AbsensiKalkulasiService
 * 
 * Kalkulasi status dan keterlambatan absensi.
 * Dipakai oleh AbsensiController (web) dan AbsensiApiController (mobile).
 */
class AbsensiKalkulasiService
{
    /**
     * Kalkulasi status dan menit terlambat dari jam masuk aktual.
     * 
     * @param  string  $jamMasukAktual  Format H:i atau H:i:s
     * @param  string  $tanggal         Format Y-m-d
     * @return array   ['status'=>..., 'menit_terlambat'=>..., 'jadwal'=>...]
     */
    public static function hitungStatus(string $jamMasukAktual, string $tanggal, ?\App\Models\TenagaPendidik $tp = null): array
    {
        $tgl      = Carbon::parse($tanggal, TimezoneHelper::TZ);
        // FIX: gunakan namaHariDB() agar format konsisten dengan getJamUntukHari()
        // yang menyimpan key 'senin','selasa',... bukan 'monday','tuesday',...
        $namaHari = TimezoneHelper::namaHariDB($tgl);

        // Jam kerja per individu tendik (fallback default global bila tak di-assign).
        $jamKerja = $tp?->jamKerjaAktif() ?? SettingJamKerja::getDefault();
        $jadwal   = $jamKerja?->getJamUntukHari($namaHari);

        if (!$jadwal) {
            return [
                'status'          => 'hadir',
                'menit_terlambat' => 0,
                'jadwal'          => null,
            ];
        }

        $masukAktual    = TimezoneHelper::parse($tanggal . ' ' . $jamMasukAktual);
        $masukJadwal    = TimezoneHelper::parse($tanggal . ' ' . $jadwal['jam_masuk']);
        $batasTerlambat = $masukJadwal->copy()->addMinutes($jadwal['toleransi'] ?? 15);

        if ($masukAktual->gt($batasTerlambat)) {
            // Terlambat — hitung dari jam masuk jadwal (bukan dari batas toleransi).
            // abs(): Carbon 3 (Laravel 12) diffInMinutes bertanda; tanpa abs hasil negatif.
            $menitTerlambat = (int) abs($masukAktual->diffInMinutes($masukJadwal));
            return [
                'status'          => 'terlambat',
                'menit_terlambat' => $menitTerlambat,
                'jadwal'          => $jadwal,
            ];
        }

        return [
            'status'          => 'hadir',
            'menit_terlambat' => 0,
            'jadwal'          => $jadwal,
        ];
    }

    /**
     * Format jam dari DB (H:i:s) ke tampilan (H:i).
     */
    public static function formatJam(?string $jam): ?string
    {
        if (!$jam) return null;
        return Carbon::parse($jam)->format('H:i');
    }

    /**
     * Label terlambat: "1 jam 30 menit" atau "45 menit".
     */
    public static function labelTerlambat(int $menit): ?string
    {
        if ($menit <= 0) return null;
        $j = (int) floor($menit / 60);
        $m = $menit % 60;
        return $j > 0 ? "{$j} jam {$m} menit" : "{$menit} menit";
    }

    /**
     * Kalkulasi ulang semua absensi yang status-nya perlu diperbarui.
     * Dipanggil dari artisan command atau setelah update setting jam kerja.
     */
    public static function rekalkuasiHarian(string $tanggal): int
    {
        $absensiList = AbsensiHarian::whereDate('tanggal', $tanggal)
            ->whereIn('status', ['hadir', 'terlambat'])
            ->whereNotNull('jam_masuk')
            ->where('is_koreksi', false) // skip yang sudah dikoreksi manual
            ->with('tenagaPendidik')     // untuk resolusi jam kerja per individu
            ->get();

        $updated = 0;
        foreach ($absensiList as $a) {
            $hasil = self::hitungStatus($a->jam_masuk, $tanggal, $a->tenagaPendidik);
            if ($hasil['status'] !== $a->status || 
                $hasil['menit_terlambat'] !== ($a->menit_terlambat ?? 0)) {
                $a->update([
                    'status'          => $hasil['status'],
                    'menit_terlambat' => $hasil['menit_terlambat'],
                ]);
                $updated++;
            }
        }
        return $updated;
    }
}