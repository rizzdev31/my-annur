<?php

namespace App\Services;

use App\Models\IzinSantri;
use App\Models\SmartHealthLaporan;
use Illuminate\Support\Collection;

/**
 * Status kehadiran santri — SUMBER TUNGGAL untuk semua roster absensi
 * (mengajar reguler, tahfidz, tahsin).
 *
 * Menyatukan dua sinkronisasi yang sebelumnya disalin di beberapa controller:
 *   • Perizinan Santri  : izin DISETUJUI yang mencakup tanggal sesi → 'izin'
 *   • Smart Health      : laporan sakit yang masih berjalan          → 'sakit'
 *
 * Guru tetap bisa menimpa manual — ini hanya nilai AWAL agar guru tidak perlu
 * mengingat siapa yang sedang izin/sakit, dan agar santri yang sudah punya
 * surat izin tidak terlanjur tercatat alpha.
 *
 * Urutan prioritas: status tersimpan > sakit > izin > hadir.
 * Sakit didahulukan karena laporan kesehatan yang masih berjalan berarti
 * santri memang sedang tidak bisa mengikuti pelajaran, apa pun izinnya.
 */
class KehadiranSantriService
{
    /** Status yang sah untuk absensi santri (dipakai validasi & UI). */
    public const STATUS = ['hadir', 'telat', 'izin', 'sakit', 'alpha'];

    /** Aturan validasi Laravel untuk field status. */
    public static function aturanStatus(): string
    {
        return 'required|in:' . implode(',', self::STATUS);
    }

    /**
     * Ambil konteks izin & sakit untuk sekumpulan santri pada satu tanggal.
     *
     * @return array{izin: Collection, sakit: Collection}  izin di-key santri_id;
     *         sakit berupa himpunan (flip) santri_id.
     */
    public function konteks(Collection|array $santriIds, string $tanggal): array
    {
        $ids = collect($santriIds)->all();
        if (empty($ids)) return ['izin' => collect(), 'sakit' => collect()];

        return [
            'izin' => IzinSantri::where('status', 'disetujui')
                ->whereIn('santri_id', $ids)
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->get()->keyBy('santri_id'),

            // Laporan yang belum selesai/sembuh → santri masih sakit.
            'sakit' => SmartHealthLaporan::whereIn('status', ['menunggu', 'dalam_pengecekan'])
                ->whereIn('santri_id', $ids)
                ->pluck('santri_id')->flip(),
        ];
    }

    /**
     * Kirim WA kehadiran ke wali, dengan aturan anti-notifikasi-ganda:
     *   • 'izin'  → dilewati; wali sudah dikabari saat izin DISETUJUI di modul
     *               Perizinan Santri.
     *   • 'sakit' → dilewati HANYA bila bersumber Smart Health (wali sudah
     *               dikabari saat lapor sakit). Sakit yang ditandai manual oleh
     *               guru tetap dikirim, karena belum tentu wali tahu.
     *
     * @param  \Illuminate\Support\Collection  $rows  baris AbsensiSantri
     */
    public function kirimWa(Collection $rows, string $pembelajaran, string $tanggal): void
    {
        if ($rows->isEmpty()) return;

        $sakitHealth = SmartHealthLaporan::whereIn('status', ['menunggu', 'dalam_pengecekan'])
            ->whereIn('santri_id', $rows->pluck('santri_id'))
            ->pluck('santri_id')->flip();

        foreach ($rows as $as) {
            if ($as->status === 'izin') continue;
            if ($as->status === 'sakit' && $sakitHealth->has($as->santri_id)) continue;

            app(WaService::class)->absenMengajar(
                $as->santri_id, $as->status, $pembelajaran, $tanggal, $as->id
            );
        }
    }

    /**
     * Bagian payload roster untuk satu santri: status awal + penanda asal-usul
     * (dipakai UI untuk menampilkan lencana "Izin"/"Sakit").
     */
    public function baris(int $santriId, array $konteks, ?string $tersimpan = null): array
    {
        $izin  = $konteks['izin']->get($santriId);
        $sakit = $konteks['sakit']->has($santriId);

        return [
            'status'         => $tersimpan ?? ($sakit ? 'sakit' : ($izin ? 'izin' : 'hadir')),
            'izin_disetujui' => (bool) $izin,
            'izin_jenis'     => $izin?->jenis_label,   // "Syar'i" / "Non-Syar'i"
            'sakit_health'   => $sakit,
        ];
    }
}
