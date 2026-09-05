<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Masukan;
use App\Services\MasukanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saran & Masukan — sisi pengguna (PWA guru).
 *
 * Pengguna HANYA boleh menyentuh utas miliknya sendiri; pemilik dicek ulang
 * di server pada setiap aksi (id dari klien tidak dipercaya).
 */
class MasukanApiController extends Controller
{
    public function __construct(private MasukanService $svc) {}

    /** GET /masukan — daftar utas milik saya. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $list = Masukan::where('user_id', $user->id)
            ->with('pesanTerakhir')
            ->orderByDesc('pesan_terakhir_pada')->orderByDesc('id')
            ->limit(100)->get()
            ->map(fn($m) => [
                'id'            => $m->id,
                'judul'         => $m->judul,
                'kategori'      => $m->kategori,
                'kategori_label'=> Masukan::LABEL_KATEGORI[$m->kategori] ?? $m->kategori,
                'status'        => $m->status,
                'status_label'  => Masukan::LABEL_STATUS[$m->status] ?? $m->status,
                'cuplikan'      => \Illuminate\Support\Str::limit((string) $m->pesanTerakhir?->isi, 70),
                'belum_dibaca'  => (bool) $m->belum_dibaca_user,
                'waktu'         => $m->pesan_terakhir_pada?->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'data' => [
            'masukan'      => $list,
            'belum_dibaca' => $list->where('belum_dibaca', true)->count(),
            'kategori'     => Masukan::LABEL_KATEGORI,
        ]]);
    }

    /** POST /masukan — buat utas baru (boleh sertakan foto bug). */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'kategori' => 'required|in:' . implode(',', Masukan::KATEGORI),
            'judul'    => 'required|string|max:150',
            'isi'      => 'required|string|max:2000',
            'modul'    => 'nullable|string|max:60',
            'foto'     => 'nullable|array|max:' . MasukanService::MAKS_LAMPIRAN,
            'foto.*'   => 'image|mimes:jpeg,jpg,png|max:3072',
        ]);

        $m = $this->svc->buat(
            $request->user(), $request->kategori, $request->judul,
            $request->isi, $request->modul, $request->file('foto', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Masukan terkirim. Terima kasih — admin akan menindaklanjuti.',
            'data'    => ['id' => $m->id],
        ]);
    }

    /** GET /masukan/{masukan} — isi percakapan satu utas. */
    public function show(Request $request, Masukan $masukan): JsonResponse
    {
        if ((int) $masukan->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Masukan ini bukan milik Anda.'], 403);
        }

        $this->svc->tandaiDibaca($masukan, sisiAdmin: false);

        return response()->json(['success' => true, 'data' => $this->format($masukan)]);
    }

    /** POST /masukan/{masukan}/balas — pelapor menambah pesan. */
    public function balas(Request $request, Masukan $masukan): JsonResponse
    {
        if ((int) $masukan->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Masukan ini bukan milik Anda.'], 403);
        }
        if (in_array($masukan->status, ['selesai', 'ditolak'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Masukan ini sudah ditutup. Silakan buat masukan baru bila masih ada kendala.',
            ], 422);
        }

        $request->validate([
            'isi'    => 'required|string|max:2000',
            'foto'   => 'nullable|array|max:' . MasukanService::MAKS_LAMPIRAN,
            'foto.*' => 'image|mimes:jpeg,jpg,png|max:3072',
        ]);

        $this->svc->balasPelapor($masukan, $request->user(), $request->isi, $request->file('foto', []));

        return response()->json([
            'success' => true,
            'message' => 'Pesan terkirim.',
            'data'    => $this->format($masukan->fresh()),
        ]);
    }

    private function format(Masukan $m): array
    {
        return [
            'id'             => $m->id,
            'judul'          => $m->judul,
            'kategori'       => $m->kategori,
            'kategori_label' => Masukan::LABEL_KATEGORI[$m->kategori] ?? $m->kategori,
            'modul'          => $m->modul,
            'status'         => $m->status,
            'status_label'   => Masukan::LABEL_STATUS[$m->status] ?? $m->status,
            'ditutup'        => in_array($m->status, ['selesai', 'ditolak'], true),
            'catatan_admin'  => $m->catatan_admin,
            'dibuat'         => $m->created_at?->translatedFormat('d M Y H:i'),
            'pesan'          => $m->pesan()->with('user:id,name')->get()->map(fn($p) => [
                'id'       => $p->id,
                'tipe'     => $p->pengirim_tipe,       // guru | admin | bot
                'nama'     => $p->pengirim_tipe === 'bot' ? 'Asisten' : ($p->user?->name ?? 'Admin'),
                'isi'      => $p->isi,
                'lampiran' => $p->lampiranUrl(),
                'sistem'   => (bool) ($p->meta['sistem'] ?? false),
                'waktu'    => $p->created_at?->translatedFormat('d M Y H:i'),
            ])->values(),
        ];
    }
}
