<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PeriodePenggajian;
use App\Models\Penggajian;
use App\Models\TenagaPendidik;
use App\Services\KoreksiSlipService;
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
        // Satu pintu bersama layar Gaji Pokok: koreksi manual hanya boleh
        // ditulis & dipertahankan dari satu tempat.
        private readonly KoreksiSlipService $koreksiSlip,
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
            // Penanda tangan slip: ditampilkan & bisa diubah dari halaman ini.
            'ttd' => (function () {
                $t = \App\Models\SettingSlipGaji::ambil();
                return [
                    'nama_tersimpan' => $t->nama_bendahara,
                    'nama_tercetak'  => $t->namaTercetak(),
                    'jabatan'        => $t->jabatan_penanda_tangan,
                    'gambar'         => $t->ttdUrl(),
                    'otomatis'       => blank($t->nama_bendahara),
                ];
            })(),
            'periodes' => $periodes,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DETAIL — data gaji dalam 1 periode
    // ══════════════════════════════════════════════════════════════════════════

    public function detail(PeriodePenggajian $periode, Request $request)
    {
        // Pencarian & jumlah baris: dulu terkunci 25/halaman TANPA navigasi apa pun
        // di layar, sehingga guru ke-26 dan seterusnya tidak pernah bisa dilihat.
        $cari    = trim((string) $request->get('cari', ''));
        $perHal  = (int) $request->get('per_halaman', 25);
        $perHal  = in_array($perHal, [25, 50, 100, 500], true) ? $perHal : 25;

        $penggajian = Penggajian::with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'detailPenggajian',
            ])
            ->where('periode_penggajian_id', $periode->id)
            ->when($cari !== '', fn ($q) => $q->whereHas('tenagaPendidik', fn ($tp) => $tp
                ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$cari}%"))
                ->orWhereHas('jabatan', fn ($j) => $j->where('nama_jabatan', 'like', "%{$cari}%"))
                ->orWhere('nip', 'like', "%{$cari}%")))
            ->orderBy('gaji_bersih', 'desc')
            ->paginate($perHal)
            ->withQueryString()
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
            'filter'        => ['cari' => $cari, 'per_halaman' => $perHal],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERATE — kalkulasi gaji semua guru
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Penanda tangan slip gaji: nama dan gambar tanda tangannya.
     *
     * Nama boleh DIBIARKAN KOSONG — bila kosong, slip memakai pemegang jabatan
     * penanda tangan yang sedang aktif, sehingga pergantian bendahara di data
     * kepegawaian otomatis tercermin tanpa ada yang perlu ingat menyunting
     * halaman ini. Diisi hanya bila memang ingin menimpa.
     */
    public function simpanTtd(Request $request)
    {
        $data = $request->validate([
            'nama_bendahara'         => 'nullable|string|max:150',
            'jabatan_penanda_tangan' => 'nullable|string|max:100',
            // PNG saja: tanda tangan perlu latar transparan agar menyatu dengan
            // slip; JPG akan mencetak kotak putih di atas garisnya.
            'ttd'                    => 'nullable|file|mimes:png|max:2048',
            'hapus_ttd'              => 'nullable|boolean',
        ], [
            'ttd.mimes' => 'Berkas tanda tangan harus PNG (agar latarnya transparan).',
            'ttd.max'   => 'Berkas tanda tangan maksimal 2 MB.',
        ], ['nama_bendahara' => 'nama penanda tangan']);

        $setting = \App\Models\SettingSlipGaji::ambil();
        $lama    = $setting->ttd_path;

        $isi = [
            'nama_bendahara'         => filled($data['nama_bendahara'] ?? null)
                ? trim($data['nama_bendahara']) : null,
            'jabatan_penanda_tangan' => filled($data['jabatan_penanda_tangan'] ?? null)
                ? trim($data['jabatan_penanda_tangan']) : 'Bendahara',
            'ditetapkan_oleh'        => Auth::id(),
        ];

        if ($request->boolean('hapus_ttd')) {
            $isi['ttd_path'] = null;
        } elseif ($request->hasFile('ttd')) {
            // Nama berkas dibubuhi waktu agar peramban tidak menyajikan
            // gambar lama dari singgahannya setelah diganti.
            $nama = 'ttd-bendahara-' . now()->format('YmdHis') . '.png';
            $isi['ttd_path'] = $request->file('ttd')->storeAs('ttd', $nama, 'public');
        }

        $setting->update($isi);

        // Berkas lama dibuang HANYA setelah yang baru tersimpan, supaya tidak
        // pernah ada keadaan slip menunjuk berkas yang sudah hilang.
        if (array_key_exists('ttd_path', $isi) && $lama && $lama !== $setting->ttd_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($lama);
        }

        $nama = $setting->fresh()->namaTercetak();
        $pesan = 'Penanda tangan slip disimpan: ' . ($nama ?: '(belum ada nama)');
        if (blank($setting->nama_bendahara) && $nama) {
            $pesan .= ' — diambil otomatis dari pemegang jabatan '
                . $setting->jabatan_penanda_tangan . '.';
        }
        if ($request->boolean('hapus_ttd')) {
            $pesan .= ' Gambar tanda tangan dihapus.';
        } elseif ($request->hasFile('ttd')) {
            $pesan .= ' Gambar tanda tangan diperbarui.';
        }

        return back()->with('success', $pesan);
    }

    public function generate(PeriodePenggajian $periode)
    {
        if ($periode->status === 'dibayar') {
            return back()->with('error', 'Periode sudah dikunci, tidak bisa di-generate ulang.');
        }

        // Guru aktif SAJA tidak cukup. Potongan & gaji pokok tetap mengalir
        // untuk guru yang statusnya cuti/nonaktif selama slipnya sudah terbit,
        // tapi loop `aktif()` tidak pernah menyentuh slip itu — jadi setting
        // yang diubah tidak pernah sampai ke slipnya. Terukur: Widiyanti
        // (cuti_sakit) settingnya Rp 500.000 sementara slipnya mencetak
        // Rp 300.000, dan tak ada tombol massal yang bisa menyusulkannya.
        // Yang ikut digenerate = guru aktif ∪ pemilik slip periode ini.
        $slipTerbit = Penggajian::where('periode_penggajian_id', $periode->id)
            ->pluck('tenaga_pendidik_id');

        $guruAktif = TenagaPendidik::query()
            ->where(fn ($q) => $q->whereIn('id', TenagaPendidik::aktif()->select('id'))
                ->orWhereIn('id', $slipTerbit))
            ->with(['user', 'jabatan', 'jabatanGuru.jabatan', 'gajiPokokIndividu'])
            ->get();

        if ($guruAktif->isEmpty()) {
            return back()->with('error', 'Tidak ada tenaga pendidik aktif yang bisa di-generate.');
        }

        $sukses = 0;
        $gagal  = [];
        $peringatan = [];

        // Slip yang sudah difinalisasi/dibayar DILEWATI dengan laporan, bukan
        // dicoba lalu gagal: uang yang sudah keluar bukan kegagalan teknis.
        // Pagar kerasnya ada di PayrollCalculationService (titik tulis), ini
        // supaya pesannya jujur dan tidak terbaca seperti error.
        $dilewatiFinal = Penggajian::where('periode_penggajian_id', $periode->id)
            ->where(fn ($q) => $q->whereNotNull('difinalisasi_oleh')->orWhereNotNull('dibayar_pada'))
            ->pluck('tenaga_pendidik_id')->all();

        // Celah KEDUA: pagar di atas menjaga slip final yang SUDAH ada, tapi
        // tidak menahan slip BARU diterbitkan di bulan yang sudah final.
        // Terukur: Generate di September 2026 (51 slip final) menerbitkan 2
        // slip baru untuk guru yang masuk belakangan dan menambah
        // Rp 883.000 pada bulan yang gajinya sudah dibagikan.
        // Bila periodenya sudah punya slip final, Generate massal hanya boleh
        // menghitung ulang yang sudah ada. Guru yang benar-benar terlewat tetap
        // bisa diterbitkan satu per satu lewat tombol per baris — di situ admin
        // memutuskannya secara sadar.
        $punyaSlip = Penggajian::where('periode_penggajian_id', $periode->id)
            ->pluck('tenaga_pendidik_id')->all();
        $adaYangFinal = count($dilewatiFinal) > 0;
        $tanpaSlip = [];

        // Koreksi manual dibaca SEBELUM generate. hitung() menulis seluruh slip
        // dari nol (termasuk catatan => null), jadi tanpa ini satu klik Generate
        // melenyapkan override yang diinput tangan tanpa jejak — persis yang
        // terjadi pada 31 Okt 2026: 50 slip tertimpa dalam 4 detik dan potongan
        // "Simpan Pinjam Angsuran Ke-2" Rp 250.000 hilang.
        $koreksiLama = $this->koreksiSlip->bacaKoreksi($periode);
        $dipertahankan = [];

        foreach ($guruAktif as $guru) {
            if (in_array($guru->id, $dilewatiFinal, true)) continue;

            if ($adaYangFinal && !in_array($guru->id, $punyaSlip, true)) {
                $tanpaSlip[] = $guru->user->name;
                continue;
            }

            try {
                $hasil = $this->payrollService->hitung($guru, $periode, dryRun: false);

                if (isset($koreksiLama[$guru->id])) {
                    $k    = $koreksiLama[$guru->id];
                    $slip = Penggajian::where('periode_penggajian_id', $periode->id)
                        ->where('tenaga_pendidik_id', $guru->id)->first();
                    if ($slip) {
                        $this->koreksiSlip->terapkanKoreksi($slip, $k['tunjangan'],
                            $k['potongan'], $k['catatan']);
                        $dipertahankan[] = $guru->user->name;
                    }
                }

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
        $dicoba = $guruAktif->count() - count($dilewatiFinal) - count($tanpaSlip);
        $pesan = "Generate selesai: {$sukses} dari {$dicoba} guru berhasil.";

        if ($dilewatiFinal) {
            $pesan .= ' ' . count($dilewatiFinal)
                . ' slip dilewati karena sudah difinalisasi/dibayar.';
        }

        if ($tanpaSlip) {
            $pesan .= ' ' . count($tanpaSlip) . ' guru belum berslip di periode ini TIDAK diterbitkan'
                . ' karena periodenya sudah difinalisasi (' . implode(', ', $tanpaSlip)
                . ') — terbitkan satu per satu bila memang berhak.';
        }
        $tipe  = 'success';

        if (!empty($gagal)) {
            $namaGagal = collect($gagal)->pluck('nama')->join(', ');
            $pesan .= " Gagal: " . count($gagal) . " guru ({$namaGagal}).";
            $tipe = $sukses === 0 ? 'error' : 'warning';
        }

        if (!empty($peringatan)) {
            $pesan .= " " . count($peringatan) . " guru berhasil tapi ada komponen yang perlu diperhatikan.";
        }

        if (!empty($dipertahankan)) {
            $pesan .= " Koreksi manual dipertahankan untuk " . count($dipertahankan)
                . " guru (" . implode(', ', $dipertahankan) . ") — periksa bila nominalnya sudah tidak berlaku.";
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

        $slipAda = Penggajian::where('periode_penggajian_id', $periode->id)
            ->where('tenaga_pendidik_id', $guru->id)->first();

        if ($slipAda && ($slipAda->difinalisasi_oleh || $slipAda->dibayar_pada)) {
            return back()->with('error', "Slip {$guru->user->name} sudah "
                . ($slipAda->dibayar_pada ? 'dibayar' : 'difinalisasi')
                . ', tidak bisa di-generate ulang. Buka finalisasinya dulu bila perlu diperbaiki.');
        }

        try {
            $guru->load(['jabatanGuru.jabatan', 'gajiPokokIndividu']);

            $koreksi = $this->koreksiSlip->bacaKoreksi($periode)[$guru->id] ?? null;

            $hasil = $this->payrollService->hitung($guru, $periode, dryRun: false);

            $msg = "Gaji {$guru->user->name} berhasil di-generate.";

            if ($koreksi) {
                $slip = Penggajian::where('periode_penggajian_id', $periode->id)
                    ->where('tenaga_pendidik_id', $guru->id)->first();
                if ($slip) {
                    $this->koreksiSlip->terapkanKoreksi($slip, $koreksi['tunjangan'],
                        $koreksi['potongan'], $koreksi['catatan']);
                    $msg .= " Koreksi manual (" . ($koreksi['catatan'] ?: 'tanpa catatan')
                        . ") dipertahankan.";
                }
            }

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

        // Kinerja guru ini untuk periode tsb dibekukan: dihitung final lalu
        // dikunci, nilai lamanya tersimpan di riwayat. Supaya skor yang menjadi
        // dasar slip tidak berubah lagi setelah slipnya terbit.
        $this->bekukanKinerja($penggajian);

        return back()->with('success', "Penggajian {$penggajian->tenagaPendidik->user->name} difinalisasi. Kinerja periode ini dibekukan.");
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

        // Bekukan kinerja SELURUH periode sekali jalan (hitung final + kunci).
        $dibekukan = app(\App\Services\KinerjaCalculationService::class)
            ->finalisasiPeriode($periode, Auth::id());

        return back()->with('success',
            "{$jumlah} penggajian berhasil difinalisasi. Kinerja {$dibekukan} guru dibekukan & tersimpan di riwayat.");
    }

    /**
     * Bekukan kinerja satu guru untuk periode slip ini: hitung final lalu kunci.
     * Nilai lama tersimpan di riwayat lewat observer (sebab 'finalisasi').
     */
    private function bekukanKinerja(Penggajian $penggajian): void
    {
        $periode = $penggajian->periodePenggajian;
        $guru    = $penggajian->tenagaPendidik;
        if (!$periode || !$guru) return;

        $svc    = app(\App\Services\KinerjaCalculationService::class);
        $alasan = 'Finalisasi penggajian ' . $periode->nama_bulan;

        \App\Models\RekapKinerjaBulanan::tandaiPerubahan('finalisasi', $alasan, Auth::id());
        $rekap = $svc->hitungRekap($guru, (int) $periode->bulan, (int) $periode->tahun);

        if ($rekap->exists && !$rekap->sudah_dikunci) {
            \App\Models\RekapKinerjaBulanan::tandaiPerubahan('finalisasi', $alasan, Auth::id());
            $rekap->update(['sudah_dikunci' => true]);
        }
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

        $this->koreksiSlip->terapkanKoreksi(
            $penggajian,
            (float) ($data['tunjangan_lainnya'] ?? 0),
            (float) ($data['potongan_lainnya'] ?? 0),
            $data['catatan'],
        );

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
            // WAJIB dikirim: tombol "Generate ulang" per baris dulu memakai
            // `id` (id SLIP) sebagai id guru, padahal rutenya mengikat
            // TenagaPendidik. Terukur pada periode Oktober: 6 dari 53 baris
            // mengenai GURU LAIN (baris Widiyanti slip#53 men-generate
            // Miqdad Muhammad tp#53) dan 47 baris 404 senyap karena id slip
            // 44..104 melewati id guru yang hanya sampai 57.
            'tenaga_pendidik_id'     => $pg->tenaga_pendidik_id,
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
            'vakasi_piket'           => $pg->vakasi_piket,
            'vakasi_ekstrakurikuler' => $pg->vakasi_ekstrakurikuler,
            'vakasi_peserta_kegiatan'=> $pg->vakasi_peserta_kegiatan,
            'vakasi_lembur'          => $pg->vakasi_lembur,
            'potongan_lainnya'       => $pg->potongan_lainnya,
            'potongan_guru'          => $pg->potongan_guru,
            'potongan_tidak_terbayar'=> $pg->potongan_tidak_terbayar,
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
            // Tunjangan/vakasi = pendapatan di luar gaji pokok. Dulu footer tabel
            // menghitungnya dari baris yang TAMPIL saja, jadi ikut berubah tiap
            // pindah halaman dan tak pernah cocok dengan total periode.
            'grand_total_tunjangan' => (clone $q)->sum('total_pendapatan') - (clone $q)->sum('gaji_pokok'),
            'total_potongan'     => (clone $q)->sum('total_potongan'),
            'total_tidak_terbayar' => (clone $q)->sum('potongan_tidak_terbayar'),
        ];
    }
}