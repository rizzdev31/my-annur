<?php

namespace App\Services;

use App\Models\OutboxLaporan;
use App\Jobs\KirimLaporanJob;
use Illuminate\Support\Str;

/**
 * Memasukkan laporan ke outbox + dispatch job pengiriman ke RamahAnak.
 * Idempotent via ref_id (firstOrCreate) — aman dari dobel kirim.
 */
class OutboxService
{
    /**
     * @param string $jenis pelanggaran|apresiasi|konselor|telat
     * @param array  $payload body sesuai kontrak RamahAnak (tanpa ref_id/app — ditambah di sini)
     * @param string|null $refId kunci idempotency stabil (auto bila null)
     * @param string|null $actor jejak: siapa tendik pengirim
     */
    public function enqueue(string $jenis, array $payload, ?string $refId = null, ?string $actor = null): OutboxLaporan
    {
        $refId ??= strtoupper($jenis) . '-' . Str::uuid();
        $payload['ref_id'] = $refId;
        $payload['app']    = config('ramahanak.app');
        if ($actor) $payload['actor'] = $actor;

        $row = OutboxLaporan::firstOrCreate(
            ['ref_id' => $refId],
            ['jenis' => $jenis, 'payload' => $payload, 'status' => 'pending', 'actor' => $actor]
        );

        if ($row->wasRecentlyCreated && config('ramahanak.enabled')) {
            KirimLaporanJob::dispatch($row->id);
        }

        return $row;
    }
}
