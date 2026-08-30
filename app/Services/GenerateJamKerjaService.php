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
    public function generate(SettingJamKerja $template, array $guruIds): array
    {
        $tpl        = $template->jadwal_per_hari ?? [];
        $generated  = [];
        $dilewati   = [];
        $peringatan = [];

        $gurus = TenagaPendidik::with('user')->whereIn('id', $guruIds)->get();

        foreach ($gurus as $guru) {
            $nama = $guru->user?->name ?? ('Guru #' . $guru->id);
            $hariMengajar = $this->hariMengajar($guru);

            // Keputusan: guru tanpa jadwal mengajar → DILEWATI (cegah libur total).
            if (empty($hariMengajar)) {
                $dilewati[] = $nama;
                continue;
            }

            $jadwal = [];
            $hariTanpaTemplate = [];
            foreach (self::HARI as $hari) {
                $td      = $tpl[$hari] ?? null;
                $ngajar  = in_array($hari, $hariMengajar, true);
                $adaWaktu = $td && !empty($td['jam_masuk']) && !empty($td['jam_pulang']);

                if ($ngajar && !$adaWaktu) {
                    $hariTanpaTemplate[] = $hari;   // ngajar tapi template tak punya jam hari itu
                }

                $jadwal[$hari] = [
                    'jam_masuk'  => $td['jam_masuk']  ?? null,
                    'jam_pulang' => $td['jam_pulang'] ?? null,
                    'toleransi'  => $td['toleransi']  ?? $template->toleransi_terlambat ?? 15,
                    'aktif'      => $ngajar && $adaWaktu,   // masuk hanya jika ngajar & template punya jam
                ];
            }

            // Satu setting per-guru (re-generate memperbarui baris yang sama).
            $setting = SettingJamKerja::updateOrCreate(
                ['tenaga_pendidik_id' => $guru->id, 'is_template' => false],
                [
                    'nama'                    => 'Jam Kerja — ' . $nama,
                    'jadwal_per_hari'         => $jadwal,
                    'gunakan_jadwal_per_hari' => true,
                    'toleransi_terlambat'     => $template->toleransi_terlambat,
                    'is_default'              => false,
                    'is_aktif'                => true,
                    'induk_template_id'       => $template->id,
                ]
            );

            $guru->update(['setting_jam_kerja_id' => $setting->id]);
            $generated[] = $nama;

            if ($hariTanpaTemplate) {
                $peringatan[] = $nama . ' mengajar di hari (' . implode(', ', $hariTanpaTemplate)
                    . ') yang tidak ada di template — hari itu tidak diaktifkan.';
            }
        }

        return ['generated' => $generated, 'dilewati' => $dilewati, 'peringatan' => $peringatan];
    }
}
