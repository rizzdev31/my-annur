<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LemburPeserta;
use App\Services\LemburService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LemburApiController extends Controller
{
    public function __construct(private LemburService $service) {}

    /** Daftar lembur milik guru (sebagai peserta). */
    public function index(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $rows = LemburPeserta::with(['lembur.jabatan'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($p) => $this->mapPeserta($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** Guru mengajukan lembur (mandiri): tanggal + jam mulai + durasi. Tarif diisi admin saat approve. */
    public function ajukan(Request $request): JsonResponse
    {
        $request->validate([
            'judul'        => 'required|string|max:150',
            'deskripsi'    => 'nullable|string|max:1000',
            'tanggal'      => 'required|date',
            'jam_mulai'    => 'required|date_format:H:i',
            'durasi_menit' => 'required|integer|min:1',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();
        if (!$tp->jabatan_id) {
            return response()->json([
                'success' => false,
                'message' => 'Jabatan Anda belum diatur. Hubungi admin.',
            ], 422);
        }

        try {
            $lembur = $this->service->ajukan(
                $tp,
                $request->only('judul', 'deskripsi', 'tanggal', 'jam_mulai', 'durasi_menit'),
                $request->user()->id
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur terkirim. Menunggu persetujuan admin.',
            'data'    => ['id' => $lembur->id, 'status' => $lembur->status],
        ]);
    }

    /** Upload bukti lembur: foto + deskripsi + GPS (geofence ketat). */
    public function uploadBukti(Request $request, int $pesertaId): JsonResponse
    {
        $request->validate([
            'deskripsi' => 'required|string|max:1000',
            'foto'      => 'required|image|mimes:jpeg,jpg,png|max:3072',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'wifi_ssid' => 'nullable|string|max:100',
            'wifi_bssid'=> 'nullable|string|max:100',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $peserta = LemburPeserta::with('lembur')->find($pesertaId);
        if (!$peserta || $peserta->tenaga_pendidik_id !== $tp->id) {
            return $this->notFound();
        }

        // Simpan foto dulu agar service hanya terima path
        $fotoPath = $request->file('foto')->store("bukti-lembur/{$tp->id}", 'public');

        try {
            $this->service->uploadBukti($peserta, [
                'foto_path' => $fotoPath,
                'deskripsi' => $request->deskripsi,
                'latitude'  => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
                'wifi_ssid' => $request->wifi_ssid,
                'wifi_bssid'=> $request->wifi_bssid,
            ]);
        } catch (\Throwable $e) {
            // Rollback file jika validasi gagal (cegah sampah storage)
            \Storage::disk('public')->delete($fotoPath);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bukti lembur berhasil dikirim. Lembur sah.',
            'data'    => $this->mapPeserta($peserta->fresh('lembur')),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function mapPeserta(LemburPeserta $p): array
    {
        $l = $p->lembur;
        $bisaUpload = $l && $l->status === 'disetujui'
            && $p->status === 'menunggu_bukti'
            && $l->dalamJendelaUpload();

        return [
            'id'             => $p->id,
            'judul'          => $l?->judul,
            'jabatan'        => $l?->jabatan?->nama_jabatan,
            'tanggal'        => $l?->tanggal?->format('Y-m-d'),
            'jam_mulai'      => $l?->jam_mulai ? substr($l->jam_mulai, 0, 5) : null,
            'jam_selesai'    => $l?->jam_selesai ? substr($l->jam_selesai, 0, 5) : null,
            'durasi_menit'   => $l?->durasi_menit,
            'lembur_status'  => $l?->status,
            'status'         => $p->status,
            'nominal'        => $p->nominal_vakasi ?? $l?->nominal_vakasi,
            'sudah_upload'   => $p->sudah_upload,
            'foto_bukti_url' => $p->foto_bukti_url,
            'bisa_upload'    => $bisaUpload,
            'buka_upload_pada'=> $l?->waktuSelesai()?->format('Y-m-d H:i'),
            'batas_upload'   => $l?->batasAkhirUpload()?->format('Y-m-d H:i'),
            'metode'         => $p->metode_pengesahan,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }
}
