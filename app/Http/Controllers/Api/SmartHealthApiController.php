<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\SmartHealthLaporan;
use App\Services\SmartHealthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Smart Health (Flutter).
 *
 * LAPOR terbuka untuk SEMUA guru — setiap guru bisa melaporkan santri sakit
 * dan MEMANTAU real-time perkembangan laporannya (timeline riwayat) sampai
 * kondisi final (sembuh / izin pulang / darurat).
 *
 * EKSEKUSI keputusan (validasi, pengecekan Hari 1–3, darurat) hanya untuk
 * guru yang ditunjuk sebagai petugas Bagian Kesehatan.
 */
class SmartHealthApiController extends Controller
{
    /** Guru yang sedang login (atau null bila akun tak terhubung data guru). */
    private function guru(Request $request)
    {
        return $request->user()->tenagaPendidik;
    }

    /** Guru login sebagai petugas Bagian Kesehatan (atau null). */
    private function petugas(Request $request)
    {
        $tp = $this->guru($request);
        return ($tp && $tp->isPetugas('kesehatan')) ? $tp : null;
    }

    private function tolakGuru(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Akun Anda belum terhubung data guru.'], 403);
    }

    private function tolakPetugas(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Hanya petugas Bagian Kesehatan yang bisa mengeksekusi keputusan ini.'], 403);
    }

    public function status(Request $request): JsonResponse
    {
        $tp = $this->guru($request);
        return response()->json(['success' => true, 'data' => [
            'is_petugas' => (bool) ($tp && $tp->isPetugas('kesehatan')),
        ]]);
    }

    /** Cari santri untuk form lapor — semua guru boleh. */
    public function santri(Request $request): JsonResponse
    {
        if (!$this->guru($request)) return $this->tolakGuru();
        $q = trim((string) $request->q);
        $data = Santri::aktif()
            ->when($q !== '', fn($x) => $x->where(fn($w) =>
                $w->where('nama_lengkap', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%")))
            ->orderBy('nama_lengkap')->limit(30)->get(['id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Daftar kasus.
     * - Petugas kesehatan  → semua kasus (untuk dieksekusi).
     * - Guru biasa         → hanya laporan yang ia buat (untuk dipantau).
     */
    public function daftar(Request $request): JsonResponse
    {
        $tp = $this->guru($request);
        if (!$tp) return $this->tolakGuru();
        $isPetugas = $tp->isPetugas('kesehatan');

        $list = SmartHealthLaporan::with([
                'santri:id,nama_lengkap',
                'pelapor.user:id,name',
                'disetujuiOleh.user:id,name',
                'pengecekan' => fn($x) => $x->orderBy('id')->with('oleh.user:id,name'),
            ])
            ->when(!$isPetugas, fn($x) => $x->where('pelapor_tenaga_pendidik_id', $tp->id))
            ->orderByRaw("FIELD(status,'menunggu','dalam_pengecekan','selesai','ditolak')")
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn($l) => $this->map($l));

        return response()->json(['success' => true, 'data' => $list]);
    }

    /** Lapor santri sakit (deskripsi + foto opsional) — semua guru boleh. */
    public function lapor(Request $request): JsonResponse
    {
        $tp = $this->guru($request);
        if (!$tp) return $this->tolakGuru();

        $d = $request->validate([
            'santri_id'          => 'required|exists:santri,id',
            'deskripsi_penyakit' => 'required|string|max:255',
            'foto'               => 'nullable|image|mimes:jpeg,jpg,png|max:3072',
        ]);
        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $d['foto'] = $request->file('foto')->store("health/{$tp->id}", 'public');
        }

        $l = app(SmartHealthService::class)->lapor($tp, $d);
        return response()->json([
            'success' => true, 'message' => 'Laporan terkirim, menunggu validasi Bagian Kesehatan.',
            'data' => ['id' => $l->id, 'status' => $l->status],
        ], 201);
    }

    public function setujui(Request $request, SmartHealthLaporan $laporan): JsonResponse
    {
        $tp = $this->petugas($request);
        if (!$tp) return $this->tolakPetugas();
        try { app(SmartHealthService::class)->setujui($laporan, $tp); }
        catch (\DomainException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
        return response()->json(['success' => true, 'message' => 'Laporan disetujui — wali diberi tahu.']);
    }

    public function tolak(Request $request, SmartHealthLaporan $laporan): JsonResponse
    {
        $tp = $this->petugas($request);
        if (!$tp) return $this->tolakPetugas();
        try { app(SmartHealthService::class)->tolak($laporan); }
        catch (\DomainException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
        return response()->json(['success' => true, 'message' => 'Laporan ditolak & dihapus.']);
    }

    /** Catat pemantauan: keputusan = sembuh | pengecekan | darurat. */
    public function pengecekan(Request $request, SmartHealthLaporan $laporan): JsonResponse
    {
        $tp = $this->petugas($request);
        if (!$tp) return $this->tolakPetugas();
        $d = $request->validate([
            'keputusan' => 'required|in:sembuh,pengecekan,darurat',
            'catatan'   => 'nullable|string|max:255',
        ]);
        try {
            $p = app(SmartHealthService::class)->catatPengecekan($laporan, $tp, $d['keputusan'], $d['catatan'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Keputusan tercatat & wali diberi tahu.',
            'data' => ['status' => $laporan->fresh()->status, 'hari_ke' => $p->hari_ke]]);
    }

    // ── Mapping ─────────────────────────────────────────────────────────────────

    private function map(SmartHealthLaporan $l): array
    {
        $peng = $l->pengecekan->where('keputusan', 'pengecekan')->count();

        return [
            'id'              => $l->id,
            'santri'          => $l->santri?->nama_lengkap ?? '—',
            'penyakit'        => $l->deskripsi_penyakit,
            'foto'            => $l->fotoUrl(),
            'status'          => $l->status,
            'kondisi'         => $l->kondisi_akhir,
            'hari_pengecekan' => $peng,        // sudah berapa hari dipantau
            'pelapor'         => $l->pelapor?->user?->name ?? '—',
            'riwayat'         => $this->riwayat($l),
            'tanggal'         => $l->created_at?->format('d M Y H:i'),
        ];
    }

    /** Timeline real-time: lapor → validasi → tiap keputusan pemantauan. */
    private function riwayat(SmartHealthLaporan $l): array
    {
        $t = [];
        $t[] = [
            'tipe'    => 'lapor',
            'judul'   => 'Laporan dibuat',
            'oleh'    => $l->pelapor?->user?->name,
            'catatan' => $l->deskripsi_penyakit,
            'waktu'   => $l->created_at?->format('d M · H:i'),
        ];

        if ($l->disetujui_pada) {
            $t[] = [
                'tipe'    => 'validasi',
                'judul'   => 'Divalidasi Bagian Kesehatan',
                'oleh'    => $l->disetujuiOleh?->user?->name,
                'catatan' => null,
                'waktu'   => $l->disetujui_pada->format('d M · H:i'),
            ];
        }

        foreach ($l->pengecekan->sortBy('id') as $p) {
            $judul = match ($p->keputusan) {
                'sembuh'  => 'Dinyatakan SEMBUH',
                'darurat' => 'Kondisi DARURAT — dipulangkan',
                default   => 'Pengecekan Hari ' . $p->hari_ke . ($p->hari_ke >= 3 ? ' — dipulangkan' : ''),
            };
            $t[] = [
                'tipe'    => $p->keputusan,
                'judul'   => $judul,
                'oleh'    => $p->oleh?->user?->name,
                'catatan' => $p->catatan,
                'waktu'   => optional($p->tanggal)->format('d M') ?? $p->created_at?->format('d M'),
            ];
        }

        return $t;
    }
}
