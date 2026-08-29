<?php

namespace App\Http\Controllers\Api\SmartHabbit;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\KodeVariabelCache;
use App\Models\OutboxLaporan;
use App\Services\OutboxService;
use App\Jobs\KirimLaporanJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Smart Eksekusi (sisi pengirim) — tendik melaporkan santri (pelanggaran/apresiasi/konselor).
 * Backend membungkus payload sesuai kontrak RamahAnak (PRD-04/05) lalu kirim via outbox.
 * NISN = santri.nip (keputusan PRD-04 #1: "NISN = nilai NIP pengirim").
 */
class EksekusiApiController extends Controller
{
    /** GET /smart-habbit/kode/{jenis} — daftar kode variabel (dari cache sinkron RamahAnak). */
    public function kode(string $jenis): JsonResponse
    {
        if (!in_array($jenis, ['pelanggaran', 'apresiasi', 'konselor'], true)) {
            return response()->json(['success' => false, 'message' => 'Jenis tidak valid.'], 422);
        }
        $data = KodeVariabelCache::jenis($jenis)->orderBy('kode')
            ->get(['kode', 'kategori', 'poin', 'label']);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** GET /smart-habbit/santri?q= — cari santri (untuk memilih pelaku/korban). */
    public function santri(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);
        $data = Santri::aktif()
            ->when($q !== '', fn($x) => $x->where(fn($w) =>
                $w->where('nama_lengkap', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%")))
            ->orderBy('nama_lengkap')->limit(30)
            ->get(['id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /smart-habbit/eksekusi — buat laporan & kirim ke RamahAnak (via outbox).
     * Body: jenis, kode, tanggal, catatan?, pelaku_santri_id?, korban_santri_id?
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis'            => 'required|in:pelanggaran,apresiasi,konselor',
            'kode'             => 'required|string|max:20',
            'tanggal'          => 'required|date',
            'catatan'          => 'nullable|string|max:1000',
            'pelaku_santri_id' => 'nullable|integer|exists:santri,id',
            'korban_santri_id' => 'nullable|integer|exists:santri,id',
        ]);

        $tp   = $request->user()->tenagaPendidik;
        $nama = $request->user()->name ?? '—';
        $actor = $tp ? "{$nama} (NIP {$tp->nip})" : $nama;

        // Resolve NISN (= nip) per jenis.
        $nisn = fn($id) => $id ? Santri::find($id)?->nip : null;

        // Validasi subjek sesuai jenis (lihat PRD-05 §2).
        $jenis = $data['jenis'];
        $nisnPelaku = $nisn($data['pelaku_santri_id'] ?? null);
        $nisnKorban = $nisn($data['korban_santri_id'] ?? null);

        if (in_array($jenis, ['pelanggaran', 'apresiasi'], true) && !$nisnPelaku) {
            return response()->json(['success' => false, 'message' => 'Santri pelaku wajib dipilih.'], 422);
        }
        if ($jenis === 'konselor' && !$nisnKorban) {
            return response()->json(['success' => false, 'message' => 'Santri (korban/terlapor) wajib dipilih.'], 422);
        }

        $payload = match ($jenis) {
            'pelanggaran' => array_filter([
                'nisn_pelaku' => $nisnPelaku,
                'nisn_korban' => $nisnKorban, // opsional
                'kode'        => $data['kode'],
                'tanggal'     => $data['tanggal'],
                'catatan'     => $data['catatan'] ?? null,
            ], fn($v) => $v !== null),
            'apresiasi'   => [
                'nisn_pelaku' => $nisnPelaku,
                'kode'        => $data['kode'],
                'tanggal'     => $data['tanggal'],
                'catatan'     => $data['catatan'] ?? null,
            ],
            'konselor'    => [
                'nisn_korban' => $nisnKorban,
                'kode'        => $data['kode'],
                'tanggal'     => $data['tanggal'],
                'catatan'     => $data['catatan'] ?? null,
            ],
        };

        $row = app(OutboxService::class)->enqueue($jenis, $payload, null, $actor);

        // Notifikasi WA wali santri — realtime saat guru menginput laporan.
        $targetId = $jenis === 'konselor'
            ? ($data['korban_santri_id'] ?? null)
            : ($data['pelaku_santri_id'] ?? null);
        $target = $targetId ? Santri::find($targetId) : null;
        if ($target) {
            $kv    = \App\Models\KodeVariabelCache::where('jenis', $jenis)->where('kode', $data['kode'])->first();
            app(\App\Services\WaService::class)->enqueue(
                $jenis, $target,
                $this->pesanEksekusi($jenis, $target, $kv?->label ?? $data['kode'], $kv?->poin, $data['tanggal'], $data['catatan'] ?? null),
                'WA-EKS-' . $row->id
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan dikirim ke RamahAnak (menunggu keputusan Guru BK).',
            'data'    => ['outbox_id' => $row->id, 'status' => $row->status, 'ref_id' => $row->payload['ref_id'] ?? null],
        ], 201);
    }

    /** Susun pesan WA untuk wali santri sesuai jenis laporan (template terpusat). */
    private function pesanEksekusi(string $jenis, $santri, string $label, $poin, string $tanggal, ?string $catatan): string
    {
        return \App\Services\WaTemplate::eksekusi($jenis, $santri->nama_lengkap, $label, $poin, $tanggal, $catatan);
    }

    /** GET /smart-habbit/outbox — riwayat kiriman (laporan milik tendik ini). */
    public function outbox(Request $request): JsonResponse
    {
        $tp   = $request->user()->tenagaPendidik;
        $nama = $request->user()->name ?? '';
        $list = OutboxLaporan::where('jenis', '!=', 'telat')
            ->where('actor', 'like', "%{$nama}%")
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn($o) => [
                'id'      => $o->id,
                'jenis'   => $o->jenis,
                'status'  => $o->status,           // pending | sent | duplicate | failed
                'ref_id'  => $o->ref_id,
                'error'   => $o->error,
                'tanggal' => $o->payload['tanggal'] ?? null,
                'kode'    => $o->payload['kode'] ?? null,
                'ramahanak_laporan_id' => $o->ramahanak_laporan_id,
                'sent_at' => $o->sent_at?->toDateTimeString(),
            ]);
        return response()->json(['success' => true, 'data' => $list]);
    }

    /** POST /smart-habbit/outbox/{outbox}/retry — kirim ulang yang gagal. */
    public function retry(OutboxLaporan $outbox): JsonResponse
    {
        if (in_array($outbox->status, ['sent', 'duplicate'], true)) {
            return response()->json(['success' => false, 'message' => 'Laporan sudah terkirim.'], 422);
        }
        $outbox->update(['status' => 'pending', 'error' => null]);
        if (config('ramahanak.enabled')) KirimLaporanJob::dispatch($outbox->id);
        return response()->json(['success' => true, 'message' => 'Dikirim ulang.']);
    }
}
