<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\JabatanGuru;
use App\Models\TenagaPendidik;
use App\Models\SettingGajiPokok;
use App\Models\SettingVakasi;
use App\Models\PeriodePenggajian;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JabatanController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // JABATAN CRUD
    // ══════════════════════════════════════════════════════════════

    public function index()
    {
        $jabatan = Jabatan::with(['tugasJabatan'])
            ->withCount(['tenagaPendidik'])
            ->orderBy('tipe')
            ->orderBy('nama_jabatan')
            ->get();

        $jabatan = $jabatan->map(function ($j) {
            // Gaji pokok aktif dari setting
            $gajiPokok = SettingGajiPokok::where('jabatan_id', $j->id)
                ->where('is_aktif', true)
                ->latest('berlaku_mulai')
                ->value('nominal') ?? 0;

            // Vakasi jabatan aktif (tipe tugas_jabatan)
            $vakasiJabatan = SettingVakasi::aktif()
                ->where('tipe_aktivitas', 'tugas_jabatan')
                ->where(fn($q) =>
                    $q->where('berlaku_untuk_semua', true)
                      ->orWhereJsonContains('jabatan_ids', $j->id)
                )
                ->latest('berlaku_mulai')
                ->value('nominal') ?? 0;

            // Template tugas jabatan (untuk preview di kartu)
            $tugas = $j->tugasJabatan
                ->map(fn($t) => [
                    'id'            => $t->id,
                    'nama_tugas'    => $t->nama_tugas ?? '—',
                    'frekuensi'     => $t->frekuensi ?? 'insidental',
                    'wajib_laporan' => $t->wajib_laporan ?? false,
                    'vakasi_nominal'=> 0,
                ])
                ->values()
                ->toArray();

            $totalTetap = $gajiPokok + $vakasiJabatan;

            // Dipakai oleh guru = jabatan UTAMA (jabatan_id) + MULTI-jabatan (pivot),
            // dihitung distinct agar akurat & konsisten dengan penjaga hapus.
            $jumlahGuru = JabatanGuru::where('jabatan_id', $j->id)->pluck('tenaga_pendidik_id')
                ->merge(TenagaPendidik::where('jabatan_id', $j->id)->pluck('id'))
                ->unique()->count();

            return array_merge($j->toArray(), [
                'jumlah_guru'         => $jumlahGuru,
                'gaji_pokok'          => $gajiPokok,
                'vakasi_jabatan'      => $vakasiJabatan,
                'total_tetap_per_bulan' => $totalTetap,
                'total_vakasi_tugas'  => 0,
                'tugas'               => $tugas,
                'jumlah_tugas'        => count($tugas),
            ]);
        })->values()->toArray();

        $summary = [
            'total'       => count($jabatan),
            'aktif'       => collect($jabatan)->where('is_aktif', true)->count(),
            'total_tugas' => collect($jabatan)->sum('jumlah_tugas'),
            'tugas_wajib' => 0,
        ];

        return Inertia::render('Admin/Master/Jabatan/Index', [
            'jabatan' => $jabatan,
            'summary' => $summary,
            'penggajianProses' => PeriodePenggajian::sedangProses(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Master/Jabatan/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'kode_jabatan' => 'required|string|max:10|unique:jabatan,kode_jabatan',
            'tipe'         => 'required|in:struktural,fungsional,mengajar',
            'deskripsi'    => 'nullable|string|max:500',
            'wajib_kegiatan' => 'boolean',
        ]);

        Jabatan::create($data);

        return redirect()->route('admin.master.jabatan.index')
            ->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan)
    {
        return Inertia::render('Admin/Master/Jabatan/Form', [
            'jabatan' => $jabatan,
        ]);
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        // Kunci saat penggajian sedang diproses (jabatan berkaitan dengan gaji).
        if (PeriodePenggajian::sedangProses()) {
            return back()->with('error', 'Jabatan tidak bisa diubah saat penggajian sedang diproses. Bisa diubah setelah gaji bulan ini selesai dibayar.');
        }

        $data = $request->validate([
            'nama_jabatan' => 'required|string|max:100',
            'kode_jabatan' => 'required|string|max:10|unique:jabatan,kode_jabatan,' . $jabatan->id,
            'tipe'         => 'required|in:struktural,fungsional,mengajar',
            'deskripsi'    => 'nullable|string|max:500',
            'wajib_kegiatan' => 'boolean',
        ]);

        $jabatan->update($data);

        return redirect()->route('admin.master.jabatan.index')
            ->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Jabatan $jabatan)
    {
        // Kunci saat penggajian sedang diproses.
        if (PeriodePenggajian::sedangProses()) {
            return back()->with('error', 'Jabatan tidak bisa dihapus saat penggajian sedang diproses. Selesaikan pembayaran gaji bulan ini dulu.');
        }

        // Dipakai guru = jabatan UTAMA (jabatan_id) ATAU MULTI-jabatan (pivot jabatan_guru).
        $dipakai = $jabatan->tenagaPendidik()->count()
            + JabatanGuru::where('jabatan_id', $jabatan->id)->count();
        if ($dipakai > 0) {
            return back()->with('error', 'Jabatan tidak bisa dihapus karena masih digunakan oleh guru.');
        }

        $jabatan->delete();

        return redirect()->route('admin.master.jabatan.index')
            ->with('success', 'Jabatan berhasil dihapus.');
    }

    public function toggleStatus(Jabatan $jabatan)
    {
        $jabatan->update(['is_aktif' => !$jabatan->is_aktif]);

        return back()->with('success', 'Status jabatan diperbarui.');
    }

    // ══════════════════════════════════════════════════════════════
    // MULTI JABATAN
    // ══════════════════════════════════════════════════════════════

    /**
     * Halaman overview semua guru & jabatan aktif mereka.
     * Admin bisa klik "Setting Jabatan" untuk kelola jabatan per guru.
     */
    public function multiJabatan(Request $request)
    {
        $search = $request->get('search');

        $guruList = TenagaPendidik::with([
                'user',
                'jabatan',
                'jabatanGuru.jabatan',
            ])
            ->when($search, fn($q) =>
                $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                )->orWhere('nip', 'like', "%{$search}%")
            )
            ->aktif()
            ->orderBy('id')
            ->paginate(20)
            ->through(function ($g) {
                $jabatanAktif = $g->jabatanGuru
                    ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                    ->sortByDesc('adalah_utama');

                return [
                    'id'             => $g->id,
                    'nama'           => $g->user->name,
                    'foto'           => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                    'nip'            => $g->nip,
                    'jabatan_aktif'  => $jabatanAktif->map(fn($jg) => [
                        'pivot_id'     => $jg->id,
                        'nama_jabatan' => $jg->jabatan?->nama_jabatan ?? '—',
                        'kode_jabatan' => $jg->jabatan?->kode_jabatan ?? '',
                        'tipe'         => $jg->jabatan?->tipe ?? '',
                        'adalah_utama' => $jg->adalah_utama,
                    ])->values(),
                    'jabatan_lama'   => $g->jabatan?->nama_jabatan ?? '—',
                    'is_rangkap'     => $jabatanAktif->count() > 1,
                    'jumlah_jabatan' => $jabatanAktif->count(),
                ];
            });

        $stats = [
            'total_guru'    => TenagaPendidik::aktif()->count(),
            'rangkap'       => JabatanGuru::selectRaw('tenaga_pendidik_id, COUNT(*) as jml')
                ->whereNull('berlaku_selesai')
                ->groupBy('tenaga_pendidik_id')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'tanpa_jabatan' => TenagaPendidik::aktif()
                ->whereDoesntHave('jabatanGuru', fn($q) => $q->whereNull('berlaku_selesai'))
                ->whereNull('jabatan_id')
                ->count(),
        ];

        return Inertia::render('Admin/Master/Jabatan/Multi', [
            'guru'    => $guruList,
            'jabatan' => Jabatan::aktif()
                ->orderBy('tipe')->orderBy('nama_jabatan')
                ->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'stats'   => $stats,
            'filters' => ['search' => $search],
        ]);
    }
}