<?php

namespace App\Jobs;

use App\Models\OutboxLaporan;
use App\Services\RamahAnakClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kirim 1 baris outbox ke RamahAnak. Retry untuk error sementara; 404/422 = gagal permanen.
 * 200 duplicate diperlakukan SUKSES (idempotent).
 */
class KirimLaporanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $outboxId) {}

    public function handle(RamahAnakClient $client): void
    {
        $row = OutboxLaporan::find($this->outboxId);
        if (!$row || in_array($row->status, ['sent', 'duplicate'], true)) return;

        $row->increment('attempts');

        $resp = match ($row->jenis) {
            'pelanggaran' => $client->kirimPelanggaran($row->payload),
            'apresiasi'   => $client->kirimApresiasi($row->payload),
            'konselor'    => $client->kirimKonselor($row->payload),
            'telat'       => $client->kirimTelat($row->payload),
            'absensi'     => $client->kirimPelanggaran($row->payload), // jawaban absensi → pelanggaran (kode dari config)
            'santri_sync' => $client->syncSantri($row->payload),      // upsert identitas & kelas santri by NISN
            'guru_sync'   => $client->syncGuru($row->payload),        // upsert identitas guru by NIP
            default       => null,
        };
        if (!$resp) {
            $row->update(['status' => 'failed', 'error' => 'jenis tidak dikenal']);
            return;
        }

        $body   = $resp->json() ?? [];
        $status = $body['status'] ?? null;

        // SUKSES: 201 ok ATAU 200 duplicate
        if ($resp->successful() && in_array($status, ['ok', 'duplicate'], true)) {
            $row->update([
                'status'   => $status === 'duplicate' ? 'duplicate' : 'sent',
                'response' => $body,
                'ramahanak_laporan_id' => $body['laporan_pelanggaran_id']
                    ?? $body['laporan_apresiasi_id'] ?? $body['laporan_konselor_id'] ?? $body['santri_id'] ?? $body['guru_id'] ?? null,
                'sent_at'  => now(),
                'error'    => null,
            ]);
            return;
        }

        // GAGAL PERMANEN (jangan retry): 404 / 422
        if (in_array($resp->status(), [404, 422], true)) {
            $row->update([
                'status'   => 'failed',
                'response' => $body,
                'error'    => $body['message'] ?? ($body['status'] ?? 'client error'),
            ]);
            return; // tidak throw → tidak retry
        }

        // GAGAL SEMENTARA (401/429/500/jaringan): throw → retry sesuai backoff
        $row->update(['response' => $body, 'error' => 'HTTP ' . $resp->status()]);
        throw new \RuntimeException('RamahAnak API gagal sementara: HTTP ' . $resp->status());
    }
}
