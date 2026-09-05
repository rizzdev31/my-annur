<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Masukan;
use App\Services\MasukanService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Saran & Masukan — sisi pengelola (superadmin). */
class MasukanController extends Controller
{
    public function __construct(private MasukanService $svc) {}

    public function index(Request $request)
    {
        $q = Masukan::with(['user:id,name', 'pesanTerakhir'])
            ->when($request->filled('status') && $request->status !== 'semua',
                fn($x) => $x->where('status', $request->status))
            ->when($request->filled('kategori') && $request->kategori !== 'semua',
                fn($x) => $x->where('kategori', $request->kategori))
            ->when($request->filled('cari'), function ($x) use ($request) {
                $cari = '%' . $request->cari . '%';
                $x->where(fn($w) => $w->where('judul', 'like', $cari)
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', $cari)));
            })
            // Yang belum tuntas naik ke atas, lalu percakapan terbaru.
            ->orderByRaw("FIELD(status,'baru','diproses','selesai','ditolak')")
            ->orderByDesc('pesan_terakhir_pada')->orderByDesc('id');

        $masukan = $q->paginate(20)->withQueryString()->through(fn($m) => [
            'id'             => $m->id,
            'judul'          => $m->judul,
            'pelapor'        => $m->user?->name ?? '—',
            'kategori'       => $m->kategori,
            'kategori_label' => Masukan::LABEL_KATEGORI[$m->kategori] ?? $m->kategori,
            'status'         => $m->status,
            'status_label'   => Masukan::LABEL_STATUS[$m->status] ?? $m->status,
            'prioritas'      => $m->prioritas,
            'modul'          => $m->modul,
            'cuplikan'       => \Illuminate\Support\Str::limit((string) $m->pesanTerakhir?->isi, 80),
            'belum_dibaca'   => (bool) $m->belum_dibaca_admin,
            'waktu'          => $m->pesan_terakhir_pada?->diffForHumans(),
        ]);

        return Inertia::render('Admin/Masukan/Index', [
            'masukan'   => $masukan,
            'filter'    => $request->only('status', 'kategori', 'cari'),
            'opsi'      => [
                'status'   => Masukan::LABEL_STATUS,
                'kategori' => Masukan::LABEL_KATEGORI,
            ],
            'ringkasan' => [
                'baru'     => Masukan::where('status', 'baru')->count(),
                'diproses' => Masukan::where('status', 'diproses')->count(),
                'bug'      => Masukan::where('kategori', 'bug')->whereNotIn('status', ['selesai', 'ditolak'])->count(),
            ],
        ]);
    }

    public function show(Masukan $masukan)
    {
        $this->svc->tandaiDibaca($masukan, sisiAdmin: true);

        return Inertia::render('Admin/Masukan/Show', [
            'masukan' => [
                'id'             => $masukan->id,
                'judul'          => $masukan->judul,
                'pelapor'        => $masukan->user?->name ?? '—',
                'kategori'       => $masukan->kategori,
                'kategori_label' => Masukan::LABEL_KATEGORI[$masukan->kategori] ?? $masukan->kategori,
                'modul'          => $masukan->modul,
                'status'         => $masukan->status,
                'prioritas'      => $masukan->prioritas,
                'catatan_admin'  => $masukan->catatan_admin,
                'ditangani_oleh' => $masukan->ditanganiOleh?->name,
                'dibuat'         => $masukan->created_at?->translatedFormat('d M Y H:i'),
                'ditutup'        => in_array($masukan->status, ['selesai', 'ditolak'], true),
                'pesan'          => $masukan->pesan()->with('user:id,name')->get()->map(fn($p) => [
                    'id'       => $p->id,
                    'tipe'     => $p->pengirim_tipe,
                    'nama'     => $p->pengirim_tipe === 'bot' ? 'Asisten' : ($p->user?->name ?? '—'),
                    'isi'      => $p->isi,
                    'lampiran' => $p->lampiranUrl(),
                    'sistem'   => (bool) ($p->meta['sistem'] ?? false),
                    'waktu'    => $p->created_at?->translatedFormat('d M Y H:i'),
                ])->values(),
            ],
            'opsi' => ['status' => Masukan::LABEL_STATUS],
        ]);
    }

    public function balas(Request $request, Masukan $masukan)
    {
        $request->validate([
            'isi'    => 'required|string|max:2000',
            'foto'   => 'nullable|array|max:' . MasukanService::MAKS_LAMPIRAN,
            'foto.*' => 'image|mimes:jpeg,jpg,png|max:3072',
        ]);

        $this->svc->balasAdmin($masukan, $request->user(), $request->isi, $request->file('foto', []));

        return back()->with('success', 'Balasan terkirim.');
    }

    public function status(Request $request, Masukan $masukan)
    {
        $request->validate([
            'status'  => 'required|in:' . implode(',', Masukan::STATUS),
            'catatan' => 'nullable|string|max:1000',
        ]);

        $this->svc->ubahStatus($masukan, $request->user(), $request->status, $request->catatan);

        return back()->with('success', 'Status masukan diperbarui.');
    }

    public function destroy(Masukan $masukan)
    {
        $masukan->delete();   // pesan ikut terhapus (cascade)

        return redirect()->route('admin.masukan.index')->with('success', 'Masukan dihapus.');
    }
}
