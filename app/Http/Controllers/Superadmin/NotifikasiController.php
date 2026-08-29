<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi in-app untuk topbar admin (dropdown lonceng).
 * Dikonsumsi via fetch() dari AdminLayout — bukan halaman Inertia.
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifikasi()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($n) => [
                'id'           => $n->id,
                'judul'        => $n->judul,
                'pesan'        => $n->pesan,
                'tipe'         => $n->tipe,
                'sudah_dibaca' => (bool) $n->sudah_dibaca,
                'waktu'        => $n->created_at?->diffForHumans(),
                'link'         => $this->link($n->data),
            ]);

        return response()->json([
            'success' => true,
            'unread'  => $user->notifikasiBelumDibaca()->count(),
            'data'    => $items,
        ]);
    }

    /** Hitung notifikasi belum dibaca — dipakai polling badge lonceng. */
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'unread'  => $request->user()->notifikasiBelumDibaca()->count(),
        ]);
    }

    public function baca(Request $request, int $id): JsonResponse
    {
        $request->user()->notifikasi()
            ->whereKey($id)
            ->update(['sudah_dibaca' => true, 'dibaca_pada' => now()]);

        return response()->json(['success' => true]);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $request->user()->notifikasiBelumDibaca()
            ->update(['sudah_dibaca' => true, 'dibaca_pada' => now()]);

        return response()->json(['success' => true]);
    }

    /** Tautan tujuan saat notifikasi diklik, diturunkan dari payload data. */
    private function link($data): ?string
    {
        $type = is_array($data) ? ($data['type'] ?? null) : null;

        return match ($type) {
            'pengajuan_izin'    => route('admin.smart-payroll.pengajuan-izin.index'),
            'kegiatan', 'tugas' => route('admin.smart-payroll.absensi-kegiatan.index'),
            default             => null,
        };
    }
}
