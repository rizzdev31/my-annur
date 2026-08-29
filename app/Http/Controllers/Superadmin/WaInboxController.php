<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\WaInbox;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Kotak masuk WhatsApp (balasan wali via webhook Fonnte).
 */
class WaInboxController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->filter ?: 'all';

        $rows = WaInbox::with('santri:id,nama_lengkap')
            ->when($filter === 'unread', fn($q) => $q->where('dibaca', false))
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($m) => [
                'id'       => $m->id,
                'nama'     => $m->nama,
                'pengirim' => $m->pengirim,
                'santri'   => $m->santri?->nama_lengkap,
                'pesan'    => $m->pesan,
                'media'    => $m->media_url,
                'dibaca'   => $m->dibaca,
                'waktu'    => ($m->diterima_pada ?? $m->created_at)?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/WaInbox/Index', [
            'rows'      => $rows,
            'filter'    => $filter,
            'ringkasan' => [
                'total'      => WaInbox::count(),
                'belum_baca' => WaInbox::where('dibaca', false)->count(),
            ],
            'webhook_url' => url('/api/v1/webhook/fonnte/incoming'),
            'secret_ada'  => (bool) config('fonnte.webhook_secret'),
        ]);
    }

    public function baca(WaInbox $waInbox)
    {
        $waInbox->update(['dibaca' => true]);
        return back();
    }

    public function bacaSemua()
    {
        WaInbox::where('dibaca', false)->update(['dibaca' => true]);
        return back()->with('success', 'Semua pesan ditandai dibaca.');
    }
}
