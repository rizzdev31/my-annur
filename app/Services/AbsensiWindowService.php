<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\HariLibur;
use App\Models\LiburTendik;
use App\Models\PengajuanIzin;
use App\Models\SettingJamKerja;
use App\Models\TenagaPendidik;
use Carbon\Carbon;

/**
 * Sumber TUNGGAL status absen harian & window check-in (terhubung ke jam kerja).
 * Dipakai bersama oleh:
 *   - AbsensiApiController@hariIni  (halaman Absensi)
 *   - DashboardApiController@ringkasan (Beranda)
 * agar keduanya SELALU sepakat: tanggal kerja, sudah/belum absen, dan kapan
 * check-in boleh dilakukan.
 *
 * Aturan window: check-in BUKA 30 menit sebelum jam masuk jadwal, dan tetap
 * boleh (telat) sampai jam pulang. Sebelum window buka → belum boleh + hitung
 * mundur. Tanpa setting jam kerja → bebas check-in.
 */
class AbsensiWindowService
{
    /** Menit window check-in dibuka sebelum jam masuk jadwal. */
    public const BUKA_SEBELUM_MENIT = 30;

    /**
     * Status absen harian kanonik untuk satu tendik.
     *
     * @param  string|null  $deviceDate  Y-m-d dari perangkat (fallback: hari server).
     * @return array{
     *   kerja_date:string, hari:string, jam_masuk:?string, jam_pulang:?string,
     *   sudah_checkin:bool, sudah_checkout:bool, boleh_checkin:bool,
     *   bisa_checkin_mulai:?string, menit_menunggu_checkin:int,
     *   is_libur:bool, izin_aktif:?array, status:?string, absen:?AbsensiHarian,
     *   overnight_carry:bool
     * }
     */
    public static function statusAbsen(TenagaPendidik $tp, ?string $deviceDate = null): array
    {
        $today = TimezoneHelper::tanggalDariRequest($deviceDate);
        $now   = TimezoneHelper::now();

        $jamKerja  = $tp->jamKerjaAktif();
        $kerjaDate = self::resolveTanggalKerja($tp, $jamKerja, $today, $now);
        $namaHari  = TimezoneHelper::namaHariDB($kerjaDate);

        $absensi = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $kerjaDate)->first();

        $jadwal = $jamKerja ? TimezoneHelper::getJadwalHariIni($jamKerja, $kerjaDate) : null;

        // Window absolut (mendukung lintas hari).
        $checkinOpenDt = null; $jamPulangDt = null; $bisaCheckInMulai = null;
        if ($jadwal && isset($jadwal['jam_masuk'], $jadwal['jam_pulang'])) {
            $masukDt       = Carbon::parse($kerjaDate->toDateString() . ' ' . $jadwal['jam_masuk'], TimezoneHelper::TZ);
            $checkinOpenDt = $masukDt->copy()->subMinutes(self::BUKA_SEBELUM_MENIT);
            $jamPulangDt   = Carbon::parse($kerjaDate->toDateString() . ' ' . $jadwal['jam_pulang'], TimezoneHelper::TZ);
            if ($jadwal['lintas_hari'] ?? false) $jamPulangDt->addDay();
            $bisaCheckInMulai = $checkinOpenDt->format('H:i');
        }

        $izinAktif   = self::deteksiIzinAktif($tp->id, $kerjaDate->toDateString());
        $isDinasLuar = $izinAktif && $izinAktif['status_absensi'] === 'dinas_luar';

        $hariLiburAktif = HariLibur::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $kerjaDate->toDateString())
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $kerjaDate->toDateString()))
            ->exists();
        $liburMingguan = $jamKerja && $jamKerja->isHariLibur($namaHari);
        $isLibur       = $hariLiburAktif || $liburMingguan;

        $sudahCheckin  = $absensi?->jam_masuk  !== null;
        $sudahCheckout = $absensi?->jam_pulang !== null;

        // ── Eligibility check-in (otoritatif, overnight-aware) ──────────────
        $bolehCheckin = false; $menitMenunggu = 0;
        if ($isDinasLuar) {
            $bolehCheckin = !$sudahCheckin;
            $bisaCheckInMulai = null;
        } elseif (!$isLibur && $izinAktif === null && !$sudahCheckin) {
            if (!$checkinOpenDt) {
                $bolehCheckin = true; // tak ada setting → bebas
            } elseif ($now->gte($checkinOpenDt) && $jamPulangDt && $now->lte($jamPulangDt)) {
                $bolehCheckin = true;
            } elseif ($now->lt($checkinOpenDt)) {
                $menitMenunggu = (int) ceil($now->diffInMinutes($checkinOpenDt, false));
            }
        }

        return [
            'kerja_date'             => $kerjaDate->toDateString(),
            'hari'                   => ucfirst($kerjaDate->locale('id')->isoFormat('dddd')),
            'jam_masuk'              => isset($jadwal['jam_masuk'])  ? substr((string) $jadwal['jam_masuk'], 0, 5)  : null,
            'jam_pulang'             => isset($jadwal['jam_pulang']) ? substr((string) $jadwal['jam_pulang'], 0, 5) : null,
            'sudah_checkin'          => $sudahCheckin,
            'sudah_checkout'         => $sudahCheckout,
            'boleh_checkin'          => $bolehCheckin,
            'bisa_checkin_mulai'     => $bisaCheckInMulai,
            'menit_menunggu_checkin' => $menitMenunggu,
            'is_libur'               => $isLibur,
            'izin_aktif'             => $izinAktif,
            'status'                 => $absensi?->status,
            'absen'                  => $absensi,
            'overnight_carry'        => $kerjaDate->toDateString() !== $today->toDateString(),
        ];
    }

    /**
     * Tanggal KERJA efektif (overnight-aware): bila shift lintas-hari kemarin
     * masih berjalan & shift hari ini belum buka → pakai tanggal kemarin.
     */
    public static function resolveTanggalKerja(TenagaPendidik $tp, ?SettingJamKerja $jamKerja, Carbon $today, Carbon $now): Carbon
    {
        if (!$jamKerja) return $today;

        $adaHariIni = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $today)->exists();
        if ($adaHariIni) return $today;

        $yesterday = $today->copy()->subDay();
        $yJadwal = $jamKerja->getJamUntukHari(TimezoneHelper::namaHariDB($yesterday));
        if ($yJadwal && ($yJadwal['lintas_hari'] ?? false)) {
            $yStart = Carbon::parse($yesterday->toDateString() . ' ' . $yJadwal['jam_masuk'], TimezoneHelper::TZ)->subMinutes(self::BUKA_SEBELUM_MENIT);
            $yEnd   = Carbon::parse($yesterday->toDateString() . ' ' . $yJadwal['jam_pulang'], TimezoneHelper::TZ)->addDay();
            if ($now->gte($yStart) && $now->lte($yEnd)) {
                $tJadwal = $jamKerja->getJamUntukHari(TimezoneHelper::namaHariDB($today));
                $tOpen = $tJadwal
                    ? Carbon::parse($today->toDateString() . ' ' . $tJadwal['jam_masuk'], TimezoneHelper::TZ)->subMinutes(self::BUKA_SEBELUM_MENIT)
                    : null;
                if (!$tOpen || $now->lt($tOpen)) return $yesterday;
            }
        }
        return $today;
    }

    /** Izin/cuti disetujui yang aktif pada tanggal tsb (atau null). */
    public static function deteksiIzinAktif(int $tpId, string $tanggal): ?array
    {
        $izin = PengajuanIzin::where('tenaga_pendidik_id', $tpId)
            ->where('status', 'disetujui')
            ->where('is_sementara', false)   // izin sementara TIDAK memengaruhi absen harian
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where('tanggal_selesai', '>=', $tanggal)
            ->with('jenisPengajuan')
            ->first();

        if (!$izin) return null;

        return [
            'ada'             => true,
            'jenis'           => $izin->jenisPengajuan?->nama ?? 'Izin',
            'kategori'        => $izin->jenisPengajuan?->kategori ?? 'izin',
            'status_absensi'  => $izin->getStatusAbsensi(),
            'tanggal_mulai'   => $izin->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $izin->tanggal_selesai?->toDateString(),
        ];
    }
}
