<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use Carbon\Carbon;

/**
 * Auto-Checkout Absensi Harian.
 *
 * Menutup baris absensi yang SUDAH check-in tapi TIDAK PERNAH check-out,
 * setelah kesempatan check-out manual benar-benar habis. Jam pulang diisi
 * sesuai JADWAL (bukan waktu perintah berjalan) agar durasi kerja tetap masuk
 * akal dan riwayat tidak terbaca seolah guru bekerja sampai tengah malam.
 *
 * Kapan sebuah shift dianggap "habis kesempatannya"?
 * Persis saat check-out manual tidak lagi mungkin, yaitu ketika window
 * check-in shift BERIKUTNYA sudah dibuka (lihat AbsensiWindowService:
 * shift kemarin dilepas begitu shift hari ini buka). Dengan begitu tidak ada
 * celah — dan tidak ada baris yang ditutup selagi guru masih boleh check out
 * sendiri. Bila hari berikutnya libur (tak ada jadwal), dipakai batas keras
 * jam pulang + BATAS_KERAS_JAM.
 *
 * Idempotent: baris yang sudah punya jam_pulang tidak pernah disentuh.
 */
class AbsensiAutoCheckoutService
{
    /** Batas cadangan bila hari berikutnya tidak punya jadwal (libur). */
    public const BATAS_KERAS_JAM = 12;

    /**
     * @param  string|null $sejak            Tanggal kerja paling awal diproses (Y-m-d).
     * @param  string|null $sampai           Tanggal kerja paling akhir (default: kemarin).
     * @param  bool        $hanyaLintasHari  Batasi ke shift malam saja.
     * @param  bool        $dryRun           Hitung saja, jangan menulis.
     * @return array{diproses:int,ditutup:int,dilewati:int,rincian:array}
     */
    public function tutup(
        ?string $sejak = null, ?string $sampai = null,
        bool $hanyaLintasHari = false, bool $dryRun = false
    ): array {
        $now = TimezoneHelper::now();

        // Default: hanya sampai kemarin — hari ini masih mungkin dikerjakan guru.
        $sampai ??= TimezoneHelper::today()->subDay()->toDateString();
        $sejak  ??= TimezoneHelper::today()->subDays(60)->toDateString();

        $baris = AbsensiHarian::with('tenagaPendidik.user:id,name')
            ->whereBetween('tanggal', [$sejak, $sampai])
            ->whereNotNull('jam_masuk')->whereNull('jam_pulang')
            ->orderBy('tanggal')->get();

        $hasil = ['diproses' => $baris->count(), 'ditutup' => 0, 'dilewati' => 0, 'rincian' => []];

        foreach ($baris as $a) {
            $tp = $a->tenagaPendidik;
            if (!$tp) { $hasil['dilewati']++; continue; }

            $tgl      = $a->tanggal->toDateString();
            $jamKerja = $tp->jamKerjaAktif($tgl);
            if (!$jamKerja) { $hasil['dilewati']++; continue; }

            $jadwal = $jamKerja->getJamUntukHari(TimezoneHelper::namaHariDB($a->tanggal));
            if (!$jadwal || empty($jadwal['jam_pulang'])) { $hasil['dilewati']++; continue; }

            $lintas = (bool) ($jadwal['lintas_hari'] ?? false);
            if ($hanyaLintasHari && !$lintas) { $hasil['dilewati']++; continue; }

            $akhirShift = Carbon::parse("$tgl {$jadwal['jam_pulang']}", TimezoneHelper::TZ);
            if ($lintas) $akhirShift->addDay();

            if ($now->lte($this->batasManual($tp, $a->tanggal, $akhirShift))) {
                $hasil['dilewati']++;   // guru masih boleh check out sendiri
                continue;
            }

            // Guru yang check-in SETELAH jam pulang jadwal (shift non-lintas,
            // datang sangat telat): memakai jam pulang jadwal akan membuat
            // pulang mendahului masuk. Pakai jam masuk → durasi 0, bukan negatif.
            $jamPulang = $akhirShift->format('H:i:s');
            if (!$lintas && $a->jam_masuk > $jadwal['jam_pulang']) {
                $jamPulang = $a->jam_masuk;
            }

            $hasil['rincian'][] = [
                'tanggal' => $tgl,
                'guru'    => $tp->user?->name ?? '—',
                'masuk'   => substr((string) $a->jam_masuk, 0, 5),
                'pulang'  => substr($jamPulang, 0, 5),
                'lintas'  => $lintas,
            ];

            if (!$dryRun) {
                $a->update([
                    'jam_pulang'  => $jamPulang,
                    'keterangan'  => trim(($a->keterangan ? $a->keterangan . ' | ' : '')
                        . 'Check out otomatis: tidak melakukan check out sampai batas waktu, '
                        . 'jam pulang diisi sesuai jadwal (' . substr($jamPulang, 0, 5) . ').'),
                ]);
            }
            $hasil['ditutup']++;
        }

        return $hasil;
    }

    /**
     * Batas akhir guru masih boleh check-out sendiri = saat window check-in
     * hari berikutnya dibuka. Bila hari itu tak berjadwal, pakai batas keras.
     */
    private function batasManual($tp, Carbon $tanggalShift, Carbon $akhirShift): Carbon
    {
        $besok    = $tanggalShift->copy()->addDay();
        $jamKerja = $tp->jamKerjaAktif($besok->toDateString());
        $jadwal   = $jamKerja?->getJamUntukHari(TimezoneHelper::namaHariDB($besok));

        if ($jadwal && !empty($jadwal['jam_masuk'])) {
            $buka = Carbon::parse($besok->toDateString() . ' ' . $jadwal['jam_masuk'], TimezoneHelper::TZ)
                ->subMinutes(AbsensiWindowService::BUKA_SEBELUM_MENIT);

            // Untuk shift malam, window besok bisa jatuh SEBELUM shift ini
            // berakhir — jangan sampai menutup shift yang masih berjalan.
            return $buka->gt($akhirShift) ? $buka : $akhirShift->copy()->addHours(self::BATAS_KERAS_JAM);
        }

        return $akhirShift->copy()->addHours(self::BATAS_KERAS_JAM);
    }
}
