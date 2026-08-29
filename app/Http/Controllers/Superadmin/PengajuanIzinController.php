<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanIzin;
use App\Models\TenagaPendidik;
use App\Models\SettingJenisPengajuan;
use App\Services\PengajuanIzinService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PengajuanIzinController extends Controller
{
    public function __construct(
        private readonly PengajuanIzinService $pengajuanService
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
            'catatan' => 'nullable|string|max:500',
        ]);

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
}