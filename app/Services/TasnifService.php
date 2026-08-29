<?php

namespace App\Services;

use App\Models\TugasTasnif;
use App\Models\TugasTambahan;
use App\Models\PenugasanTambahan;
use App\Models\Santri;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * TASNIF = ujian kenaikan level Tahsin (analog Tasmi' pada Tahfidz).
 * Alur: pengampu menunjuk penguji (level harus selesai — semua materi lulus) →
 * penguji menilai 4 rubrik → rata-rata >= 8 → santri NAIK LEVEL + penugasan selesai
 * (vakasi mengalir ke payroll). Tidak menyentuh fitur tasmi'.
 */
class TasnifService
{
    /** Ambang lulus tasnif (rata-rata 4 rubrik), sama seperti tasmi'. */
    public const NILAI_LULUS = 8.0;

    /** Pengampu menunjuk penguji ujian kenaikan level untuk seorang santri. */
    public function tunjukPenguji(int $santriId, int $level, int $pengampuId, int $pengujiId, ?int $userId): TugasTasnif
    {
        // Level harus selesai (semua materi level tsb lulus) sebelum diujikan.
        if (!(new TahsinService())->levelSelesai($santriId, $level)) {
            throw new \DomainException('Semua materi level ini harus lulus dulu sebelum ujian kenaikan (tasnif).');
        }
        if (TugasTasnif::where('santri_id', $santriId)->where('level', $level)->where('status', 'ditugaskan')->exists()) {
            throw new \DomainException("Sudah ada penugasan tasnif aktif untuk level $level.");
        }

        $santri  = Santri::findOrFail($santriId);
        $label   = TahsinService::levelLabel($level);
        // Nominal vakasi tasnif: SettingVakasi tipe 'tasnif' aktif (bila ada), else 0.
        $nominal = (float) (\App\Models\SettingVakasi::where('tipe_aktivitas', 'tasnif')->where('is_aktif', true)->value('nominal') ?? 0);

        return DB::transaction(function () use ($santri, $level, $label, $pengampuId, $pengujiId, $userId, $nominal) {
            $tt = TugasTambahan::create([
                'judul'           => "Tasnif $label — {$santri->nama_lengkap}",
                'deskripsi'       => "Ujian kenaikan $label santri {$santri->nama_lengkap}.",
                'tanggal_mulai'   => Carbon::today()->toDateString(),
                'tipe'            => 'individu',
                'tipe_pengerjaan' => 'mandiri',
                'vakasi_override' => $nominal,
                'wajib_laporan'   => false,
                'status'          => 'aktif',
                'dibuat_oleh'     => $userId,
            ]);

            $pn = PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tt->id,
                'tenaga_pendidik_id' => $pengujiId,
                'status_pengerjaan'  => 'belum',
                'disetujui'          => true,
            ]);

            return TugasTasnif::create([
                'santri_id'         => $santri->id,
                'level'             => $level,
                'pengampu_id'       => $pengampuId,
                'penguji_id'        => $pengujiId,
                'tugas_tambahan_id' => $tt->id,
                'penugasan_id'      => $pn->id,
                'status'            => 'ditugaskan',
            ]);
        });
    }

    /** Daftar tasnif yang ditugaskan ke seorang penguji (belum dinilai). */
    public function tasnifSaya(int $pengujiId)
    {
        return TugasTasnif::where('penguji_id', $pengujiId)->where('status', 'ditugaskan')
            ->with(['santri:id,nama_lengkap,nip', 'pengampu.user:id,name'])
            ->orderBy('created_at')->get();
    }

    /**
     * Penguji menilai tasnif via 4 rubrik (1-10): Pemahaman Materi, Kelancaran,
     * Fashohah, Makhorijul Huruf. Nilai akhir = rata-rata; lulus bila >= 8 → naik level.
     *
     * @param array{pemahaman_materi:float,kelancaran:float,fashohah:float,makhorijul_huruf:float} $rubrik
     */
    public function nilaiTasnif(int $tugasTasnifId, int $pengujiId, array $rubrik, ?string $catatan): array
    {
        $tt = TugasTasnif::findOrFail($tugasTasnifId);
        if ($tt->penguji_id !== $pengujiId) {
            throw new \DomainException('Tugas tasnif ini bukan milik Anda.');
        }
        if ($tt->status === 'selesai') {
            throw new \DomainException('Tasnif ini sudah dinilai.');
        }

        $komponen = [
            (float) $rubrik['pemahaman_materi'],
            (float) $rubrik['kelancaran'],
            (float) $rubrik['fashohah'],
            (float) $rubrik['makhorijul_huruf'],
        ];
        $nilai = round(array_sum($komponen) / count($komponen), 2);
        $lulus = $nilai >= self::NILAI_LULUS;

        $naik = null;
        DB::transaction(function () use ($tt, $nilai, $lulus, $catatan, $rubrik, &$naik) {
            $tt->update([
                'nilai'                  => $nilai,
                'nilai_pemahaman_materi' => $rubrik['pemahaman_materi'],
                'nilai_kelancaran'       => $rubrik['kelancaran'],
                'nilai_fashohah'         => $rubrik['fashohah'],
                'nilai_makhorijul_huruf' => $rubrik['makhorijul_huruf'],
                'lulus'                  => $lulus,
                'catatan'                => $catatan,
                'status'                 => 'selesai',
            ]);

            if ($lulus) {
                // LULUS ujian → naik level (override: ujian adalah otoritas kenaikan).
                $naik = (new TahsinService())->naikLevel($tt->santri_id, true);
            }

            // Selesaikan penugasan → vakasi mengalir ke payroll bulan ini.
            if ($tt->penugasan_id) {
                PenugasanTambahan::where('id', $tt->penugasan_id)->update([
                    'status_pengerjaan' => 'selesai',
                    'dikerjakan_pada'   => now(),
                    'dilaporkan_pada'   => now(),
                    'laporan'           => "Tasnif level {$tt->level} — nilai {$nilai}" . ($lulus ? ' (lulus, naik level)' : ' (belum lulus)'),
                ]);
            }
        });

        return ['lulus' => $lulus, 'nilai' => $nilai, 'rubrik' => $rubrik, 'ambang' => self::NILAI_LULUS, 'naik' => $naik];
    }
}
