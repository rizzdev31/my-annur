<?php

namespace App\Jobs;

use App\Models\WaOutbox;
use App\Services\FonnteClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kirim 1 baris wa_outbox via Fonnte. Retry untuk gangguan sementara
 * (jaringan/5xx). status:false dari Fonnte (nomor invalid/kuota) = gagal permanen.
 */
class KirimWaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $waOutboxId) {}

    public function handle(FonnteClient $client): void
    {
        $row = WaOutbox::find($this->waOutboxId);
        if (!$row || in_array($row->status, ['sent', 'skipped'], true)) return;
        if (!$row->tujuan) { $row->update(['status' => 'skipped', 'error' => 'nomor kosong']); return; }

        $row->increment('attempts');

        $resp = $client->kirim($row->tujuan, $row->pesan, $row->media_url);
        $body = $resp->json() ?? [];

        // SUKSES: HTTP 2xx & status true dari Fonnte.
        if ($resp->successful() && ($body['status'] ?? false) === true) {
            $row->update([
                'status'   => 'sent',
                'provider_response' => $body,
                'sent_at'  => now(),
                'error'    => null,
            ]);
            return;
        }

        // GAGAL PERMANEN: HTTP 2xx tapi status false (nomor invalid / kuota habis / device off).
        if ($resp->successful()) {
            $row->update([
                'status'   => 'failed',
                'provider_response' => $body,
                'error'    => $body['reason'] ?? 'fonnte status false',
            ]);
            return;
        }

        // GAGAL SEMENTARA (5xx / jaringan): throw → retry sesuai backoff.
        $row->update(['provider_response' => $body, 'error' => 'HTTP ' . $resp->status()]);
        throw new \RuntimeException('Fonnte gagal sementara: HTTP ' . $resp->status());
    }
}
