<?php

namespace App\Observers;

use App\Models\RekapKinerjaBulanan;
use App\Models\RiwayatRekapKinerja;

/**
 * Menyalin nilai LAMA ke riwayat setiap kali baris rekap kinerja berubah.
 *
 * Dipasang di event `updating` (bukan di controller) supaya SEMUA jalur
 * perubahan tertangkap: reset, reset massal, override, catatan, dan
 * penghitungan ulang yang terjadi saat halaman detail/raport dibuka.
 */
class RekapKinerjaBulananObserver
{
    /** Kolom yang bila berubah dianggap perubahan berarti (layak dicatat). */
    private const PENTING = [
        'skor_total', 'skor_absensi', 'skor_tugas', 'skor_administrasi', 'skor_piket',
        'sudah_dikunci', 'catatan_superadmin',
    ];

    /**
     * Baris rekap yang BARU LAHIR juga dicatat, dengan sebab 'awal'.
     *
     * Observer ini dulu hanya memasang diri di `updating`, sehingga kemunculan
     * PERTAMA sebuah skor tidak pernah masuk riwayat. Terukur 27 Okt 2026:
     * 25 baris rekap tidak punya satu pun jejak, dan di layar log aktivitas
     * skor itu seolah muncul dari ketiadaan. Skor lamanya dibiarkan null —
     * itulah yang membedakan "nilai awal" dari "naik/turun".
     */
    public function created(RekapKinerjaBulanan $rekap): void
    {
        $konteks = RekapKinerjaBulanan::$konteksPerubahan;
        RekapKinerjaBulanan::$konteksPerubahan = [];   // sekali pakai

        RiwayatRekapKinerja::create([
            'rekap_kinerja_bulanan_id' => $rekap->id,
            'tenaga_pendidik_id'       => $rekap->tenaga_pendidik_id,
            'bulan'                    => $rekap->bulan,
            'tahun'                    => $rekap->tahun,
            // null = belum pernah ada skor sebelumnya.
            'skor_total'               => null,
            'skor_total_baru'          => $rekap->skor_total,
            'sudah_dikunci_lama'       => false,
            'sebab'                    => 'awal',
            'alasan'                   => $konteks['alasan'] ?? null,
            'diubah_oleh'              => $konteks['aktor'] ?? auth()->id(),
            'setting_kinerja_id'       => $rekap->setting_kinerja_id,
        ]);
    }

    public function updating(RekapKinerjaBulanan $rekap): void
    {
        $berubah = collect(self::PENTING)->contains(function ($kolom) use ($rekap) {
            if (!array_key_exists($kolom, $rekap->getDirty())) return false;
            $lama = $rekap->getOriginal($kolom);
            $baru = $rekap->$kolom;
            // Angka dibandingkan sebagai angka agar 100 vs 100.00 tidak dianggap berubah.
            if (is_numeric($lama) && is_numeric($baru)) {
                return round((float) $lama, 2) !== round((float) $baru, 2);
            }
            return (string) $lama !== (string) $baru;
        });

        if (!$berubah) return;

        $konteks = RekapKinerjaBulanan::$konteksPerubahan;
        RekapKinerjaBulanan::$konteksPerubahan = [];   // sekali pakai

        RiwayatRekapKinerja::create([
            'rekap_kinerja_bulanan_id' => $rekap->id,
            'tenaga_pendidik_id'       => $rekap->tenaga_pendidik_id,
            'bulan'                    => $rekap->bulan,
            'tahun'                    => $rekap->tahun,
            'skor_total'               => $rekap->getOriginal('skor_total'),
            'skor_absensi'             => $rekap->getOriginal('skor_absensi'),
            'skor_tugas'               => $rekap->getOriginal('skor_tugas'),
            'skor_administrasi'        => $rekap->getOriginal('skor_administrasi'),
            'skor_piket'               => $rekap->getOriginal('skor_piket'),
            'skor_total_baru'          => $rekap->skor_total,
            'sudah_dikunci_lama'       => (bool) $rekap->getOriginal('sudah_dikunci'),
            'catatan_superadmin_lama'  => $rekap->getOriginal('catatan_superadmin'),
            'data_lama'                => $rekap->getOriginal(),
            'sebab'                    => $konteks['sebab'] ?? 'hitung_ulang',
            'alasan'                   => $konteks['alasan'] ?? null,
            'diubah_oleh'              => $konteks['aktor'] ?? auth()->id(),
            'setting_kinerja_id'       => $rekap->getOriginal('setting_kinerja_id'),
        ]);
    }
}
