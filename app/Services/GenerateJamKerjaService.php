<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use App\Models\SettingJamKerja;
use App\Models\TenagaPendidik;

/**
 * Generate Jam Kerja per-guru dari JADWAL MENGAJAR.
 *
 * Template = waktu per hari (Sen–Kam/Jum/Sab). Untuk tiap guru, hari yang ADA
 * jadwal mengajar → masuk (pakai jam template); hari tanpa jadwal → libur.
 * Hasilnya SettingJamKerja per-guru (is_template=false) yang di-assign ke guru,
 * sehingga absensi/kinerja/payroll (yang membaca getJamUntukHari) otomatis ikut.
 *
 * v1: snapshot saat generate (jadwal berubah → generate ulang).
 */
class GenerateJamKerjaService
{
    private const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];

    /** Hari mengajar unik guru (tahun ajaran aktif). */
    public function hariMengajar(TenagaPendidik $guru): array
    {
        return JadwalMengajar::where('tenaga_pendidik_id', $guru->id)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->distinct()->pluck('hari')
            ->map(fn ($h) => strtolower((string) $h))->unique()->values()->all();
    }

    /**
     * Generate/refresh jam kerja per-guru dari template.
     *
     * @param  int[]  $guruIds
     * @return array{generated:string[], dilewati:string[], peringatan:string[]}
     */
    /**
     * @param  string  $mode   'mengajar' (libur=hari tanpa jadwal) | 'libur' (libur=hari_libur guru)
     * @param  bool    $paksa  true = timpa walau guru sedang shift khusus (asrama/satpam)
     */
    public function generate(SettingJamKerja $template, array $guruIds, string $mode = 'mengajar', bool $paksa = false): array
    {
        $tpl            = $template->jadwal_per_hari ?? [];
        $hariTemplate   = $this->hariTemplate($tpl);   // hari yg aktif & punya jam di template
        $templateKhusus = (bool) $template->is_shift_khusus;
        $generated      = [];
        $dilewati       = [];
        $dilewatiKhusus = [];
        $peringatan     = [];

        [$repMasuk, $repPulang] = $this->jamRepresentatif($template, $tpl);

        $gurus = TenagaPendidik::with('user')->whereIn('id', $guruIds)->get();

        foreach ($gurus as $guru) {
            $nama = $guru->user?->name ?? ('Guru #' . $guru->id);

            // PENGAMAN: jangan menimpa guru yang sedang SHIFT KHUSUS (asrama/satpam)
            // dengan template REGULER — kecuali dipaksa. Cegah jam khusus tertimpa.
            if (!$paksa && !$templateKhusus && $this->guruShiftKhusus($guru)) {
                $dilewatiKhusus[] = $nama;
                continue;
            }

            // Tentukan HARI MASUK menurut mode.
            if ($mode === 'libur') {
                // Kerja semua hari template KECUALI hari libur mingguan guru.
                $liburGuru = array_map('strtolower', (array) ($guru->hari_libur ?? []));
                $hariMasuk = array_values(array_diff($hariTemplate, $liburGuru));
                // Guru tanpa hari_libur → kerja semua hari template (keputusan).
            } else {
                $hariMengajarSemua = $this->hariMengajar($guru);
                if (empty($hariMengajarSemua)) {   // tanpa jadwal → dilewati
                    $dilewati[] = $nama;
                    continue;
                }
                $hariMasuk = array_values(array_intersect($hariMengajarSemua, $hariTemplate));
                $luar = array_diff($hariMengajarSemua, $hariTemplate);
                if ($luar) {
                    $peringatan[] = $nama . ' mengajar di hari (' . implode(', ', $luar)
                        . ') yang tidak aktif di template — hari itu tidak diaktifkan.';
                }
            }

            $jadwal = [];
            foreach (self::HARI as $hari) {
                $td = $tpl[$hari] ?? null;
                $jadwal[$hari] = [
                    'jam_masuk'  => $td['jam_masuk']  ?? null,
                    'jam_pulang' => $td['jam_pulang'] ?? null,
                    'toleransi'  => $td['toleransi']  ?? $template->toleransi_terlambat ?? 15,
                    'aktif'      => in_array($hari, $hariMasuk, true),
                ];
            }

            // Satu setting per-guru (re-generate memperbarui baris yang sama).
            $setting = SettingJamKerja::updateOrCreate(
                ['tenaga_pendidik_id' => $guru->id, 'is_template' => false],
                [
                    'nama'                    => 'Jam Kerja — ' . $nama,
                    'jadwal_per_hari'         => $jadwal,
                    'gunakan_jadwal_per_hari' => true,
                    'toleransi_terlambat'     => $template->toleransi_terlambat ?? 15,
                    'jam_masuk'               => $repMasuk,
                    'jam_pulang'              => $repPulang,
                    'hari_kerja'              => [],
                    'total_jam_kerja_sehari'  => $template->total_jam_kerja_sehari ?? 480,
                    'is_default'              => false,
                    'is_aktif'                => true,
                    'is_shift_khusus'         => $templateKhusus, // wariskan flag dari template
                    'induk_template_id'       => $template->id,
                ]
            );

            $guru->update(['setting_jam_kerja_id' => $setting->id]);
            $generated[] = $nama;
        }

        return [
            'generated'       => $generated,
            'dilewati'        => $dilewati,
            'dilewati_khusus' => $dilewatiKhusus,
            'peringatan'      => $peringatan,
        ];
    }

    /** Apakah guru sedang memakai jam kerja SHIFT KHUSUS (asrama/satpam)? */
    private function guruShiftKhusus(TenagaPendidik $guru): bool
    {
        $s = $guru->jamKerjaAktif();
        if (!$s) return false;
        // Flag pada setting itu sendiri, atau diwarisi dari template induknya.
        return $s->is_shift_khusus || (bool) ($s->indukTemplate?->is_shift_khusus);
    }

    /** Hari yang AKTIF & punya jam di template (kandidat hari kerja). */
    private function hariTemplate(array $tpl): array
    {
        $days = [];
        foreach (self::HARI as $h) {
            $td = $tpl[$h] ?? null;
            if ($td && ($td['aktif'] ?? false) && !empty($td['jam_masuk']) && !empty($td['jam_pulang'])) {
                $days[] = $h;
            }
        }
        return $days;
    }

    /** Jam masuk/pulang representatif utk isi kolom legacy NOT NULL. */
    private function jamRepresentatif(SettingJamKerja $template, array $tpl): array
    {
        $masuk = $template->jam_masuk;
        $pulang = $template->jam_pulang;
        if (!$masuk || !$pulang) {
            foreach (self::HARI as $h) {
                if (!empty($tpl[$h]['jam_masuk']) && !empty($tpl[$h]['jam_pulang'])) {
                    $masuk = $masuk ?: $tpl[$h]['jam_masuk'];
                    $pulang = $pulang ?: $tpl[$h]['jam_pulang'];
                    break;
                }
            }
        }
        return [$masuk ?: '07:00', $pulang ?: '15:00'];
    }
}
