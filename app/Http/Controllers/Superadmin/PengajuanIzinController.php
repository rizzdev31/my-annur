<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\TenagaPendidik;
use App\Models\SettingJenisPengajuan;
use App\Services\IzinSementaraService;
use App\Services\PengajuanIzinService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PengajuanIzinController extends Controller
{
    public function __construct(
        private readonly PengajuanIzinService $pengajuanService,
        private readonly IzinSementaraService $izinSementara,
    ) {}

    /**
     * Daftar semua pengajuan — monitor superadmin.
     */
    public function index(Request $request)
    {
        $query = PengajuanIzin::with([
            'tenagaPendidik.user',
            'tenagaPendidik.jabatan',
            'jenisPengajuan',
            'diprosesOleh',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->jenis_id, fn($q) => $q->where('setting_jenis_pengajuan_id', $request->jenis_id))
        ->when($request->guru_id, fn($q) => $q->where('tenaga_pendidik_id', $request->guru_id))
        ->when($request->bulan, fn($q) => $q->whereMonth('tanggal_mulai', $request->bulan))
        ->when($request->tahun, fn($q) => $q->whereYear('tanggal_mulai', $request->tahun))
        ->orderByRaw("FIELD(status, 'pending', 'disetujui', 'ditolak', 'dibatalkan')")
        ->orderByDesc('created_at');

        $pengajuan = $query->paginate(15)->withQueryString();

        $pengajuan->through(fn($p) => [
            'id'               => $p->id,
            'nama_guru'        => $p->tenagaPendidik->user->name,
            'foto_guru'        => $p->tenagaPendidik->user->foto
                                    ? asset('storage/' . $p->tenagaPendidik->user->foto)
                                    : null,
            'jabatan'          => $p->tenagaPendidik->jabatan->nama_jabatan ?? '-',
            'jenis'            => $p->jenisPengajuan->nama,
            'jenis_kategori'   => $p->jenisPengajuan->kategori,
            'jenis_badge'      => SettingJenisPengajuan::badgeKategori($p->jenisPengajuan->kategori),
            'tanggal_mulai'    => $p->tanggal_mulai->format('d M Y'),
            'tanggal_selesai'  => $p->tanggal_selesai->format('d M Y'),
            'jumlah_hari'      => $p->jumlah_hari,
            'is_datang_terlambat' => (bool) $p->is_datang_terlambat,
            'jam_mulai'        => $p->jam_mulai ? substr((string) $p->jam_mulai, 0, 5) : null,
            'alasan'           => $p->alasan,
            'file_dokumen'     => $p->file_dokumen ? asset('storage/' . $p->file_dokumen) : null,
            'nama_dokumen'     => $p->nama_dokumen,
            'status'           => $p->status,
            'status_badge'     => $p->status_badge,
            'catatan_admin'    => $p->catatan_admin,
            'diproses_oleh'    => $p->diprosesOleh?->name,
            'tanggal_keputusan'=> $p->tanggal_keputusan?->format('d M Y H:i'),
            'created_at'       => $p->created_at->format('d M Y H:i'),
        ]);

        return Inertia::render('Admin/SmartPayroll/PengajuanIzin/Index', [
            'pengajuan'     => $pengajuan,
            'jenisList'     => SettingJenisPengajuan::aktif()
                                ->get(['id', 'nama', 'kategori', 'kuota_per_tahun',
                                       'max_hari_per_pengajuan', 'butuh_dokumen',
                                       'keterangan_dokumen', 'auto_approve', 'pengaruh_gaji']),
            'guruList'      => TenagaPendidik::with(['user', 'jabatan'])
                                ->aktif()
                                ->get()
                                ->map(fn($g) => [
                                    'id'      => $g->id,
                                    'nama'    => $g->user->name,
                                    'jabatan' => $g->jabatan->nama_jabatan ?? '-',
                                ])
                                ->sortBy('nama')
                                ->values(),
            'filters'       => $request->only(['status', 'jenis_id', 'guru_id', 'bulan', 'tahun']),
            'summary'       => [
                'pending'   => PengajuanIzin::pending()->count(),
                'disetujui' => PengajuanIzin::disetujui()->whereMonth('tanggal_mulai', now()->month)->count(),
                'bulan_ini' => PengajuanIzin::whereMonth('created_at', now()->month)->count(),
            ],
        ]);
    }

    /**
     * Detail 1 pengajuan.
     */
    public function show(PengajuanIzin $pengajuanIzin)
    {
        $pengajuanIzin->load([
            'tenagaPendidik.user',
            'tenagaPendidik.jabatan',
            'jenisPengajuan',
            'diprosesOleh',
        ]);

        return Inertia::render('Admin/SmartPayroll/PengajuanIzin/Show', [
            'pengajuan' => [
                'id'               => $pengajuanIzin->id,
                'nama_guru'        => $pengajuanIzin->tenagaPendidik->user->name,
                'foto_guru'        => $pengajuanIzin->tenagaPendidik->user->foto
                                        ? asset('storage/' . $pengajuanIzin->tenagaPendidik->user->foto)
                                        : null,
                'jabatan'          => $pengajuanIzin->tenagaPendidik->jabatan->nama_jabatan ?? '-',
                'nip'              => $pengajuanIzin->tenagaPendidik->nip,
                'jenis'            => $pengajuanIzin->jenisPengajuan->nama,
                'jenis_badge'      => SettingJenisPengajuan::badgeKategori($pengajuanIzin->jenisPengajuan->kategori),
                'tanggal_mulai'    => $pengajuanIzin->tanggal_mulai->format('d M Y'),
                'tanggal_selesai'  => $pengajuanIzin->tanggal_selesai->format('d M Y'),
                'jumlah_hari'      => $pengajuanIzin->jumlah_hari,
                'alasan'           => $pengajuanIzin->alasan,
                'file_dokumen'     => $pengajuanIzin->file_dokumen ? asset('storage/' . $pengajuanIzin->file_dokumen) : null,
                'nama_dokumen'     => $pengajuanIzin->nama_dokumen,
                'status'           => $pengajuanIzin->status,
                'status_badge'     => $pengajuanIzin->status_badge,
                'catatan_admin'    => $pengajuanIzin->catatan_admin,
                'diproses_oleh'    => $pengajuanIzin->diprosesOleh?->name,
                'tanggal_keputusan'=> $pengajuanIzin->tanggal_keputusan?->format('d M Y H:i'),
                'is_pending'       => $pengajuanIzin->isPending(),
                'absensi_diupdate' => $pengajuanIzin->absensi_sudah_diupdate,
                'created_at'       => $pengajuanIzin->created_at->format('d M Y H:i'),
            ],
        ]);
    }

    /**
     * Setujui pengajuan.
     */
    public function setujui(Request $request, PengajuanIzin $pengajuanIzin)
    {
        $request->validate([
            'catatan'   => 'nullable|string|max:500',
            'jam_mulai' => 'nullable|date_format:H:i,H:i:s',   // admin bisa atur jam (datang terlambat)
        ]);

        // Izin datang terlambat: TIDAK lewat alur absensi harian. Cukup set disetujui
        // (+ atur jam batas). Efeknya di check-in (AbsensiKalkulasiService).
        if ($pengajuanIzin->is_datang_terlambat) {
            $update = [
                'status'            => 'disetujui',
                'catatan_admin'     => $request->catatan,
                'diproses_oleh'     => $request->user()->id,
                'tanggal_keputusan' => now(),
            ];
            if ($request->filled('jam_mulai')) {
                $update['jam_mulai'] = strlen($request->jam_mulai) === 5 ? $request->jam_mulai . ':00' : $request->jam_mulai;
            }
            $pengajuanIzin->update($update);

            if ($pengajuanIzin->tenagaPendidik?->user) {
                \App\Services\NotifikasiService::kirim(
                    $pengajuanIzin->tenagaPendidik->user->id, 'Izin Datang Terlambat Disetujui',
                    'Kamu boleh datang s/d ' . substr((string) $pengajuanIzin->jam_mulai, 0, 5)
                        . ' pada ' . $pengajuanIzin->tanggal_mulai?->format('d/m/Y') . '. Dalam batas itu tetap dihitung hadir.',
                    'izin', ['type' => 'izin', 'route' => '/izin']
                );
            }
            return back()->with('success', 'Izin datang terlambat disetujui (boleh datang s/d ' . substr((string) $pengajuanIzin->jam_mulai, 0, 5) . ').');
        }

        try {
            $this->pengajuanService->setujui($pengajuanIzin, $request->catatan);

            return back()->with('success',
                "Pengajuan {$pengajuanIzin->jenisPengajuan->nama} dari {$pengajuanIzin->tenagaPendidik->user->name} telah disetujui. Absensi diupdate otomatis."
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Tolak pengajuan.
     */
    public function tolak(Request $request, PengajuanIzin $pengajuanIzin)
    {
        $request->validate([
            'catatan' => 'required|string|max:500',
        ], [
            'catatan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        try {
            $this->pengajuanService->tolak($pengajuanIzin, $request->catatan);

            return back()->with('success',
                "Pengajuan {$pengajuanIzin->jenisPengajuan->nama} dari {$pengajuanIzin->tenagaPendidik->user->name} ditolak."
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Batalkan pengajuan.
     */
    public function batalkan(Request $request, PengajuanIzin $pengajuanIzin)
    {
        $request->validate([
            'alasan' => 'required|string|max:500',
        ]);

        try {
            $this->pengajuanService->batalkan($pengajuanIzin, $request->alasan);

            return back()->with('success', 'Pengajuan berhasil dibatalkan.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Input manual pengajuan oleh superadmin untuk guru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tenaga_pendidik_id'         => 'required|exists:tenaga_pendidik,id',
            'setting_jenis_pengajuan_id' => 'required|exists:setting_jenis_pengajuan,id',
            'tanggal_mulai'              => 'required|date',
            'tanggal_selesai'            => 'required|date|after_or_equal:tanggal_mulai',
            'alasan'                     => 'required|string|max:500',
            'file_dokumen'               => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $guru = TenagaPendidik::findOrFail($request->tenaga_pendidik_id);

        try {
            $this->pengajuanService->buat(
                $guru,
                $request->except('file_dokumen'),
                $request->file('file_dokumen')
            );

            return redirect()->route('admin.smart-payroll.pengajuan-izin.index')
                ->with('success', "Pengajuan berhasil dibuat untuk {$guru->user->name}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // IZIN SEMENTARA (admin buatkan atas nama guru) — endpoint JSON (axios)
    // ══════════════════════════════════════════════════════════════════════════

    /** POST pengajuan-izin/sementara/preview — sesi mengajar terdampak (tanpa membuat). */
    public function sementaraPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'jam_mulai'          => 'required|date_format:H:i,H:i:s',
            'jam_selesai'        => 'required|date_format:H:i,H:i:s',
        ]);
        if (!$this->izinSementara->windowValid($data['jam_mulai'], $data['jam_selesai'])) {
            return response()->json(['success' => false, 'message' => 'Jam selesai harus setelah jam mulai.'], 422);
        }
        $guru = TenagaPendidik::findOrFail($data['tenaga_pendidik_id']);
        $sesi = $this->izinSementara->sesiTerdampak($guru, TimezoneHelper::now(), $data['jam_mulai'], $data['jam_selesai']);

        return response()->json(['success' => true, 'data' => ['sesi_terdampak' => $sesi->map(fn ($j) => $this->formatSesiAdmin($j))->values()]]);
    }

    /** POST pengajuan-izin/sementara — buat izin sementara atas nama guru (langsung disetujui). */
    public function sementaraStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'jam_mulai'          => 'required|date_format:H:i,H:i:s',
            'jam_selesai'        => 'required|date_format:H:i,H:i:s',
            'alasan'             => 'required|string|max:255',
        ]);
        if (!$this->izinSementara->windowValid($data['jam_mulai'], $data['jam_selesai'])) {
            return response()->json(['success' => false, 'message' => 'Jam selesai harus setelah jam mulai.'], 422);
        }
        $guru = TenagaPendidik::with('user')->findOrFail($data['tenaga_pendidik_id']);
        $now  = TimezoneHelper::now();

        $izin = $this->izinSementara->ajukan($guru, $data['jam_mulai'], $data['jam_selesai'], $data['alasan'], $now, $request->user()->id);
        $sesi = $this->izinSementara->sesiTerdampak($guru, $now, $data['jam_mulai'], $data['jam_selesai']);

        // Info ke guru yang bersangkutan (dibuatkan admin).
        if ($guru->user) {
            \App\Services\NotifikasiService::kirim(
                $guru->user->id,
                'Izin Sementara Dibuatkan',
                'Admin membuatkan izin sementara Anda ' . substr((string) $izin->jam_mulai, 0, 5) . '–' . substr((string) $izin->jam_selesai, 0, 5) . '.',
                'izin',
                ['type' => 'izin', 'route' => '/izin']
            );
        }

        return response()->json([
            'success' => true,
            'message' => $sesi->isEmpty() ? 'Izin sementara dibuat. Tidak ada sesi terdampak.' : 'Izin sementara dibuat. ' . $sesi->count() . ' sesi butuh pengganti.',
            'data'    => ['izin_id' => $izin->id, 'sesi_terdampak' => $sesi->map(fn ($j) => $this->formatSesiAdmin($j))->values()],
        ]);
    }

    /** POST pengajuan-izin/sementara/tunjuk-pengganti — admin tunjuk pengganti atas nama guru. */
    public function sementaraTunjukPengganti(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jadwal_mengajar_id' => 'required|exists:jadwal_mengajar,id',
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'pengganti_id'       => 'required|exists:tenaga_pendidik,id',
            'keterangan'         => 'nullable|string|max:255',
        ]);
        try {
            $absensi = (new \App\Services\PenggantiMengajarService())->tunjukPengganti(
                (int) $data['jadwal_mengajar_id'], (int) $data['tenaga_pendidik_id'], (int) $data['pengganti_id'],
                TimezoneHelper::now(), $data['keterangan'] ?? 'Izin sementara (dibuatkan admin)'
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Pengganti ditunjuk.', 'data' => ['absensi_id' => $absensi->id]]);
    }

    /** POST pengajuan-izin/{izin}/sementara-batal — batalkan izin sementara + rollback pengganti. */
    public function sementaraBatal(PengajuanIzin $pengajuanIzin): JsonResponse
    {
        if (!$pengajuanIzin->is_sementara) {
            return response()->json(['success' => false, 'message' => 'Bukan izin sementara.'], 422);
        }
        try {
            $r = $this->izinSementara->batalkan($pengajuanIzin);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Izin sementara dibatalkan.', 'data' => $r]);
    }

    private function formatSesiAdmin(JadwalMengajar $j): array
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
}