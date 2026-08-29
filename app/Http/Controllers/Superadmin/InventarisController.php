<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Inventaris;
use App\Models\PeminjamanInventaris;
use App\Services\PeminjamanInventarisService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Admin inventaris: kelola master (benda/ruang/bangunan/dll), tampung pengajuan
 * pemakaian dari guru (setujui/tolak), monitor real-time, rekap bulanan.
 */
class InventarisController extends Controller
{
    public function __construct(private PeminjamanInventarisService $svc) {}

    public function index(Request $request)
    {
        $statusFilter = $request->status ?: 'diajukan';

        $inventaris = Inventaris::orderBy('kategori')->orderBy('nama')->get()
            ->map(fn($i) => [
                'id' => $i->id, 'kode' => $i->kode, 'nama' => $i->nama,
                'kategori' => $i->kategori, 'lokasi' => $i->lokasi,
                'jumlah_total' => $i->jumlah_total, 'satuan' => $i->satuan,
                'kondisi' => $i->kondisi, 'perlu_persetujuan' => $i->perlu_persetujuan,
                'is_aktif' => $i->is_aktif, 'keterangan' => $i->keterangan,
            ]);

        $pengajuan = PeminjamanInventaris::with(['inventaris:id,nama,kategori', 'tenagaPendidik.user:id,name'])
            ->when($statusFilter !== 'semua', fn($q) => $q->where('status', $statusFilter))
            ->orderByDesc('created_at')->limit(200)->get()
            ->map(fn($p) => $this->mapPeminjaman($p));

        // Monitor: yang disetujui & berlangsung hari ini.
        $hariIni = PeminjamanInventaris::with(['inventaris:id,nama,kategori', 'tenagaPendidik.user:id,name'])
            ->where('status', 'disetujui')->whereDate('tanggal', today())
            ->orderBy('jam_mulai')->get()->map(fn($p) => $this->mapPeminjaman($p));

        return Inertia::render('Admin/Inventaris/Index', [
            'inventaris'  => $inventaris,
            'pengajuan'   => $pengajuan,
            'hariIni'     => $hariIni,
            'filterStatus'=> $statusFilter,
            'kategoriOpsi'=> ['ruang', 'bangunan', 'alat', 'elektronik', 'kendaraan', 'lainnya'],
            'ringkasan'   => [
                'total_item' => $inventaris->count(),
                'pending'    => PeminjamanInventaris::where('status', 'diajukan')->count(),
                'dipakai_hari_ini' => $hariIni->count(),
            ],
        ]);
    }

    // ─── Master CRUD ────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $this->validateMaster($request);
        Inventaris::create($data);
        return back()->with('success', "Inventaris \"{$data['nama']}\" ditambahkan.");
    }

    public function update(Request $request, Inventaris $inventaris)
    {
        $data = $this->validateMaster($request, $inventaris->id);
        $inventaris->update($data);
        return back()->with('success', 'Inventaris diperbarui.');
    }

    public function destroy(Inventaris $inventaris)
    {
        if ($inventaris->peminjaman()->whereIn('status', ['diajukan', 'disetujui'])->exists()) {
            return back()->with('error', 'Tidak bisa dihapus: masih ada pengajuan/peminjaman aktif.');
        }
        $inventaris->delete();
        return back()->with('success', 'Inventaris dihapus.');
    }

    // ─── Keputusan pengajuan ──────────────────────────────────────────────────────

    public function setujui(Request $request, PeminjamanInventaris $peminjaman)
    {
        try {
            $this->svc->setujui($peminjaman->id, Auth::id(), $request->catatan);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Pengajuan disetujui.');
    }

    public function tolak(Request $request, PeminjamanInventaris $peminjaman)
    {
        $request->validate(['alasan' => 'required|string|max:255']);
        try {
            $this->svc->tolak($peminjaman->id, Auth::id(), $request->alasan);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function selesai(PeminjamanInventaris $peminjaman)
    {
        try { $this->svc->selesai($peminjaman->id); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Peminjaman ditandai selesai.');
    }

    public function batal(PeminjamanInventaris $peminjaman)
    {
        try { $this->svc->batal($peminjaman->id); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Peminjaman dibatalkan.');
    }

    // ─── Rekap bulanan ────────────────────────────────────────────────────────────

    public function rekap(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        $base = PeminjamanInventaris::whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->whereIn('status', ['disetujui', 'selesai']);

        // Per item: frekuensi & total jam pakai.
        $rows = (clone $base)->with('inventaris:id,nama,kategori')->get();
        $perItem = $rows->groupBy('inventaris_id')->map(function ($g) {
            $first = $g->first();
            $totalMenit = $g->sum(fn($p) =>
                max(0, Carbon::parse($p->jam_selesai)->diffInMinutes(Carbon::parse($p->jam_mulai))));
            return [
                'nama'      => $first->inventaris?->nama ?? '—',
                'kategori'  => $first->inventaris?->kategori ?? '-',
                'frekuensi' => $g->count(),
                'total_jam' => round($totalMenit / 60, 1),
            ];
        })->sortByDesc('frekuensi')->values();

        // Per guru: frekuensi pemakaian.
        $rowsG = (clone $base)->with('tenagaPendidik.user:id,name')->get();
        $perGuru = $rowsG->groupBy('tenaga_pendidik_id')->map(fn($g) => [
            'nama'      => $g->first()->tenagaPendidik?->user?->name ?? '—',
            'frekuensi' => $g->count(),
        ])->sortByDesc('frekuensi')->values();

        return Inertia::render('Admin/Inventaris/Rekap', [
            'bulan' => $bulan, 'tahun' => $tahun,
            'perItem' => $perItem, 'perGuru' => $perGuru,
            'ringkasan' => [
                'total_peminjaman' => $rows->count(),
                'item_terpakai'    => $perItem->count(),
                'guru_aktif'       => $perGuru->count(),
            ],
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────────

    private function validateMaster(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode'              => 'required|string|max:50|unique:inventaris,kode' . ($id ? ",$id" : ''),
            'nama'              => 'required|string|max:150',
            'kategori'          => 'required|in:ruang,bangunan,alat,elektronik,kendaraan,lainnya',
            'lokasi'            => 'nullable|string|max:150',
            'jumlah_total'      => 'required|integer|min:1',
            'satuan'            => 'nullable|string|max:30',
            'kondisi'           => 'required|in:baik,perlu_perbaikan,rusak',
            'perlu_persetujuan' => 'boolean',
            'is_aktif'          => 'boolean',
            'keterangan'        => 'nullable|string|max:500',
        ]);
    }

    private function mapPeminjaman(PeminjamanInventaris $p): array
    {
        return [
            'id'        => $p->id,
            'inventaris'=> $p->inventaris?->nama ?? '—',
            'kategori'  => $p->inventaris?->kategori ?? '-',
            'peminjam'  => $p->tenagaPendidik?->user?->name ?? '—',
            'jumlah'    => $p->jumlah,
            'keperluan' => $p->keperluan,
            'tanggal'   => $p->tanggal?->toDateString(),
            'tanggal_label' => $p->tanggal?->locale('id')->isoFormat('ddd, D MMM YYYY'),
            'jam'       => substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5),
            'status'    => $p->status,
            'catatan_admin' => $p->catatan_admin,
        ];
    }
}
