<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Lembur;
use App\Models\LemburPeserta;
use App\Models\SettingVakasi;
use App\Models\TenagaPendidik;
use App\Services\LemburService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LemburController extends Controller
{
    public function __construct(private LemburService $service) {}

    // ── LIST ──────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $bulan  = (int) ($request->bulan ?? now()->month);
        $tahun  = (int) ($request->tahun ?? now()->year);
        $status = $request->status; // diajukan|disetujui|ditolak|dibatalkan|null

        $lembur = Lembur::with(['jabatan', 'peserta.tenagaPendidik.user', 'settingVakasi'])
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get()
            ->map(fn($l) => $this->ringkasLembur($l))
            ->values();

        return Inertia::render('Admin/SmartPayroll/Lembur/Index', [
            'lembur'  => $lembur,
            'filters' => ['bulan' => $bulan, 'tahun' => $tahun, 'status' => $status],
            'stats'   => [
                'total'          => $lembur->count(),
                'diajukan'       => $lembur->where('status', 'diajukan')->count(),
                'disetujui'      => $lembur->where('status', 'disetujui')->count(),
                'perlu_tindakan' => $lembur->sum('perlu_tindakan'),
            ],
        ]);
    }

    // ── FORM KOLEKTIF ─────────────────────────────────────────────────────────
    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/Lembur/Form', [
            'jabatan'        => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'settingLembur'  => $this->settingLemburList(),
            'guru'           => $this->guruList(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jabatan_id'        => 'required|exists:jabatan,id',
            'judul'             => 'required|string|max:150',
            'deskripsi'         => 'nullable|string',
            'tanggal'           => 'required|date',
            'jam_mulai'         => 'required|date_format:H:i',
            'durasi_menit'      => 'required|integer|min:1',
            'setting_vakasi_id' => 'required|exists:setting_vakasi,id',
            'guru_ids'          => 'required|array|min:1',
            'guru_ids.*'        => 'integer|exists:tenaga_pendidik,id',
        ]);

        try {
            $this->service->buatKolektif($data, $data['guru_ids'], auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.smart-payroll.lembur.index')
            ->with('success', 'Lembur kolektif berhasil dibuat & ditugaskan.');
    }

    // ── DETAIL ────────────────────────────────────────────────────────────────
    public function show(Lembur $lembur)
    {
        $lembur->load(['jabatan', 'settingVakasi', 'diajukanOleh', 'disetujuiOleh',
            'peserta.tenagaPendidik.user', 'peserta.disahkanOleh']);

        $peserta = $lembur->peserta->map(fn($p) => [
            'id'             => $p->id,
            'guru_nama'      => $p->tenagaPendidik?->user?->name ?? '—',
            'guru_foto'      => $p->tenagaPendidik?->user?->foto
                ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
            'status'         => $p->status,
            'sudah_upload'   => $p->sudah_upload,
            'terlewat'       => $p->status === 'menunggu_bukti' && !$p->sudah_upload && $lembur->sudahTerlewat(),
            'deskripsi'      => $p->deskripsi,
            'foto_bukti_url' => $p->foto_bukti_url,
            'latitude'       => $p->latitude,
            'longitude'      => $p->longitude,
            'validasi_lokasi'=> $p->validasi_lokasi,
            'jarak_meter'    => $p->jarak_meter,
            'diunggah_pada'  => $p->diunggah_pada?->format('d M Y H:i'),
            'nominal_vakasi' => $p->nominal_vakasi,
            'metode'         => $p->metode_pengesahan,
            'disahkan_oleh'  => $p->disahkanOleh?->name,
            'alasan'         => $p->alasan_pengesahan,
        ])->values();

        return Inertia::render('Admin/SmartPayroll/Lembur/Show', [
            'lembur'        => $this->detailLembur($lembur),
            'peserta'       => $peserta,
            'settingLembur' => $this->settingLemburList(),
        ]);
    }

    // ── APPROVAL PENGAJUAN MANDIRI ────────────────────────────────────────────
    public function setujui(Request $request, Lembur $lembur)
    {
        $data = $request->validate([
            'setting_vakasi_id' => 'required|exists:setting_vakasi,id',
            'catatan'           => 'nullable|string|max:500',
        ]);

        try {
            $this->service->setujui($lembur, $data, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Pengajuan lembur disetujui.');
    }

    public function tolak(Request $request, Lembur $lembur)
    {
        $request->validate(['catatan' => 'nullable|string|max:500']);
        $this->service->tolak($lembur, $request->catatan, auth()->id());
        return back()->with('success', 'Pengajuan lembur ditolak.');
    }

    public function batalkan(Request $request, Lembur $lembur)
    {
        $request->validate(['catatan' => 'nullable|string|max:500']);
        $this->service->batalkan($lembur, $request->catatan);
        return back()->with('success', 'Lembur dibatalkan.');
    }

    // ── PESERTA: pengesahan manual & pembatalan (exception lupa bukti) ─────────
    public function sahkanManual(Request $request, LemburPeserta $peserta)
    {
        $data = $request->validate(['alasan' => 'required|string|max:255']);
        try {
            $this->service->sahkanManual($peserta, $data['alasan'], auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Lembur peserta disahkan manual.');
    }

    public function batalkanPeserta(Request $request, LemburPeserta $peserta)
    {
        $request->validate(['alasan' => 'nullable|string|max:255']);
        $this->service->batalkanPeserta($peserta, $request->alasan);
        return back()->with('success', 'Peserta lembur dibatalkan.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function ringkasLembur(Lembur $l): array
    {
        $terlewat = $l->status === 'disetujui' && $l->sudahTerlewat();
        return [
            'id'            => $l->id,
            'judul'         => $l->judul,
            'jabatan'       => $l->jabatan?->nama_jabatan ?? '—',
            'tanggal'       => $l->tanggal->format('d M Y'),
            'jam'           => $l->jam_mulai ? substr($l->jam_mulai, 0, 5).'–'.substr($l->jam_selesai ?? '', 0, 5) : '—',
            'sumber_input'  => $l->sumber_input,
            'status'        => $l->status,
            'nominal'       => $l->nominal_vakasi,
            'total_peserta' => $l->peserta->count(),
            'total_sah'     => $l->peserta->where('status', 'sah')->count(),
            'menunggu'      => $l->peserta->where('status', 'menunggu_bukti')->count(),
            'perlu_tindakan'=> $terlewat ? $l->peserta->where('status', 'menunggu_bukti')
                ->filter(fn($p) => !$p->sudah_upload)->count() : 0,
        ];
    }

    private function detailLembur(Lembur $l): array
    {
        return [
            'id'            => $l->id,
            'judul'         => $l->judul,
            'deskripsi'     => $l->deskripsi,
            'jabatan'       => $l->jabatan?->nama_jabatan ?? '—',
            'jabatan_id'    => $l->jabatan_id,
            'tanggal'       => $l->tanggal->format('Y-m-d'),
            'tanggal_label' => $l->tanggal->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'jam_mulai'     => $l->jam_mulai ? substr($l->jam_mulai, 0, 5) : null,
            'jam_selesai'   => $l->jam_selesai ? substr($l->jam_selesai, 0, 5) : null,
            'durasi_menit'  => $l->durasi_menit,
            'sumber_input'  => $l->sumber_input,
            'status'        => $l->status,
            'nominal'       => $l->nominal_vakasi,
            'min_durasi'    => $l->min_durasi_menit,
            'grace'         => $l->batas_grace_menit,
            'setting_nama'  => $l->settingVakasi?->nama,
            'diajukan_oleh' => $l->diajukanOleh?->name,
            'disetujui_oleh'=> $l->disetujuiOleh?->name,
            'catatan'       => $l->catatan,
        ];
    }

    private function settingLemburList()
    {
        return SettingVakasi::where('tipe_aktivitas', 'lembur')->where('is_aktif', true)
            ->orderBy('nama')->get()
            ->map(fn($s) => [
                'id'                => $s->id,
                'nama'              => $s->nama,
                'nominal'           => $s->nominal,
                'min_durasi_menit'  => $s->min_durasi_menit,
                'batas_grace_menit' => $s->batas_grace_menit,
                'jabatan_ids'       => $s->jabatan_ids,
                'lingkup'           => $s->lingkup,
            ])->values();
    }

    private function guruList()
    {
        return TenagaPendidik::aktif()->with(['user', 'jabatanAktif'])->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'nama'        => $g->user?->name,
                'foto'        => $g->user?->foto ? asset('storage/'.$g->user->foto) : null,
                'jabatan_id'  => $g->jabatan_id,
                'jabatan_ids' => $g->jabatanAktif->pluck('jabatan_id')->push($g->jabatan_id)->filter()->unique()->values(),
            ])
            ->filter(fn($g) => $g['nama'] !== null)
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
