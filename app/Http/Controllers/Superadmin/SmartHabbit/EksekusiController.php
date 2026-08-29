<?php

namespace App\Http\Controllers\Superadmin\SmartHabbit;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\KodeVariabelCache;
use App\Models\OutboxLaporan;
use App\Services\OutboxService;
use App\Jobs\KirimLaporanJob;
use App\Jobs\SyncVariabelJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Smart Eksekusi (admin web) + Monitor Outbox. Logika kirim sama dgn API Flutter:
 * bungkus payload sesuai kontrak RamahAnak (NISN=nip) → OutboxService::enqueue.
 */
class EksekusiController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SmartHabbit/Eksekusi/Index', [
            'kode' => [
                'pelanggaran' => KodeVariabelCache::jenis('pelanggaran')->orderBy('kode')->get(['kode', 'kategori', 'poin', 'label']),
                'apresiasi'   => KodeVariabelCache::jenis('apresiasi')->orderBy('kode')->get(['kode', 'kategori', 'poin', 'label']),
                'konselor'    => KodeVariabelCache::jenis('konselor')->orderBy('kode')->get(['kode', 'kategori', 'poin', 'label']),
            ],
            'santriOpsi' => Santri::where('is_aktif', true)->orderBy('nama_lengkap')
                ->get(['id', 'nip', 'nama_lengkap'])
                ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]),
            'enabled' => (bool) config('ramahanak.enabled'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'jenis'            => 'required|in:pelanggaran,apresiasi,konselor',
            'kode'             => 'required|string|max:20',
            'tanggal'          => 'required|date',
            'catatan'          => 'nullable|string|max:1000',
            'pelaku_santri_id' => 'nullable|integer|exists:santri,id',
            'korban_santri_id' => 'nullable|integer|exists:santri,id',
        ]);

        $nip = fn($id) => $id ? Santri::find($id)?->nip : null;
        $nisnPelaku = $nip($d['pelaku_santri_id'] ?? null);
        $nisnKorban = $nip($d['korban_santri_id'] ?? null);

        if (in_array($d['jenis'], ['pelanggaran', 'apresiasi'], true) && !$nisnPelaku) {
            return back()->with('error', 'Santri pelaku wajib dipilih.');
        }
        if ($d['jenis'] === 'konselor' && !$nisnKorban) {
            return back()->with('error', 'Santri (terlapor) wajib dipilih.');
        }

        $payload = match ($d['jenis']) {
            'pelanggaran' => array_filter([
                'nisn_pelaku' => $nisnPelaku, 'nisn_korban' => $nisnKorban,
                'kode' => $d['kode'], 'tanggal' => $d['tanggal'], 'catatan' => $d['catatan'] ?? null,
            ], fn($v) => $v !== null),
            'apresiasi'   => ['nisn_pelaku' => $nisnPelaku, 'kode' => $d['kode'], 'tanggal' => $d['tanggal'], 'catatan' => $d['catatan'] ?? null],
            'konselor'    => ['nisn_korban' => $nisnKorban, 'kode' => $d['kode'], 'tanggal' => $d['tanggal'], 'catatan' => $d['catatan'] ?? null],
        };

        app(OutboxService::class)->enqueue($d['jenis'], $payload, null, Auth::user()->name ?? 'admin');

        return back()->with('success', 'Laporan dikirim ke RamahAnak (menunggu keputusan Guru BK).');
    }

    // ── Monitor Outbox ──────────────────────────────────────────────────────
    public function outbox(Request $request)
    {
        $q = OutboxLaporan::query()->orderByDesc('id');
        if ($request->status) $q->where('status', $request->status);
        if ($request->jenis)  $q->where('jenis', $request->jenis);

        return Inertia::render('Admin/SmartHabbit/Outbox/Index', [
            'rows' => $q->limit(200)->get()->map(fn($o) => [
                'id' => $o->id, 'jenis' => $o->jenis, 'status' => $o->status,
                'ref_id' => $o->ref_id, 'attempts' => $o->attempts, 'error' => $o->error,
                'actor' => $o->actor, 'payload' => $o->payload,
                'ramahanak_laporan_id' => $o->ramahanak_laporan_id,
                'sent_at' => $o->sent_at?->toDateTimeString(),
                'created_at' => $o->created_at?->toDateTimeString(),
            ]),
            'filter'  => $request->only(['status', 'jenis']),
            'enabled' => (bool) config('ramahanak.enabled'),
            'ringkasan' => [
                'pending'   => OutboxLaporan::where('status', 'pending')->count(),
                'sent'      => OutboxLaporan::where('status', 'sent')->count(),
                'duplicate' => OutboxLaporan::where('status', 'duplicate')->count(),
                'failed'    => OutboxLaporan::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function retry(OutboxLaporan $outbox)
    {
        if (in_array($outbox->status, ['sent', 'duplicate'], true)) {
            return back()->with('error', 'Laporan sudah terkirim.');
        }
        $outbox->update(['status' => 'pending', 'error' => null]);
        if (config('ramahanak.enabled')) KirimLaporanJob::dispatch($outbox->id);
        return back()->with('success', 'Dikirim ulang.');
    }

    public function syncKode()
    {
        if (!config('ramahanak.enabled')) {
            return back()->with('error', 'Integrasi RamahAnak nonaktif (set RAMAHANAK_ENABLED=true & token).');
        }
        SyncVariabelJob::dispatchSync();
        return back()->with('success', 'Sinkron kode variabel selesai.');
    }
}
