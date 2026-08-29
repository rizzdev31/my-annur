<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\WaOutbox;
use App\Jobs\KirimWaJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Monitor outbox WhatsApp (Fonnte): pantau terkirim/gagal/skip/pending + retry.
 */
class WaOutboxController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->status ?: 'all';

        $rows = WaOutbox::with('santri:id,nama_lengkap')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($o) => [
                'id'        => $o->id,
                'santri'    => $o->santri?->nama_lengkap ?? '—',
                'tujuan'    => $o->tujuan,
                'jenis'     => $o->jenis,
                'status'    => $o->status,
                'attempts'  => $o->attempts,
                'error'     => $o->error,
                'pesan'     => $o->pesan,
                'sent_at'   => $o->sent_at?->format('d M Y H:i'),
                'dibuat'    => $o->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/WaOutbox/Index', [
            'rows'         => $rows,
            'filterStatus' => $status,
            'ringkasan'    => [
                'total'   => WaOutbox::count(),
                'pending' => WaOutbox::where('status', 'pending')->count(),
                'sent'    => WaOutbox::where('status', 'sent')->count(),
                'failed'  => WaOutbox::where('status', 'failed')->count(),
                'skipped' => WaOutbox::where('status', 'skipped')->count(),
            ],
            'fonnte' => [
                'aktif'     => (bool) config('fonnte.enabled'),
                'token_ada' => (bool) config('fonnte.token'),
            ],
        ]);
    }

    /** Kirim ulang satu pesan gagal/pending. */
    public function retry(WaOutbox $waOutbox)
    {
        if ($waOutbox->status === 'sent') {
            return back()->with('error', 'Pesan ini sudah terkirim.');
        }
        if (!$waOutbox->tujuan) {
            return back()->with('error', 'Nomor tujuan kosong — tidak bisa dikirim.');
        }
        if (!config('fonnte.enabled')) {
            return back()->with('error', 'Fonnte belum aktif (FONNTE_ENABLED=false).');
        }
        $waOutbox->update(['status' => 'pending', 'error' => null]);
        KirimWaJob::dispatch($waOutbox->id);
        return back()->with('success', 'Pesan dikirim ulang (antre worker).');
    }

    /** Kirim ulang semua yang gagal. */
    public function retryGagal()
    {
        if (!config('fonnte.enabled')) {
            return back()->with('error', 'Fonnte belum aktif (FONNTE_ENABLED=false).');
        }
        $ids = WaOutbox::where('status', 'failed')->whereNotNull('tujuan')->pluck('id');
        foreach ($ids as $id) {
            WaOutbox::whereKey($id)->update(['status' => 'pending', 'error' => null]);
            KirimWaJob::dispatch($id);
        }
        return back()->with('success', "{$ids->count()} pesan gagal dikirim ulang.");
    }
}
