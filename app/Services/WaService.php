<?php

namespace App\Services;

use App\Models\Santri;
use App\Models\WaOutbox;
use App\Jobs\KirimWaJob;

/**
 * Memasukkan notifikasi WA ke outbox + dispatch job pengiriman (Fonnte).
 * Idempotent via ref_id (firstOrCreate). Lewati (skipped) bila santri tak punya
 * nomor WA. Hanya dispatch bila Fonnte aktif (config('fonnte.enabled')).
 */
class WaService
{
    /**
     * @param string      $jenis  controlling|mengajar|pelanggaran|apresiasi|konselor
     * @param Santri|null $santri penerima (nomor diambil dari santri.no_whatsapp wali/ortu)
     * @param string      $pesan  isi WA (sudah jadi)
     * @param string      $refId  kunci idempotency stabil (mis. WA-CTRL-{absensi_id})
     */
    public function enqueue(string $jenis, ?Santri $santri, string $pesan, string $refId, ?string $mediaUrl = null): WaOutbox
    {
        $tujuan = $santri ? FonnteClient::normalisasiNomor($santri->no_whatsapp) : null;

        $row = WaOutbox::firstOrCreate(
            ['ref_id' => $refId],
            [
                'santri_id' => $santri?->id,
                'jenis'     => $jenis,
                'pesan'     => $pesan,
                'media_url' => $mediaUrl,
                'tujuan'    => $tujuan,
                'status'    => $tujuan ? 'pending' : 'skipped',
            ]
        );

        if ($row->wasRecentlyCreated && $row->status === 'pending' && config('fonnte.enabled')) {
            KirimWaJob::dispatch($row->id);
        }

        return $row;
    }

    /**
     * Helper khusus absensi mengajar santri (tahfidz/tahsin/reguler/pengganti).
     * Memuat santri, menyusun pesan, lalu enqueue. Idempotent per baris absensi_santri.
     */
    public function absenMengajar(int $santriId, string $status, string $pembelajaran, string $tgl, int $absensiSantriId): void
    {
        $santri = Santri::find($santriId);
        if (!$santri) return;
        $pesan = WaTemplate::mengajar($santri->nama_lengkap, $status, $pembelajaran, $tgl);
        $this->enqueue('mengajar', $santri, $pesan, 'WA-AJR-' . $absensiSantriId);
    }
}
