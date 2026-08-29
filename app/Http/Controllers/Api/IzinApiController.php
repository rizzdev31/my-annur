<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanIzin;
use App\Models\SettingJenisPengajuan;
use App\Services\PengajuanIzinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IzinApiController extends Controller
{
    public function __construct(
        private readonly PengajuanIzinService $service
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // GET /izin/jenis — Daftar jenis pengajuan aktif + sisa kuota guru
    // ══════════════════════════════════════════════════════════════════════════

    public function jenisIzin(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $tahun     = now()->year;
        $jenisList = SettingJenisPengajuan::aktif()->orderBy('kategori')->orderBy('nama')->get();

        $data = $jenisList->map(fn($j) => [
            'id'                            => $j->id,
            'nama'                          => $j->nama,
            'kode'                          => $j->kode,
            'kategori'                      => $j->kategori,   // sakit|izin|cuti|dinas
            'deskripsi'                     => $j->deskripsi,
            'max_hari_per_pengajuan'        => $j->max_hari_per_pengajuan ?? 999,
            'kuota_per_tahun'               => $j->kuota_per_tahun,
            'sisa_kuota'                    => $j->kuota_per_tahun
                ? $this->service->getSisaKuota($tp, $j, $tahun) : null,
            'min_hari_pengajuan_sebelumnya' => $j->min_hari_pengajuan_sebelumnya ?? 0,
            'butuh_dokumen'                 => $j->butuh_dokumen ?? false,
            'keterangan_dokumen'            => $j->keterangan_dokumen,
            'auto_approve'                  => $j->auto_approve ?? false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data->values(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /izin — Riwayat pengajuan izin guru ini (tahun berjalan)
    // ══════════════════════════════════════════════════════════════════════════

    public function riwayat(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $tahun = (int) ($request->tahun ?? now()->year);

        $list = PengajuanIzin::with('jenisPengajuan')
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereYear('tanggal_mulai', $tahun)
            ->orderByDesc('created_at')
            ->get();

        // Statistik ringkas untuk header
        $stats = [
            'total'     => $list->count(),
            'pending'   => $list->where('status', 'pending')->count(),
            'disetujui' => $list->where('status', 'disetujui')->count(),
            'ditolak'   => $list->where('status', 'ditolak')->count(),
            'total_hari'=> (int) $list->where('status', 'disetujui')->sum('jumlah_hari'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'tahun'  => $tahun,
                'stats'  => $stats,
                'riwayat'=> $list->map(fn($p) => $this->formatPengajuan($p))->values(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /izin — Buat pengajuan baru
    // ══════════════════════════════════════════════════════════════════════════

    public function buat(Request $request): JsonResponse
    {
        $request->validate([
            'setting_jenis_pengajuan_id' => 'required|exists:setting_jenis_pengajuan,id',
            'tanggal_mulai'              => 'required|date|after_or_equal:today',
            'tanggal_selesai'            => 'required|date|after_or_equal:tanggal_mulai',
            'alasan'                     => 'required|string|min:10|max:500',
            'dokumen'                    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        try {
            $pengajuan = $this->service->buat(
                guru:        $tp,
                data:        $request->only([
                    'setting_jenis_pengajuan_id',
                    'tanggal_mulai',
                    'tanggal_selesai',
                    'alasan',
                ]),
                fileDokumen: $request->hasFile('dokumen') ? $request->file('dokumen') : null,
            );

            $message = $pengajuan->status === 'disetujui'
                ? 'Pengajuan disetujui otomatis! Absensi sudah diperbarui.'
                : 'Pengajuan berhasil dikirim. Menunggu persetujuan admin.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $this->formatPengajuan($pengajuan),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'VALIDATION_BISNIS',
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('[IZIN_API] buat error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan. Coba lagi nanti.',
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DELETE /izin/{id} — Batalkan pengajuan (hanya yang masih pending
    //                      atau disetujui tapi belum berjalan)
    // ══════════════════════════════════════════════════════════════════════════

    public function batalkan(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'alasan' => 'nullable|string|max:200',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $pengajuan = PengajuanIzin::where('tenaga_pendidik_id', $tp->id)->find($id);
        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan.',
            ], 404);
        }

        try {
            $this->service->batalkan(
                $pengajuan,
                $request->alasan ?? 'Dibatalkan oleh guru via aplikasi.'
            );
            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil dibatalkan.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════════════════

    private function formatPengajuan(PengajuanIzin $p): array
    {
        return [
            'id'                => $p->id,
            'jenis_id'          => $p->setting_jenis_pengajuan_id,
            'jenis_nama'        => $p->jenisPengajuan?->nama ?? '—',
            'jenis_kategori'    => $p->jenisPengajuan?->kategori ?? 'izin',
            'tanggal_mulai'     => $p->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai'   => $p->tanggal_selesai?->format('Y-m-d'),
            'tanggal_mulai_label'  => $p->tanggal_mulai?->locale('id')->isoFormat('D MMM YYYY'),
            'tanggal_selesai_label'=> $p->tanggal_selesai?->locale('id')->isoFormat('D MMM YYYY'),
            'jumlah_hari'       => $p->jumlah_hari,
            'alasan'            => $p->alasan,
            'status'            => $p->status,          // pending|disetujui|ditolak|dibatalkan
            'catatan_admin'     => $p->catatan_admin,
            'nama_dokumen'      => $p->nama_dokumen,
            'dokumen_url'       => $p->file_dokumen
                ? asset('storage/'.$p->file_dokumen) : null,
            'bisa_batalkan'     => $p->status === 'pending'
                || ($p->status === 'disetujui' && $p->tanggal_mulai?->isFuture()),
            'created_at'        => $p->created_at?->locale('id')->isoFormat('D MMM YYYY, HH:mm'),
            'tanggal_keputusan' => $p->tanggal_keputusan?->locale('id')->isoFormat('D MMM YYYY, HH:mm'),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }
}
