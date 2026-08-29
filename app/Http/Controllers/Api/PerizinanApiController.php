<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\IzinSantri;
use App\Services\WaService;
use App\Services\WaTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Perizinan Santri untuk GURU PETUGAS (Flutter).
 * Hanya guru yang ditunjuk (petugas 'perizinan') yang boleh mengakses.
 * Sementara guru mengajukan atas nama santri (akun santri menyusul).
 */
class PerizinanApiController extends Controller
{
    /** Pastikan user petugas perizinan; kembalikan tenaga_pendidik atau null+response. */
    private function petugas(Request $request)
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp || !$tp->isPetugas('perizinan')) return null;
        return $tp;
    }

    private function tolakAkses(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Anda bukan Petugas Perizinan.'], 403);
    }

    /** GET /perizinan/status — apakah user petugas perizinan. */
    public function status(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        return response()->json(['success' => true, 'data' => [
            'is_petugas' => (bool) ($tp && $tp->isPetugas('perizinan')),
        ]]);
    }

    /** GET /perizinan/santri?q= — cari santri (untuk memilih pemohon). Semua guru boleh (untuk lapor). */
    public function santri(Request $request): JsonResponse
    {
        if (!$request->user()->tenagaPendidik) return $this->tolakAkses();
        $q = trim((string) $request->q);
        $data = Santri::aktif()
            ->when($q !== '', fn($x) => $x->where(fn($w) =>
                $w->where('nama_lengkap', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%")))
            ->orderBy('nama_lengkap')->limit(30)->get(['id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /perizinan — daftar izin.
     * Petugas: semua izin (untuk verifikasi). Guru lain: HANYA izin yang ia laporkan
     * sendiri (untuk melacak status laporannya).
     */
    public function daftar(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->tolakAkses();

        $q = IzinSantri::with(['santri:id,nama_lengkap', 'diajukanOleh.user:id,name'])->orderByDesc('id');
        if (!$tp->isPetugas('perizinan')) {
            $q->where('diajukan_oleh', $tp->id); // guru biasa: hanya laporannya sendiri
        }
        $list = $q->limit(100)->get()->map(fn($i) => $this->map($i));
        return response()->json(['success' => true, 'data' => $list]);
    }

    /** POST /perizinan — LAPOR/ajukan izin atas nama santri. Boleh SEMUA guru (fungsi pelaporan);
     * persetujuan tetap oleh Petugas Perizinan. */
    public function ajukan(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->tolakAkses();

        $d = $request->validate([
            'santri_id'       => 'required|exists:santri,id',
            'jenis'           => 'required|in:syari,non_syari',
            'alasan'          => 'required|string|max:255',
            'tanggal_mulai'   => 'required|date_format:Y-m-d',
            'tanggal_selesai' => 'required|date_format:Y-m-d|after_or_equal:tanggal_mulai',
        ]);

        $izin = IzinSantri::create([
            'santri_id'       => $d['santri_id'],
            'jenis'           => $d['jenis'],
            'alasan'          => $d['alasan'],
            'tanggal_mulai'   => $d['tanggal_mulai'],
            'tanggal_selesai' => $d['tanggal_selesai'],
            'status'          => 'diajukan',
            'pengaju_tipe'    => 'guru',
            'diajukan_oleh'   => $tp->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Izin diajukan. Menunggu persetujuan petugas.',
            'data'    => ['id' => $izin->id, 'status' => $izin->status],
        ], 201);
    }

    /** POST /perizinan/{izin}/setujui — setujui + WA ke wali. */
    public function setujui(Request $request, IzinSantri $izin): JsonResponse
    {
        $tp = $this->petugas($request);
        if (!$tp) return $this->tolakAkses();
        if ($izin->status !== 'diajukan') {
            return response()->json(['success' => false, 'message' => 'Izin ini sudah diproses.'], 422);
        }

        $izin->update([
            'status'          => 'disetujui',
            'disetujui_oleh'  => $tp->id,
            'catatan_petugas' => $request->catatan,
            'diputuskan_pada' => now(),
        ]);

        // Notifikasi WA ke wali santri.
        $s = $izin->santri;
        if ($s) {
            app(WaService::class)->enqueue('izin', $s,
                WaTemplate::izin($s->nama_lengkap, $izin->jenis_label, $izin->alasan,
                    $izin->tanggal_mulai->toDateString(), $izin->tanggal_selesai->toDateString()),
                'WA-IZIN-' . $izin->id);
        }

        return response()->json(['success' => true, 'message' => 'Izin disetujui & wali diberi tahu.']);
    }

    /** POST /perizinan/{izin}/tolak — tolak dengan alasan. */
    public function tolak(Request $request, IzinSantri $izin): JsonResponse
    {
        $tp = $this->petugas($request);
        if (!$tp) return $this->tolakAkses();
        $request->validate(['alasan' => 'required|string|max:255']);
        if ($izin->status !== 'diajukan') {
            return response()->json(['success' => false, 'message' => 'Izin ini sudah diproses.'], 422);
        }
        $izin->update([
            'status'          => 'ditolak',
            'disetujui_oleh'  => $tp->id,
            'catatan_petugas' => $request->alasan,
            'diputuskan_pada' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Izin ditolak.']);
    }

    private function map(IzinSantri $i): array
    {
        return [
            'id'          => $i->id,
            'santri'      => $i->santri?->nama_lengkap ?? '—',
            'jenis'       => $i->jenis,
            'jenis_label' => $i->jenis_label,
            'alasan'      => $i->alasan,
            'tanggal_mulai'   => $i->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $i->tanggal_selesai?->toDateString(),
            'status'      => $i->status,
            'diajukan'    => $i->diajukanOleh?->user?->name,
            'catatan'     => $i->catatan_petugas,
        ];
    }
}
