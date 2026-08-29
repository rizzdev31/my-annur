<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\PiketKategori;
use App\Models\PiketPenilaian;
use App\Services\PiketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Guru Piket (sisi guru yang ditugaskan). Input penilaian kinerja keliling.
 * Semua aksi tunduk pada window jam kerja piket (lihat PiketService).
 */
class PiketApiController extends Controller
{
    /** GET /piket/status — apakah user piket hari ini + kondisi window. */
    public function status(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => app(PiketService::class)->status($request->user())]);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
    }

    /** GET /piket/kategori — rubrik kategori aktif. */
    public function kategori(): JsonResponse
    {
        $data = PiketKategori::where('is_aktif', true)
            ->orderBy('jenis')->orderBy('dimensi')->orderBy('nama')
            ->get(['id', 'nama', 'jenis', 'dimensi', 'poin']);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** GET /piket/guru?q= — daftar guru yang dapat dinilai (selain diri sendiri) + status hadir hari ini. */
    public function guru(Request $request): JsonResponse
    {
        $tpId = $request->user()->tenagaPendidik?->id;
        $q    = trim((string) $request->q);

        // Status kehadiran hari ini agar piket tak salah menilai guru yang izin/libur.
        $today = \App\Services\TimezoneHelper::today()->toDateString();
        $statusMap = \App\Models\AbsensiHarian::whereDate('tanggal', $today)->pluck('status', 'tenaga_pendidik_id');

        $data = TenagaPendidik::aktif()->with('user:id,name')
            ->when($tpId, fn($x) => $x->where('id', '!=', $tpId))
            ->get()
            ->map(fn($g) => [
                'id'           => $g->id,
                'nama'         => $g->user?->name ?? '—',
                'nip'          => $g->nip,
                'status_absen' => $statusMap[$g->id] ?? 'belum_absen',
            ])
            ->filter(fn($g) => $g['nama'] !== '—' && ($q === '' || stripos($g['nama'], $q) !== false))
            ->sortBy('nama')->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /piket/penilaian — simpan penilaian. */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'guru_dinilai_id'    => 'required|integer|exists:tenaga_pendidik,id',
            'kategori_id'        => 'required|integer|exists:piket_kategori,id',
            'catatan'            => 'nullable|string|max:1000',
            'jadwal_mengajar_id' => 'nullable|integer',
            'bukti_foto'         => 'nullable|image|mimes:jpeg,jpg,png|max:3072',
        ]);

        // Simpan bukti foto (opsional) ke storage publik.
        if ($request->hasFile('bukti_foto') && $request->file('bukti_foto')->isValid()) {
            $tpId = $request->user()->tenagaPendidik?->id ?? 'x';
            $d['bukti_foto'] = $request->file('bukti_foto')->store("bukti-piket/{$tpId}", 'public');
        }

        try {
            $p = app(PiketService::class)->simpanPenilaian($request->user(), $d);
        } catch (\DomainException $e) {
            return $this->err($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penilaian tersimpan.',
            'data'    => ['id' => $p->id, 'jenis' => $p->jenis, 'poin' => $p->poin_bertanda],
        ], 201);
    }

    /** GET /piket/penilaian — status + riwayat penilaian pada penugasan hari ini. */
    public function riwayat(Request $request): JsonResponse
    {
        $st = app(PiketService::class)->status($request->user());

        $riwayat = $st['jadwal_id']
            ? PiketPenilaian::with(['kategori:id,nama', 'guruDinilai.user:id,name'])
                ->where('piket_jadwal_id', $st['jadwal_id'])->orderByDesc('id')->get()
                ->map(fn($p) => [
                    'id'       => $p->id,
                    'guru'     => $p->guruDinilai?->user?->name ?? '—',
                    'kategori' => $p->kategori?->nama ?? '—',
                    'jenis'    => $p->jenis,
                    'dimensi'  => $p->dimensi,
                    'poin'     => $p->poin_bertanda,
                    'catatan'  => $p->catatan,
                    'bukti_foto' => $p->bukti_foto ? asset('storage/'.$p->bukti_foto) : null,
                    'waktu'    => $p->created_at?->format('H:i'),
                ])
            : collect();

        return response()->json(['success' => true, 'data' => ['status' => $st, 'riwayat' => $riwayat]]);
    }

    /** POST /piket/laporan-harian — isi catatan harian (mis. "semua aman"). */
    public function laporanHarian(Request $request): JsonResponse
    {
        $d = $request->validate(['catatan' => 'required|string|max:1000']);
        try {
            app(PiketService::class)->laporanHarian($request->user(), $d['catatan']);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
        return response()->json(['success' => true, 'message' => 'Laporan harian tersimpan.']);
    }

    /** GET /piket/sesi — jadwal hari ini yang gurunya belum konfirmasi (perlu diisi piket). */
    public function sesi(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => app(PiketService::class)->sesiPerluAbsen($request->user())]);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
    }

    /** GET /piket/roster/{jadwal} — daftar santri untuk diisi piket. */
    public function roster(Request $request, int $jadwal): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => app(PiketService::class)->rosterJadwal($request->user(), $jadwal)]);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
    }

    /** POST /piket/absen-kelas — piket mengisi absensi santri untuk sesi tak terkonfirmasi. */
    public function absenKelas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'jadwal_id'            => 'required|integer|exists:jadwal_mengajar,id',
            'absensi'             => 'required|array|min:1',
            'absensi.*.santri_id' => 'required|integer|exists:santri,id',
            'absensi.*.status'    => 'required|in:hadir,telat,alpha',
            'materi'              => 'nullable|string|max:500',
        ]);

        try {
            $am = app(PiketService::class)->absenKelas($request->user(), (int) $d['jadwal_id'], $d['absensi'], $d['materi'] ?? null);
        } catch (\DomainException $e) {
            return $this->err($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Absensi santri tersimpan oleh piket (sesi ditandai tidak terlaksana — vakasi guru tidak diberikan).',
            'data'    => ['absensi_mengajar_id' => $am->id],
        ], 201);
    }

    /** GET /piket/penilaian-saya — penilaian atas diri guru (untuk disanggah). */
    public function penilaianSaya(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => app(PiketService::class)->penilaianSaya($request->user())]);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
    }

    /** POST /piket/penilaian/{id}/sanggah — ajukan sanggah. */
    public function sanggah(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['alasan' => 'required|string|max:1000']);
        try {
            app(PiketService::class)->ajukanSanggah($request->user(), $id, $d['alasan']);
        } catch (\DomainException $e) {
            return $this->err($e);
        }
        return response()->json(['success' => true, 'message' => 'Sanggahan diajukan. Menunggu peninjauan admin.']);
    }

    private function err(\DomainException $e): JsonResponse
    {
        $code = in_array($e->getCode(), [403, 404, 422], true) ? $e->getCode() : 422;
        return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
    }
}
