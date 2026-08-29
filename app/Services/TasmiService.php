<?php

namespace App\Services;

use App\Models\TugasTasmi;
use App\Models\TugasTambahan;
use App\Models\PenugasanTambahan;
use App\Models\HafalanJuz;
use App\Models\Santri;
use App\Models\SettingTahfidz;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Tasmi' sebagai tugas tambahan ber-vakasi.
 * Alur: pengampu menunjuk penguji (juz harus 'selesai') → penguji menilai →
 * juz tasmi_lulus (jika lulus) + penugasan selesai → vakasi mengalir ke payroll.
 */
class TasmiService
{
    /** Pengampu menunjuk guru penguji untuk tasmi' satu juz seorang santri. */
    public function tunjukPenguji(int $santriId, int $juz, int $pengampuId, int $pengujiId, ?int $userId): TugasTasmi
    {
        $hj = HafalanJuz::where('santri_id', $santriId)->where('juz', $juz)->first();
        if (!$hj || $hj->status !== 'selesai') {
            throw new \DomainException("Juz $juz belum siap ditasmi' (harus selesai 1 juz penuh dulu).");
        }
        if (TugasTasmi::where('santri_id', $santriId)->where('juz', $juz)->where('status', 'ditugaskan')->exists()) {
            throw new \DomainException("Sudah ada penugasan tasmi' aktif untuk juz $juz.");
        }
        if ($pengujiId === $pengampuId) {
            throw new \DomainException('Penguji tasmi harus guru lain (bukan pengampu sendiri).');
        }

        $santri = Santri::findOrFail($santriId);
        // Nominal vakasi tasmi: utamakan Setting Vakasi (tipe 'tasmi' aktif),
        // fallback ke SettingTahfidz.vakasi_tasmi (kompat lama).
        $nominal = (float) (
            \App\Models\SettingVakasi::where('tipe_aktivitas', 'tasmi')->where('is_aktif', true)->value('nominal')
            ?? SettingTahfidz::get()->vakasi_tasmi
            ?? 0
        );

        return DB::transaction(function () use ($santri, $juz, $pengampuId, $pengujiId, $userId, $nominal) {
            $tt = TugasTambahan::create([
                'judul'           => "Tasmi' Juz $juz — {$santri->nama_lengkap}",
                'deskripsi'       => "Menguji tasmi' juz $juz santri {$santri->nama_lengkap}.",
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
                'disetujui'          => true, // ditunjuk pengampu → langsung sah
            ]);

            return TugasTasmi::create([
                'santri_id'         => $santri->id,
                'juz'               => $juz,
                'pengampu_id'       => $pengampuId,
                'penguji_id'        => $pengujiId,
                'tugas_tambahan_id' => $tt->id,
                'penugasan_id'      => $pn->id,
                'status'            => 'ditugaskan',
            ]);
        });
    }

    /** Daftar tasmi' yang ditugaskan ke seorang penguji (belum dinilai). */
    public function tasmiSaya(int $pengujiId)
    {
        return TugasTasmi::where('penguji_id', $pengujiId)->where('status', 'ditugaskan')
            ->with(['santri:id,nama_lengkap,nip', 'pengampu.user:id,name'])
            ->orderBy('created_at')->get();
    }

    /** Penguji menilai tasmi' → catat setoran, juz tasmi_lulus (jika lulus), penugasan selesai (vakasi). */
    /**
     * Penguji menilai tasmi' via 4 rubrik (masing-masing 1-10):
     * Kelancaran, Makhorijul Huruf, Tajwid, Fashohah.
     * Nilai akhir = rata-rata 4 rubrik (desimal); lulus bila rata-rata >= 8 (ambang tasmi').
     *
     * @param array{kelancaran:float,makhorijul_huruf:float,tajwid:float,fashohah:float} $rubrik
     */
    public function nilaiTasmi(int $tugasTasmiId, int $pengujiId, array $rubrik, ?string $catatan): array
    {
        $tt = TugasTasmi::findOrFail($tugasTasmiId);
        if ($tt->penguji_id !== $pengujiId) {
            throw new \DomainException('Tugas tasmi ini bukan milik Anda.');
        }
        if ($tt->status === 'selesai') {
            throw new \DomainException('Tasmi ini sudah dinilai.');
        }

        $komponen = [
            (float) $rubrik['kelancaran'],
            (float) $rubrik['makhorijul_huruf'],
            (float) $rubrik['tajwid'],
            (float) $rubrik['fashohah'],
        ];
        $nilai = round(array_sum($komponen) / count($komponen), 2); // rata-rata desimal

        // Catat sebagai setoran tasmi (set juz tasmi_lulus bila lulus, ambang tasmi 8) — reuse logika tahfidz.
        $setoran = (new TahfidzService())->catatSetoran([
            'santri_id'          => $tt->santri_id,
            'tenaga_pendidik_id' => $pengujiId,
            'jenis'              => 'tasmi',
            'juz'                => $tt->juz,
            'nilai'              => $nilai,
            'catatan'            => $catatan,
        ]);
        $lulus = (bool) $setoran->lulus;

        DB::transaction(function () use ($tt, $nilai, $lulus, $catatan, $rubrik) {
            $tt->update([
                'nilai'                  => $nilai,
                'nilai_kelancaran'       => $rubrik['kelancaran'],
                'nilai_makhorijul_huruf' => $rubrik['makhorijul_huruf'],
                'nilai_tajwid'           => $rubrik['tajwid'],
                'nilai_fashohah'         => $rubrik['fashohah'],
                'lulus'                  => $lulus,
                'catatan'                => $catatan,
                'status'                 => 'selesai',
            ]);

            // Selesaikan penugasan → vakasi mengalir ke payroll bulan ini.
            if ($tt->penugasan_id) {
                PenugasanTambahan::where('id', $tt->penugasan_id)->update([
                    'status_pengerjaan' => 'selesai',
                    'dilaporkan_pada'   => now(),
                    'laporan'           => "Tasmi' juz {$tt->juz} — nilai {$nilai}" . ($lulus ? ' (lulus)' : ' (belum lulus)'),
                ]);
            }
        });

        return ['lulus' => $lulus, 'nilai' => $nilai, 'rubrik' => $rubrik, 'ambang' => TahfidzService::NILAI_LULUS_TASMI];
    }
}
