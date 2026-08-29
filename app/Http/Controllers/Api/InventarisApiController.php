<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventaris;
use App\Models\PeminjamanInventaris;
use App\Services\PeminjamanInventarisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API peminjaman inventaris untuk guru (Flutter).
 * Nama peminjam diturunkan dari user login (anti-spoof). Anti double-booking &
 * anti pengajuan kembar ditegakkan di PeminjamanInventarisService.
 */
class InventarisApiController extends Controller
{
    public function __construct(private PeminjamanInventarisService $svc) {}

    /** GET /inventaris — daftar inventaris aktif. */
    public function index(Request $request): JsonResponse
    {
        $list = Inventaris::aktif()
            ->when($request->kategori, fn($q) => $q->where('kategori', $request->kategori))
            ->when($request->q, fn($q) => $q->where('nama', 'like', "%{$request->q}%"))
            ->orderBy('kategori')->orderBy('nama')->get()
            ->map(fn($i) => [
                'id' => $i->id, 'kode' => $i->kode, 'nama' => $i->nama,
                'kategori' => $i->kategori, 'lokasi' => $i->lokasi,
                'jumlah_total' => $i->jumlah_total, 'satuan' => $i->satuan,
                'perlu_persetujuan' => $i->perlu_persetujuan,
            ]);
        return response()->json(['success' => true, 'data' => $list]);
    }

    /** GET /inventaris/{inventaris}/ketersediaan?tanggal=&jam_mulai=&jam_selesai= */
    public function ketersediaan(Request $request, Inventaris $inventaris): JsonResponse
    {
        $request->validate([
            'tanggal'     => 'required|date_format:Y-m-d',
            'jam_mulai'   => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
        ]);
        $sisa = $this->svc->sisaKapasitas(
            $inventaris->id, $request->tanggal, $request->jam_mulai, $request->jam_selesai
        );
        return response()->json(['success' => true, 'data' => [
            'sisa'    => $sisa,
            'tersedia'=> $sisa > 0,
            'total'   => $inventaris->jumlah_total,
        ]]);
    }

    /** GET /inventaris/peminjaman — riwayat peminjaman guru ini. */
    public function peminjamanSaya(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], 404);

        $list = PeminjamanInventaris::with('inventaris:id,nama,kategori')
            ->where('tenaga_pendidik_id', $tp->id)
            ->orderByDesc('tanggal')->orderByDesc('created_at')->limit(100)->get()
            ->map(fn($p) => [
                'id'        => $p->id,
                'inventaris'=> $p->inventaris?->nama ?? '—',
                'kategori'  => $p->inventaris?->kategori ?? '-',
                'keperluan' => $p->keperluan,
                'tanggal'   => $p->tanggal?->toDateString(),
                'jam_mulai' => substr((string) $p->jam_mulai, 0, 5),
                'jam_selesai'=> substr((string) $p->jam_selesai, 0, 5),
                'status'    => $p->status,
                'catatan_admin' => $p->catatan_admin,
            ]);
        return response()->json(['success' => true, 'data' => $list]);
    }

    /** POST /inventaris/peminjaman — ajukan pemakaian. */
    public function ajukan(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], 404);

        $data = $request->validate([
            'inventaris_id' => 'required|exists:inventaris,id',
            'jumlah'        => 'nullable|integer|min:1',
            'keperluan'     => 'required|string|max:255',
            'tanggal'       => 'required|date_format:Y-m-d',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i',
        ]);

        try {
            $p = $this->svc->ajukan($tp->id, $data);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $p->status === 'disetujui'
                ? 'Peminjaman langsung disetujui.'
                : 'Pengajuan terkirim, menunggu persetujuan admin.',
            'data' => ['id' => $p->id, 'status' => $p->status],
        ], 201);
    }

    /** POST /inventaris/peminjaman/{peminjaman}/batal — batalkan milik sendiri. */
    public function batal(Request $request, PeminjamanInventaris $peminjaman): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp || $peminjaman->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Bukan peminjaman Anda.'], 403);
        }
        try {
            $this->svc->batal($peminjaman->id);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Peminjaman dibatalkan.']);
    }
}
