<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PeriodePenggajian;
use App\Models\Penggajian;
use App\Models\TenagaPendidik;
use App\Services\PayrollCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PenggajianController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $payrollService,
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // INDEX — daftar periode sebagai entry point
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $periodes = PeriodePenggajian::withCount('penggajian')
            ->withSum('penggajian', 'gaji_bersih')
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->paginate(12)
            ->through(fn($p) => [
                'id'              => $p->id,
                'nama'            => $p->nama,
                'bulan'           => $p->bulan,
                'tahun'           => $p->tahun,
                'nama_bulan'      => $p->nama_bulan,
                'tanggal_mulai'   => $p->tanggal_mulai?->format('d M Y'),
                'tanggal_selesai' => $p->tanggal_selesai?->format('d M Y'),
                'status'          => $p->status,
                'jumlah_guru'     => $p->penggajian_count,
                'total_gaji'      => $p->penggajian_sum_gaji_bersih ?? 0,
                'dikunci_pada'    => $p->dikunci_pada?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/SmartPayroll/Penggajian/Index', [
            'periodes' => $periodes,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DETAIL — data gaji dalam 1 periode
    // ══════════════════════════════════════════════════════════════════════════

    public function detail(PeriodePenggajian $periode)
    {
        $penggajian = Penggajian::with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'detailPenggajian',
            ])
            ->where('periode_penggajian_id', $periode->id)
            ->orderBy('gaji_bersih', 'desc')
            ->paginate(25)
            ->through(fn($pg) => $this->formatPenggajian($pg));

        $stats = $this->buildStats($periode->id);

        // Guru aktif yang belum di-generate
        $sudahGenerate = Penggajian::where('periode_penggajian_id', $periode->id)
            ->pluck('tenaga_pendidik_id')->toArray();
        $belumGenerate = TenagaPendidik::aktif()
            ->whereNotIn('id', $sudahGenerate)
            ->with(['user', 'jabatan'])
            ->get()
            ->map(fn($g) => [
                'id'      => $g->id,
                'nama'    => $g->user->name,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
            ]);

        return Inertia::render('Admin/SmartPayroll/Penggajian/Detail', [
            'periode'       => $this->formatPeriode($periode),
            'penggajian'    => $penggajian,
            'stats'         => $stats,
            'belumGenerate' => $belumGenerate,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERATE — kalkulasi gaji semua guru
    // ══════════════════════════════════════════════════════════════════════════

    public function generate(PeriodePenggajian $periode)
    {
        if ($periode->status === 'dibayar') {
            return back()->with('error', 'Periode sudah dikunci, tidak bisa di-generate ulang.');
        }

        $guruAktif = TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'jabatanGuru.jabatan', 'gajiPokokIndividu'])
            ->get();

        if ($guruAktif->isEmpty()) {
            return back()->with('error', 'Tidak ada tenaga pendidik aktif yang bisa di-generate.');
        }

        $sukses = 0;
        $gagal  = [];
        $peringatan = [];

        foreach ($guruAktif as $guru) {
            try {
                $hasil = $this->payrollService->hitung($guru, $periode, dryRun: false);

                // Catat peringatan komponen (generate berhasil tapi ada komponen error)
                if (!empty($hasil['_errors'])) {
                    $peringatan[] = [
                        'nama'      => $guru->user->name,
                        'peringatan'=> $hasil['_errors'],
                    ];
                }

                $sukses++;
            } catch (\Throwable $e) {
                $gagal[] = [
                    'nama'   => $guru->user->name,
                    'alasan' => $e->getMessage(),
                ];
                Log::error("Generate gagal [{$guru->id}] {$guru->user->name}: " . $e->getMessage(), [
                    'trace'   => $e->getTraceAsString(),
                    'periode' => $periode->id,
                ]);
            }
        }

        // Build pesan hasil
        $pesan = "Generate selesai: {$sukses} dari {$guruAktif->count()} guru berhasil.";
        $tipe  = 'success';

        if (!empty($gagal)) {
            $namaGagal = collect($gagal)->pluck('nama')->join(', ');
            $pesan .= " Gagal: " . count($gagal) . " guru ({$namaGagal}).";
            $tipe = $sukses === 0 ? 'error' : 'warning';
        }

        if (!empty($peringatan)) {
            $pesan .= " " . count($peringatan) . " guru berhasil tapi ada komponen yang perlu diperhatikan.";
        }

        return redirect()
            ->route('admin.smart-payroll.penggajian.detail', $periode->id)
            ->with($tipe, $pesan)
            ->with('gagal_detail', $gagal)
            ->with('peringatan_detail', $peringatan);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERATE SATU — re-generate 1 guru
    // ══════════════════════════════════════════════════════════════════════════

    public function generateSatu(PeriodePenggajian $periode, TenagaPendidik $guru)
    {
        if ($periode->status === 'dibayar') {
            return back()->with('error', 'Periode sudah dikunci.');
        }

        try {
            $guru->load(['jabatanGuru.jabatan', 'gajiPokokIndividu']);
            $hasil = $this->payrollService->hitung($guru, $periode, dryRun: false);

            $msg = "Gaji {$guru->user->name} berhasil di-generate.";

            if (!empty($hasil['_errors'])) {
                $msg .= " Peringatan: " . implode('; ', $hasil['_errors']);
                return back()->with('warning', $msg)->with('preserveScroll', true);
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error("GenerateSatu gagal [{$guru->id}]: " . $e->getMessage());
            return back()->with('error', "Gagal generate {$guru->user->name}: " . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PREVIEW — dry run untuk 1 guru
    // ══════════════════════════════════════════════════════════════════════════

    public function preview(PeriodePenggajian $periode, Request $request)
    {
        $guruId = $request->get('guru_id');
        $guru   = TenagaPendidik::with(['user', 'jabatan', 'jabatanGuru.jabatan', 'gajiPokokIndividu'])
            ->findOrFail($guruId);

        try {
            $preview = $this->payrollService->hitung($guru, $periode, dryRun: true);
            return response()->json(['success' => true, 'data' => $preview]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'hint'    => 'Cek setting gaji pokok dan vakasi untuk guru ini.',
            ], 422);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FINALISASI & KUNCI
    // ══════════════════════════════════════════════════════════════════════════

    public function finalisasi(Penggajian $penggajian)
    {
        if ($penggajian->status !== 'draft') {
            return back()->with('error', 'Hanya penggajian berstatus draft yang bisa difinalisasi.');
        }

        $penggajian->update([
            'status'            => 'final',
            'difinalisasi_oleh' => Auth::id(),
        ]);

        $this->notifSlipTerbit($penggajian->loadMissing('tenagaPendidik.user'));

        return back()->with('success', "Penggajian {$penggajian->tenagaPendidik->user->name} difinalisasi.");
    }

    public function finalisasiSemua(PeriodePenggajian $periode)
    {
        // Ambil draft (beserta user) SEBELUM di-finalisasi agar bisa dinotifikasi.
        $drafts = Penggajian::with('tenagaPendidik.user')
            ->where('periode_penggajian_id', $periode->id)
            ->where('status', 'draft')->get();

        $jumlah = Penggajian::where('periode_penggajian_id', $periode->id)
            ->where('status', 'draft')
            ->update([
                'status'            => 'final',
                'difinalisasi_oleh' => Auth::id(),
            ]);

        foreach ($drafts as $p) $this->notifSlipTerbit($p);

        return back()->with('success', "{$jumlah} penggajian berhasil difinalisasi.");
    }

    /** Notifikasi 'penggajian.terbit' ke guru pemilik slip (menghormati toggle). */
    private function notifSlipTerbit(Penggajian $penggajian): void
    {
        $user = $penggajian->tenagaPendidik?->user;
        if (!$user) return;
        \App\Services\NotifikasiService::event('penggajian.terbit', [
            'user'  => $user,
            'judul' => 'Slip Gaji Terbit',
            'pesan' => 'Slip gaji Anda telah difinalisasi & bisa dilihat.',
            'tipe'  => 'penggajian',
            'data'  => ['type' => 'penggajian', 'route' => '/slip-gaji', 'penggajian_id' => $penggajian->id],
            'dedup' => "gaji-{$penggajian->id}",
        ]);
    }

    public function override(Penggajian $penggajian, Request $request)
    {
        $data = $request->validate([
            'tunjangan_lainnya' => 'nullable|numeric|min:0',
            'potongan_lainnya'  => 'nullable|numeric|min:0',
            'catatan'           => 'required|string|max:500',
        ]);

        $tunjangan = $data['tunjangan_lainnya'] ?? 0;
        $potongan  = $data['potongan_lainnya']  ?? 0;

        $totalPendapatan = $penggajian->total_pendapatan - $penggajian->tunjangan_lainnya + $tunjangan;
        $totalPotongan   = $penggajian->total_potongan   - $penggajian->potongan_lainnya  + $potongan;

        $penggajian->update([
            'tunjangan_lainnya'  => $tunjangan,
            'potongan_lainnya'   => $potongan,
            'total_pendapatan'   => $totalPendapatan,
            'total_potongan'     => $totalPotongan,
            'gaji_bersih'        => max(0, $totalPendapatan - $totalPotongan),
            'catatan'            => $data['catatan'],
            'ada_koreksi_manual' => true,
        ]);

        return back()->with('success', 'Override gaji berhasil disimpan.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PENYESUAIAN LIBURAN — potongan manual khusus periode liburan (transparan)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Input/ubah potongan liburan manual untuk 1 guru.
     * Fleksibel: admin menentukan nominal potongan + alasan transparan yang
     * tampil di slip. Set nominal=0 untuk menghapus penyesuaian.
     */
    public function penyesuaianLiburan(Penggajian $penggajian, Request $request)
    {
        if ($penggajian->status === 'dibayar') {
            return back()->with('error', 'Penggajian sudah dibayar, tidak bisa diubah.');
        }

        $data = $request->validate([
            'potongan_liburan'   => 'required|numeric|min:0',
            'keterangan_liburan' => 'required_if:potongan_liburan,>,0|nullable|string|max:500',
        ]);

        $potonganBaru = (float) $data['potongan_liburan'];
        $keterangan   = $potonganBaru > 0 ? ($data['keterangan_liburan'] ?? 'Penyesuaian liburan') : null;

        DB::transaction(function () use ($penggajian, $potonganBaru, $keterangan) {
            // Total potongan: ganti porsi liburan lama dengan yang baru
            $totalPotongan = $penggajian->total_potongan
                - (float) $penggajian->potongan_liburan
                + $potonganBaru;

            $penggajian->update([
                'potongan_liburan'   => $potonganBaru,
                'keterangan_liburan' => $keterangan,
                'total_potongan'     => $totalPotongan,
                'gaji_bersih'        => max(0, $penggajian->total_pendapatan - $totalPotongan),
                'ada_koreksi_manual' => true,
            ]);

            // Sinkronkan baris detail (transparansi slip)
            $penggajian->detailPenggajian()
                ->where('tipe', 'penyesuaian_liburan')->delete();

            if ($potonganBaru > 0) {
                $penggajian->detailPenggajian()->create([
                    'tipe'             => 'penyesuaian_liburan',
                    'keterangan'       => $keterangan,
                    'jumlah_satuan'    => 1,
                    'satuan'           => 'periode',
                    'nilai_per_satuan' => $potonganBaru,
                    'subtotal'         => -$potonganBaru,
                    'referensi_ids'    => [],
                ]);
            }
        });

        $msg = $potonganBaru > 0
            ? 'Penyesuaian liburan ' . $this->rupiah($potonganBaru) . ' berhasil disimpan.'
            : 'Penyesuaian liburan dihapus.';

        return back()->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SLIP GAJI
    // ══════════════════════════════════════════════════════════════════════════

    public function slip(Penggajian $penggajian)
    {
        // Pakai builder yang sama dgn Laporan agar desain & data slip konsisten.
        return Inertia::render(
            'Admin/SmartPayroll/Penggajian/Slip',
            \App\Services\SlipGajiBuilder::build($penggajian)
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function formatPeriode(PeriodePenggajian $p): array
    {
        return [
            'id'              => $p->id,
            'nama'            => $p->nama,
            'bulan'           => $p->bulan,
            'tahun'           => $p->tahun,
            'nama_bulan'      => $p->nama_bulan,
            'tanggal_mulai'   => $p->tanggal_mulai?->format('d M Y'),
            'tanggal_selesai' => $p->tanggal_selesai?->format('d M Y'),
            'status'          => $p->status,
            'dikunci_pada'    => $p->dikunci_pada?->format('d M Y H:i'),
        ];
    }

    private function formatPenggajian(Penggajian $pg): array
    {
        $guru = $pg->tenagaPendidik;
        return [
            'id'                     => $pg->id,
            'nama'                   => $guru->user->name,
            'foto'                   => $guru->user->foto ? asset('storage/'.$guru->user->foto) : null,
            'jabatan'                => $guru->jabatan?->nama_jabatan ?? '—',
            'nip'                    => $guru->nip,
            'status'                 => $pg->status,
            'status_badge'           => $pg->status_badge,
            'gaji_pokok'             => $pg->gaji_pokok,
            'vakasi_absen_harian'    => $pg->vakasi_absen_harian,
            'vakasi_mengajar'        => $pg->vakasi_mengajar,
            'vakasi_tugas_jabatan'   => $pg->vakasi_tugas_jabatan,
            'vakasi_tugas_tambahan'  => $pg->vakasi_tugas_tambahan,
            'tunjangan_lainnya'      => $pg->tunjangan_lainnya,
            'potongan_keterlambatan' => $pg->potongan_keterlambatan,
            'potongan_alfa'          => $pg->potongan_alfa,
            'potongan_tetap'         => $pg->potongan_tetap,
            'potongan_lainnya'       => $pg->potongan_lainnya,
            'potongan_liburan'       => $pg->potongan_liburan,
            'keterangan_liburan'     => $pg->keterangan_liburan,
            'total_pendapatan'       => $pg->total_pendapatan,
            'total_potongan'         => $pg->total_potongan,
            'gaji_bersih'            => $pg->gaji_bersih,
            'total_hadir'            => $pg->total_hadir,
            'total_alfa'             => $pg->total_alfa,
            'total_izin'             => $pg->total_izin,
            'total_sakit'            => $pg->total_sakit,
            'total_terlambat'        => $pg->total_terlambat,
            'total_jp_mengajar'      => $pg->total_jp_mengajar,
            'ada_koreksi_manual'     => $pg->ada_koreksi_manual,
            'catatan'                => $pg->catatan,
            'dibayar_pada'           => $pg->dibayar_pada?->format('d M Y'),
        ];
    }

    private function rupiah(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    private function buildStats(int $periodeId): array
    {
        $q = Penggajian::where('periode_penggajian_id', $periodeId);
        return [
            'total_guru'         => (clone $q)->count(),
            'total_draft'        => (clone $q)->where('status', 'draft')->count(),
            'total_final'        => (clone $q)->where('status', 'final')->count(),
            'total_dibayar'      => (clone $q)->where('status', 'dibayar')->count(),
            'grand_total_bersih' => (clone $q)->sum('gaji_bersih'),
            'grand_total_kotor'  => (clone $q)->sum('total_pendapatan'),
            'total_potongan'     => (clone $q)->sum('total_potongan'),
        ];
    }
}