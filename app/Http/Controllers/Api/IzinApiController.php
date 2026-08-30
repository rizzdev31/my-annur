<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\SettingJenisPengajuan;
use App\Services\IzinSementaraService;
use App\Services\PengajuanIzinService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IzinApiController extends Controller
{
    public function __construct(
        private readonly PengajuanIzinService $service,
        private readonly IzinSementaraService $izinSementara,
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
    // IZIN SEMENTARA (partial-day) — preview sesi & buat (auto-approve)
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /izin/sementara/preview?jam_mulai=&jam_selesai= — sesi mengajar terdampak (tanpa membuat). */
    public function sementaraPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jam_mulai'   => 'required|date_format:H:i,H:i:s',
            'jam_selesai' => 'required|date_format:H:i,H:i:s',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        if (!$this->izinSementara->windowValid($data['jam_mulai'], $data['jam_selesai'])) {
            return response()->json(['success' => false, 'message' => 'Jam selesai harus setelah jam mulai.'], 422);
        }

        $sesi = $this->izinSementara->sesiTerdampak($tp, TimezoneHelper::now(), $data['jam_mulai'], $data['jam_selesai']);

        return response()->json([
            'success' => true,
            'data'    => ['sesi_terdampak' => $sesi->map(fn ($j) => $this->formatSesi($j))->values()],
        ]);
    }

    /** POST /izin/sementara — buat izin sementara (langsung disetujui) + kembalikan sesi terdampak. */
    public function sementaraBuat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jam_mulai'   => 'required|date_format:H:i,H:i:s',
            'jam_selesai' => 'required|date_format:H:i,H:i:s',
            'alasan'      => 'required|string|max:255',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        if (!$this->izinSementara->windowValid($data['jam_mulai'], $data['jam_selesai'])) {
            return response()->json(['success' => false, 'message' => 'Jam selesai harus setelah jam mulai.'], 422);
        }

        // Edge #1: hanya untuk yang SUDAH check-in hari ini.
        $now = TimezoneHelper::now();
        $sudahCheckin = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $now->toDateString())
            ->whereNotNull('jam_masuk')->exists();
        if (!$sudahCheckin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin sementara hanya untuk yang sudah check-in. Jika belum masuk, gunakan izin harian biasa.',
            ], 422);
        }

        // Edge #8: cegah dua izin sementara aktif tumpang tindih di hari sama.
        $bentrokIzin = PengajuanIzin::sementaraAktif($tp->id, $now->toDateString())
            ->where('jam_mulai', '<', strlen($data['jam_selesai']) === 5 ? $data['jam_selesai'] . ':00' : $data['jam_selesai'])
            ->where('jam_selesai', '>', strlen($data['jam_mulai']) === 5 ? $data['jam_mulai'] . ':00' : $data['jam_mulai'])
            ->exists();
        if ($bentrokIzin) {
            return response()->json(['success' => false, 'message' => 'Sudah ada izin sementara di rentang jam yang tumpang tindih.'], 422);
        }

        $izin = $this->izinSementara->ajukan(
            $tp, $data['jam_mulai'], $data['jam_selesai'], $data['alasan'], $now, $request->user()->id
        );
        $sesi = $this->izinSementara->sesiTerdampak($tp, $now, $data['jam_mulai'], $data['jam_selesai']);

        // Info ke admin (izin sementara berlaku seketika tanpa approval).
        \App\Services\NotifikasiService::keSuperadmin(
            'Izin Sementara',
            ($request->user()->name ?? 'Guru') . ' izin sementara '
                . substr((string) $izin->jam_mulai, 0, 5) . '–' . substr((string) $izin->jam_selesai, 0, 5)
                . ' (' . $sesi->count() . ' sesi mengajar terdampak).',
            'izin',
            ['type' => 'izin', 'route' => '/perizinan']
        );

        return response()->json([
            'success' => true,
            'message' => $sesi->isEmpty()
                ? 'Izin sementara tercatat. Tidak ada sesi mengajar yang terdampak.'
                : 'Izin sementara tercatat. ' . $sesi->count() . ' sesi mengajar butuh pengganti.',
            'data' => [
                'izin_id'         => $izin->id,
                'jam_mulai'       => substr((string) $izin->jam_mulai, 0, 5),
                'jam_selesai'     => substr((string) $izin->jam_selesai, 0, 5),
                'sesi_terdampak'  => $sesi->map(fn ($j) => $this->formatSesi($j))->values(),
            ],
        ]);
    }

    /** POST /izin/sementara/{id}/batal — batalkan izin sementara + rollback pengganti belum absen. */
    public function sementaraBatal(Request $request, int $id): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $izin = PengajuanIzin::where('tenaga_pendidik_id', $tp->id)->where('is_sementara', true)->find($id);
        if (!$izin) return response()->json(['success' => false, 'message' => 'Izin sementara tidak ditemukan.'], 404);

        try {
            $r = $this->izinSementara->batalkan($izin);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json([
            'success' => true,
            'message' => 'Izin sementara dibatalkan.'
                . ($r['pengganti_terlanjur'] ? ' Catatan: ' . $r['pengganti_terlanjur'] . ' sesi tetap (pengganti sudah mengajar).' : ''),
            'data'    => $r,
        ]);
    }

    /** POST /izin/datang-terlambat — guru mengajukan izin datang terlambat (perlu approval admin). */
    public function datangTerlambat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => 'required|date|after_or_equal:today',
            'jam'     => 'required|date_format:H:i,H:i:s',   // usul jam boleh datang
            'alasan'  => 'required|string|max:255',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $jenis = SettingJenisPengajuan::where('kode', 'DATANG_TERLAMBAT')->firstOrFail();

        $ada = PengajuanIzin::where('tenaga_pendidik_id', $tp->id)
            ->where('is_datang_terlambat', true)->whereIn('status', ['pending', 'disetujui'])
            ->whereDate('tanggal_mulai', $data['tanggal'])->exists();
        if ($ada) {
            return response()->json(['success' => false, 'message' => 'Sudah ada izin datang terlambat untuk tanggal itu.'], 422);
        }

        $jam = strlen($data['jam']) === 5 ? $data['jam'] . ':00' : $data['jam'];
        $izin = PengajuanIzin::create([
            'tenaga_pendidik_id'         => $tp->id,
            'setting_jenis_pengajuan_id' => $jenis->id,
            'tanggal_mulai'              => $data['tanggal'],
            'tanggal_selesai'            => $data['tanggal'],
            'jam_mulai'                  => $jam,
            'is_datang_terlambat'        => true,
            'jumlah_hari'                => 1,
            'alasan'                     => $data['alasan'],
            'status'                     => 'pending',
        ]);

        \App\Services\NotifikasiService::keSuperadmin(
            'Izin Datang Terlambat',
            ($request->user()->name ?? 'Guru') . ' mengajukan datang terlambat ' . $data['tanggal']
                . ' (boleh datang s/d ' . substr($jam, 0, 5) . ').',
            'izin', ['type' => 'izin', 'route' => '/pengajuan-izin']
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan datang terlambat terkirim. Menunggu persetujuan admin.',
            'data'    => ['id' => $izin->id],
        ]);
    }

    private function formatSesi(JadwalMengajar $j): array
    {
        return [
            'jadwal_mengajar_id' => $j->id,
            'mapel'              => $j->mataPelajaran?->nama ?? '—',
            'kelas'              => $j->kelasRel?->nama ?? $j->kelas ?? '—',
            'jam_mulai'          => substr((string) $j->jam_mulai, 0, 5),
            'jam_selesai'        => substr((string) $j->jam_selesai, 0, 5),
            'jumlah_jp'          => $j->jumlah_jp,
        ];
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
