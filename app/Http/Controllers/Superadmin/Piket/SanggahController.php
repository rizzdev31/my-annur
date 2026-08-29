<?php

namespace App\Http\Controllers\Superadmin\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketPenilaian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Peninjauan sanggahan penilaian piket oleh admin (terima/tolak).
 * 'diterima' → penilaian dibatalkan & dikecualikan dari skor kinerja.
 */
class SanggahController extends Controller
{
    public function index()
    {
        $rows = PiketPenilaian::with([
                'kategori:id,nama',
                'guruDinilai.user:id,name',
                'jadwal:id,tanggal,tenaga_pendidik_id',
                'jadwal.tenagaPendidik.user:id,name',
            ])
            ->where('status_sanggah', '!=', '-')
            ->orderByRaw("FIELD(status_sanggah,'diajukan','ditolak','diterima')")
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'guru_dinilai'   => $p->guruDinilai?->user?->name ?? '—',
                'piket'          => $p->jadwal?->tenagaPendidik?->user?->name ?? '—',
                'tanggal'        => optional($p->jadwal?->tanggal)->toDateString(),
                'kategori'       => $p->kategori?->nama ?? '—',
                'jenis'          => $p->jenis,
                'dimensi'        => $p->dimensi,
                'poin'           => $p->poin_bertanda,
                'catatan'        => $p->catatan,
                'status_sanggah' => $p->status_sanggah,
                'alasan_sanggah' => $p->alasan_sanggah,
                'catatan_tinjauan' => $p->catatan_tinjauan,
            ]);

        return Inertia::render('Admin/Piket/Sanggah/Index', [
            'rows'      => $rows,
            'ringkasan' => [
                'diajukan' => PiketPenilaian::where('status_sanggah', 'diajukan')->count(),
                'diterima' => PiketPenilaian::where('status_sanggah', 'diterima')->count(),
                'ditolak'  => PiketPenilaian::where('status_sanggah', 'ditolak')->count(),
            ],
        ]);
    }

    public function tinjau(Request $request, PiketPenilaian $penilaian)
    {
        $d = $request->validate([
            'keputusan' => 'required|in:diterima,ditolak',
            'catatan'   => 'nullable|string|max:1000',
        ]);

        if ($penilaian->status_sanggah !== 'diajukan') {
            return back()->with('error', 'Sanggahan ini sudah ditinjau.');
        }

        $penilaian->update([
            'status_sanggah'   => $d['keputusan'],
            'ditinjau_oleh'    => Auth::id(),
            'ditinjau_pada'    => now(),
            'catatan_tinjauan' => $d['catatan'] ?? null,
        ]);

        $msg = $d['keputusan'] === 'diterima'
            ? 'Sanggahan diterima — penilaian dibatalkan & tidak dihitung di kinerja.'
            : 'Sanggahan ditolak — penilaian tetap berlaku.';
        return back()->with('success', $msg);
    }
}
