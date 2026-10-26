<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TugasTambahan;
use App\Models\PenugasanTambahan;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\SettingVakasi;
use App\Models\JabatanGuru;
use App\Services\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TugasTambahanController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->input('status', '');
        $q      = trim((string) $request->input('q', ''));

        // ── Saringan PERIODE / rentang tanggal ──────────────────────────────
        // Tugas tambahan merentang tanggal, jadi "milik periode mana" tidak bisa
        // dijawab dengan satu tanggal. Aturan yang dipakai di sini SAMA dengan
        // yang dipakai penilaian kinerja (KinerjaCalculationService::komponenTugas):
        // sebuah tugas masuk periode bila rentangnya BERSINGGUNGAN dengan jendela
        // periode — mulai <= akhir jendela DAN (tanpa akhir ATAU akhir >= awal
        // jendela). Dengan begitu daftar di layar ini persis tugas yang menentukan
        // skor kinerja periode itu, bukan daftar yang mirip-mirip.
        [$dari, $sampai, $periodeDipakai] = $this->rentangFilter($request);

        $tugas = TugasTambahan::with(['dibuatOleh', 'settingVakasi'])
            ->withCount([
                'penugasan',
                'penugasan as selesai_count' => fn($q) => $q->where('status_pengerjaan', 'selesai'),
                'penugasan as disetujui_count' => fn($q) => $q->where('disetujui', true),
                'penugasan as menunggu_count' => fn($q) => $q->where('status_pengerjaan', 'selesai')->whereNull('disetujui'),
            ])
            // Filter status (atau "perlu_verifikasi": tugas yang punya penugasan menunggu).
            ->when(in_array($status, ['draft', 'aktif', 'selesai', 'dibatalkan'], true),
                fn($qq) => $qq->where('status', $status))
            ->when($status === 'perlu_verifikasi',
                fn($qq) => $qq->whereHas('penugasan', fn($p) => $p->menungguVerifikasi()))
            ->when($q !== '', fn($qq) => $qq->where('judul', 'like', "%{$q}%"))
            ->when($dari && $sampai, fn($qq) => $qq->where('tanggal_mulai', '<=', $sampai)
                ->where(fn($w) => $w->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $dari)))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($t) => [
                'id'               => $t->id,
                'judul'            => $t->judul,
                'deskripsi'        => $t->deskripsi,
                'tipe'             => $t->tipe,
                'status'           => $t->status,
                'tanggal_mulai'    => $t->tanggal_mulai?->format('d M Y'),
                'tanggal_selesai'  => $t->tanggal_selesai?->format('d M Y'),
                'nominal_vakasi'   => $t->getNominalVakasi(),
                'sumber_vakasi'    => $t->setting_vakasi_id ? 'setting' : ($t->vakasi_override !== null ? 'override' : 'tidak_ada'),
                'setting_vakasi'   => $t->settingVakasi?->nama,
                'total_penerima'   => $t->penugasan_count,
                'selesai'          => $t->selesai_count,
                'disetujui'        => $t->disetujui_count,
                'menunggu'         => $t->menunggu_count,
                'dibuat_oleh'      => $t->dibuatOleh?->name,
                'dibuat_pada'      => $t->created_at?->format('d M Y'),
            ]);

        // Estimasi vakasi = Σ (nominal vakasi × penerima disetujui) seluruh tugas.
        $vakasiTotal = TugasTambahan::with('settingVakasi')
            ->withCount(['penugasan as disetujui_count' => fn($q) => $q->where('disetujui', true)])
            ->get()->sum(fn($t) => (float) $t->getNominalVakasi() * $t->disetujui_count);

        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Index', [
            'tugas'   => $tugas,
            'filters' => [
                'status'  => $status,
                'q'       => $q,
                'periode' => $periodeDipakai?->id,
                'dari'    => $dari,
                'sampai'  => $sampai,
            ],
            'periodeOpsi' => $this->opsiPeriode(),
            'rentang'     => $dari && $sampai
                ? $this->ringkasanRentang($dari, $sampai, $periodeDipakai)
                : null,
            'summary' => [
                'total'    => TugasTambahan::count(),
                'aktif'    => TugasTambahan::where('status', 'aktif')->count(),
                'selesai'  => TugasTambahan::where('status', 'selesai')->count(),
                'menunggu' => PenugasanTambahan::menungguVerifikasi()->count(),
                'vakasi'   => $vakasiTotal,
            ],
        ]);
    }

    /**
     * Tentukan jendela tanggal dari permintaan: pilihan periode gaji menang atas
     * tanggal manual, supaya satu klik "Okt 2026" selalu berarti 26 Sep–25 Okt
     * dan tidak pernah tertukar dengan 1–31 Oktober.
     *
     * @return array{0: ?string, 1: ?string, 2: ?\App\Models\PeriodePenggajian}
     */
    private function rentangFilter(Request $request): array
    {
        $periode = $request->filled('periode')
            ? \App\Models\PeriodePenggajian::find($request->input('periode'))
            : null;

        if ($periode?->tanggal_mulai && $periode->tanggal_selesai) {
            return [
                $periode->tanggal_mulai->toDateString(),
                $periode->tanggal_selesai->toDateString(),
                $periode,
            ];
        }

        $dari   = $request->input('dari');
        $sampai = $request->input('sampai');

        // Separuh rentang tidak berguna — keduanya harus ada, kalau tidak diabaikan.
        if (!$dari || !$sampai) return [null, null, null];

        try {
            return [
                \Carbon\Carbon::parse($dari)->toDateString(),
                \Carbon\Carbon::parse($sampai)->toDateString(),
                null,
            ];
        } catch (\Throwable) {
            return [null, null, null];
        }
    }

    /** Periode gaji terakhir sebagai pilihan cepat (terbaru di atas). */
    private function opsiPeriode(): array
    {
        return \App\Models\PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')
            ->limit(12)->get()
            ->filter(fn ($p) => $p->tanggal_mulai && $p->tanggal_selesai)
            ->map(fn ($p) => [
                'id'     => $p->id,
                'label'  => $p->nama_bulan,
                'dari'   => $p->tanggal_mulai->format('d M Y'),
                'sampai' => $p->tanggal_selesai->format('d M Y'),
            ])->values()->all();
    }

    /**
     * Ringkasan penugasan DI DALAM jendela — ini yang menjawab "siapa yang
     * belum beres di periode ini", bukan angka global di kartu atas.
     */
    private function ringkasanRentang(string $dari, string $sampai, $periode): array
    {
        $penugasan = PenugasanTambahan::with('tugasTambahan.settingVakasi')
            ->whereHas('tugasTambahan', fn ($q) => $q->where('tanggal_mulai', '<=', $sampai)
                ->where(fn ($w) => $w->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $dari)))
            ->get();

        $beres = $penugasan->where('status_pengerjaan', 'selesai')->where('disetujui', true);

        return [
            'label'          => $periode?->nama_bulan,
            'dari'           => \Carbon\Carbon::parse($dari)->format('d M Y'),
            'sampai'         => \Carbon\Carbon::parse($sampai)->format('d M Y'),
            'tugas'          => $penugasan->pluck('tugas_tambahan_id')->unique()->count(),
            'penugasan'      => $penugasan->count(),
            'beres'          => $beres->count(),
            'menunggu'       => $penugasan->where('status_pengerjaan', 'selesai')
                                    ->where('disetujui', false)->count(),
            'belum'          => $penugasan->whereIn('status_pengerjaan', ['belum', 'sedang'])->count(),
            'tidak_selesai'  => $penugasan->where('status_pengerjaan', 'tidak_selesai')->count(),
            // Orthogonal terhadap status: dihitung terpisah, bukan dikurangkan.
            'dikecualikan'   => $penugasan->where('dikecualikan_penilaian', true)->count(),
            // Vakasi yang benar-benar menempel pada jendela ini, bukan total global.
            'vakasi'         => (float) $beres->sum(
                fn ($p) => app(\App\Services\VakasiTugasService::class)->untukPenerima($p)['nominal']
            ),
        ];
    }

    /**
     * Jadikan SELURUH tugas tambahan satu periode gaji TANPA VAKASI.
     *
     * Keputusan tingkat periode, bukan per tugas: dipakai saat lembaga
     * memutuskan periode itu tidak memberi vakasi tugas tambahan. Penjagaannya
     * ada di VakasiTugasService::nolkanPeriode — menolak bila ada slip periode
     * itu yang sudah difinalisasi atau dibayar, dan menolak periode terkunci.
     *
     * `simulasi=1` hanya melaporkan apa yang akan berubah, tanpa menulis.
     */
    public function nolkanVakasiPeriode(Request $request, \App\Services\VakasiTugasService $svc)
    {
        $data = $request->validate([
            'periode'  => 'required|integer|exists:periode_penggajian,id',
            'alasan'   => 'required|string|min:5|max:300',
            'simulasi' => 'nullable|boolean',
        ], [], ['alasan' => 'alasan penetapan']);

        $periode = \App\Models\PeriodePenggajian::findOrFail($data['periode']);

        try {
            $hasil = $svc->nolkanPeriode(
                $periode,
                $data['alasan'],
                $request->user()?->id,
                (bool) ($data['simulasi'] ?? false),
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (!empty($data['simulasi'])) {
            return back()->with('success', sprintf(
                'Simulasi: %d tugas & %d penerima akan jadi tanpa vakasi (Rp %s dicabut). Belum ada yang diubah.',
                $hasil['tugas'], $hasil['penugasan'],
                number_format($hasil['nominal_dicabut'], 0, ',', '.')
            ));
        }

        if ($hasil['tugas'] === 0 && $hasil['penugasan'] === 0) {
            return back()->with('success',
                'Tidak ada yang perlu diubah: seluruh tugas periode ' . $periode->nama_bulan
                . ' sudah tanpa vakasi.');
        }

        $pesan = sprintf(
            'Periode %s jadi tanpa vakasi: %d tugas & %d penerima dinolkan (Rp %s dicabut).',
            $periode->nama_bulan, $hasil['tugas'], $hasil['penugasan'],
            number_format($hasil['nominal_dicabut'], 0, ',', '.')
        );
        if ($hasil['perlu_generate_ulang']) {
            $pesan .= ' Slip periode ini sudah ada dan angkanya SNAPSHOT — generate ulang '
                . 'penggajian periode itu agar slipnya ikut berubah.';
        }

        return back()->with('success', $pesan);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Form', [
            'guru'    => $this->getGuruList(),
            'jabatan' => Jabatan::aktif()->orderBy('tipe')->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'vakasi'  => $this->getVakasiList(),
        ]);
    }

    public function store(Request $request)
    {
        // Sanitasi: empty string dari form → null agar FK dan cast tidak error
        $request->merge([
            'setting_vakasi_id' => $request->input('setting_vakasi_id') !== '' ? $request->input('setting_vakasi_id') : null,
            'vakasi_override'   => $request->input('vakasi_override') !== '' ? $request->input('vakasi_override') : null,
            'tanggal_selesai'   => $request->input('tanggal_selesai') !== '' ? $request->input('tanggal_selesai') : null,
        ]);

        $data = $request->validate([
            'judul'               => 'required|string|max:200',
            'deskripsi'           => 'nullable|string',
            'tanggal_mulai'       => 'required|date',
            'tanggal_selesai'     => 'nullable|date|after_or_equal:tanggal_mulai',
            'tipe'                => 'required|in:individu,jabatan,semua',
            'tipe_pengerjaan'     => 'required|in:mandiri,absen_kegiatan',
            'setting_vakasi_id'   => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'     => 'nullable|numeric|min:0',
            'wajib_laporan'       => 'boolean',
            // Penerima
            'penerima_ids'        => 'nullable|array',
            'penerima_ids.*'      => 'integer',
            // Vakasi override per penerima (opsional)
            'vakasi_per_penerima' => 'nullable|array',
        ]);

        $tugas = TugasTambahan::create([
            'judul'             => $data['judul'],
            'deskripsi'         => $data['deskripsi'] ?? null,
            'tanggal_mulai'     => $data['tanggal_mulai'],
            'tanggal_selesai'   => $data['tanggal_selesai'] ?? null,
            'tipe'              => $data['tipe'],
            'tipe_pengerjaan'   => $data['tipe_pengerjaan'] ?? 'mandiri',
            'setting_vakasi_id' => $data['setting_vakasi_id'] ?? null,
            'vakasi_override'   => $data['vakasi_override'] ?? null,
            'wajib_laporan'     => $data['wajib_laporan'] ?? false,
            'status'            => 'aktif',
            'dibuat_oleh'       => auth()->id(),
        ]);

        // Tentukan penerima berdasarkan tipe
        $guruIds = match ($data['tipe']) {
            'semua'    => TenagaPendidik::aktif()->pluck('id'),
            'jabatan'  => $this->getGuruIdsByJabatan($data['penerima_ids'] ?? []),
            'individu' => collect($data['penerima_ids'] ?? []),
        };

        // Buat penugasan per penerima
        $vakasiPerPenerima = $request->vakasi_per_penerima ?? [];
        foreach ($guruIds as $guruId) {
            $vakasiInfo = $vakasiPerPenerima[$guruId] ?? [];
            PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tugas->id,
                'tenaga_pendidik_id' => $guruId,
                'status_pengerjaan'  => 'belum',
                'setting_vakasi_id'  => $vakasiInfo['setting_vakasi_id'] ?? null,
                'vakasi_override'    => $vakasiInfo['vakasi_override'] ?? null,
            ]);
        }

        // Notifikasi 'tugas.baru' ke tiap penerima (menghormati toggle Setting Notifikasi).
        $penerima = TenagaPendidik::with('user')->whereIn('id', $guruIds)->get();
        foreach ($penerima as $tp) {
            if (!$tp->user) continue;
            \App\Services\NotifikasiService::event('tugas.baru', [
                'user'  => $tp->user,
                'judul' => 'Tugas Baru',
                'pesan' => 'Anda mendapat tugas: ' . $tugas->judul
                    . ($tugas->tanggal_selesai ? ' (deadline ' . \Carbon\Carbon::parse($tugas->tanggal_selesai)->format('d/m/Y') . ')' : ''),
                'tipe'  => 'tugas_baru',
                'data'  => ['type' => 'tugas', 'route' => '/tugas', 'tugas_id' => $tugas->id],
                'dedup' => "tugas-{$tugas->id}",
            ]);
        }

        return redirect()->route('admin.smart-payroll.tugas-tambahan.index')
            ->with('success', "Tugas dibuat dan dikirim ke {$guruIds->count()} orang.");
    }

    public function show(TugasTambahan $tugasTambahan, \App\Services\VakasiTugasService $vakasiSvc)
    {
        $penugasan = $tugasTambahan->penugasan()
            ->with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'settingVakasi',
                'diverifikasiOleh',
                'kegiatanAbsensi',
            ])
            ->orderBy('status_pengerjaan')
            ->get()
            ->map(fn($p) => [
                'id'                  => $p->id,
                'guru_id'             => $p->tenaga_pendidik_id,
                'guru_nama'           => $p->tenagaPendidik->user->name,
                'guru_foto'           => $p->tenagaPendidik->user->foto
                    ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
                'guru_jabatan'        => $p->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'status_pengerjaan'   => $p->status_pengerjaan,
                'disetujui'           => $p->disetujui,
                'laporan'             => $p->laporan,
                'dikerjakan_pada'     => $p->dikerjakan_pada?->format('d M Y H:i'),
                'catatan_verifikasi'  => $p->catatan_verifikasi,
                // Vakasi per penerima — nominal, ASAL-USULNYA, dan apakah masih
                // bisa bergeser sendiri. Dulu hanya angka: admin tidak bisa tahu
                // apakah Rp 0 itu keputusan atau lubang fallback, dan tidak tahu
                // kalau nominalnya masih mengikuti setting yang bisa diedit.
                'vakasi'              => $vakasiSvc->untukPenerima($p),
                'vakasi_terkunci_slip' => $vakasiSvc->alasanTidakBolehUbah($p),
                'nominal_vakasi'      => $p->getNominalVakasi(),
                'sumber_vakasi'       => $p->sumber_vakasi,
                // Status tenggat: dasar keputusan admin (tidak terlaksana / perpanjang)
                'batas_pengisian'     => $p->batasPengisian()?->toDateString(),
                'lewat_tenggat'       => $p->lewatTenggat(),
                'bisa_diisi'          => $p->bisaDiisi(),
                'tenggat_perpanjangan'=> $p->tenggat_perpanjangan?->toDateString(),
                'alasan_perpanjangan' => $p->alasan_perpanjangan,
                'diputuskan_pada'     => $p->diputuskan_pada?->format('d M Y H:i'),
                // Pengecualian penilaian harus KELIHATAN, kalau tidak keputusannya
                // tak terlacak dari layar dan hanya ada di database.
                'dikecualikan'        => (bool) $p->dikecualikan_penilaian,
                'alasan_pengecualian' => $p->alasan_pengecualian,
                'vakasi_override'     => $p->vakasi_override,
                'setting_vakasi_nama' => $p->settingVakasi?->nama,
                'setting_vakasi_id'   => $p->setting_vakasi_id,
                // Bukti pengerjaan
                'bukti_tipe'          => $p->bukti_tipe,
                'link_bukti'          => $p->link_bukti,
                'teks_bukti'          => $p->teks_bukti,
                'file_laporan_url'    => $p->file_laporan_url,
                // Absensi kegiatan (jika tipe_pengerjaan=absen_kegiatan)
                // CATATAN: null-safe ?-> hanya melindungi 1 level; method berikutnya
                // (whereIn, count) harus dibungkus dengan pemeriksaan eksplisit agar
                // tidak memanggil method pada null (TypeError) untuk tugas mandiri.
                'kegiatan_id'         => $p->kegiatanAbsensi?->id,
                'kegiatan_status'     => $p->kegiatanAbsensi?->status,
                'kegiatan_nama'       => $p->kegiatanAbsensi?->nama_kegiatan,
                'kegiatan_hadir'      => $p->kegiatanAbsensi
                    ? $p->kegiatanAbsensi->peserta()
                        ->whereIn('status_kehadiran', ['hadir', 'terlambat'])->count()
                    : null,
                'kegiatan_total'      => $p->kegiatanAbsensi
                    ? $p->kegiatanAbsensi->peserta()->count()
                    : null,
            ]);

        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Show', [
            'tugas' => [
                'id'               => $tugasTambahan->id,
                'judul'            => $tugasTambahan->judul,
                'deskripsi'        => $tugasTambahan->deskripsi,
                'tipe'             => $tugasTambahan->tipe,
                'tipe_pengerjaan'  => $tugasTambahan->tipe_pengerjaan ?? 'mandiri',
                'status'           => $tugasTambahan->status,
                'tanggal_mulai'    => $tugasTambahan->tanggal_mulai?->format('d M Y'),
                'tanggal_selesai'  => $tugasTambahan->tanggal_selesai?->format('d M Y'),
                'nominal_vakasi'        => $tugasTambahan->getNominalVakasi(),
                // Vakasi tingkat tugas: nominal + asal-usul + apakah terkunci.
                'vakasi'                => $vakasiSvc->untukTugas($tugasTambahan),
                'setting_vakasi_nama'   => $tugasTambahan->settingVakasi?->nama,
                'setting_vakasi_aktif'  => $tugasTambahan->settingVakasi
                    ? (bool) $tugasTambahan->settingVakasi->is_aktif : null,
                'vakasi_ditetapkan_oleh'=> $tugasTambahan->vakasiDitetapkanOleh?->name,
                'vakasi_ditetapkan_pada'=> $tugasTambahan->vakasi_ditetapkan_pada?->format('d M Y H:i'),
                // Penerima yang nominalnya sudah terkunci slip -> tugas tak bisa
                // diubah massal, hanya per penerima yang belum berslip.
                'penerima_terkunci'     => $vakasiSvc->penerimaTerkunci($tugasTambahan),
                'wajib_laporan'         => $tugasTambahan->wajib_laporan,
                'dibuat_oleh'           => $tugasTambahan->dibuatOleh?->name,
                // Skema vakasi: untuk absen_kegiatan, peserta juga dapat vakasi yg sama
                'vakasi_peserta_nominal'=> $tugasTambahan->tipe_pengerjaan === 'absen_kegiatan'
                    ? $tugasTambahan->getNominalVakasi()
                    : null,
            ],
            'penugasan'  => $penugasan,
            // Riwayat perubahan vakasi: jawaban atas "kenapa vakasi ini berubah".
            'riwayatVakasi' => \App\Models\RiwayatVakasiTugas::with('diubahOleh:id,name')
                ->where('tugas_tambahan_id', $tugasTambahan->id)
                ->orderByDesc('id')->limit(50)->get()
                ->map(fn ($r) => [
                    'id'           => $r->id,
                    'penugasan_id' => $r->penugasan_id,
                    'penerima'     => $r->penugasan_id
                        ? ($penugasan->firstWhere('id', $r->penugasan_id)['guru_nama'] ?? '—') : null,
                    'nominal_lama' => $r->nominal_lama,
                    'nominal_baru' => $r->nominal_baru,
                    'sumber_lama'  => $r->sumber_lama,
                    'sumber_baru'  => $r->sumber_baru,
                    'dikunci'      => $r->dikunci,
                    'alasan'       => $r->alasan,
                    'oleh'         => $r->diubahOleh?->name ?? 'sistem',
                    'pada'         => $r->created_at?->format('d M Y H:i'),
                ])->values(),
            'guru'       => $this->getGuruList(),
            'vakasi'     => $this->getVakasiList(),
        ]);
    }

    public function edit(TugasTambahan $tugasTambahan)
    {
        // Kirim field eksplisit — JANGAN kirim full model agar:
        // 1. tanggal_mulai_raw / tanggal_selesai_raw tersedia dalam format Y-m-d
        //    (Carbon yang diserialisasi Inertia menjadi ISO string, bukan format input[date])
        // 2. setting_vakasi_id dan vakasi_override konsisten dengan yang dikirim form
        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Form', [
            'tugas'   => [
                'id'                  => $tugasTambahan->id,
                'judul'               => $tugasTambahan->judul,
                'deskripsi'           => $tugasTambahan->deskripsi,
                'tanggal_mulai'       => $tugasTambahan->tanggal_mulai?->format('d M Y'),
                'tanggal_mulai_raw'   => $tugasTambahan->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai'     => $tugasTambahan->tanggal_selesai?->format('d M Y'),
                'tanggal_selesai_raw' => $tugasTambahan->tanggal_selesai?->format('Y-m-d'),
                'tipe'                => $tugasTambahan->tipe,
                'tipe_pengerjaan'     => $tugasTambahan->tipe_pengerjaan ?? 'mandiri',
                'setting_vakasi_id'   => $tugasTambahan->setting_vakasi_id,   // null jika tidak ada
                'vakasi_override'     => $tugasTambahan->vakasi_override,     // null jika tidak ada
                'wajib_laporan'       => $tugasTambahan->wajib_laporan,
                'status'              => $tugasTambahan->status,
            ],
            'guru'    => $this->getGuruList(),
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'vakasi'  => $this->getVakasiList(),
        ]);
    }

    public function update(Request $request, TugasTambahan $tugasTambahan)
    {
        // ── Sanitasi: empty-string → null ──────────────────────────────────────
        // Dilakukan SEBELUM validate() agar cast Eloquent tidak error:
        //   setting_vakasi_id: '' → QueryException (FK integer)
        //   vakasi_override: ''   → 0.0 (float cast, korupsi data diam-diam)
        //   tanggal_selesai: ''   → InvalidFormatException (date cast Carbon::parse(''))
        //   deskripsi: ''         → simpan null, bukan string kosong
        $nullableStrings = ['setting_vakasi_id', 'vakasi_override', 'tanggal_selesai', 'deskripsi'];
        foreach ($nullableStrings as $field) {
            $val = $request->input($field);
            if ($val === '' || $val === null) {
                $request->merge([$field => null]);
            }
        }

        // ── Logic absen_kegiatan vs mandiri ────────────────────────────────────
        // "wajib_laporan" hanya berlaku untuk tugas mandiri.
        // Untuk absen_kegiatan, guru tidak upload bukti — tugas selesai lewat absensi kegiatan.
        // Paksa false agar tidak ada inkonsistensi data.
        if ($tugasTambahan->tipe_pengerjaan === 'absen_kegiatan') {
            $request->merge(['wajib_laporan' => false]);
        }

        $data = $request->validate([
            'judul'             => 'required|string|max:200',
            'deskripsi'         => 'nullable|string',
            'tanggal_selesai'   => 'nullable|date|after_or_equal:' . ($tugasTambahan->tanggal_mulai?->format('Y-m-d') ?? '2000-01-01'),
            'setting_vakasi_id' => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'   => 'nullable|numeric|min:0',
            'wajib_laporan'     => 'boolean',
            'status'            => 'required|in:draft,aktif,selesai,dibatalkan',
        ]);

        $tugasTambahan->update($data);

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $tugasTambahan->id)
            ->with('success', 'Tugas tambahan diperbarui.');
    }

    /**
     * SELESAIKAN TUGAS walau pengisiannya belum lengkap.
     *
     * Dibutuhkan karena tugas bisa menggantung selamanya: tenggat sudah lewat
     * sehingga guru tidak bisa mengisi lagi (lihat PenugasanTambahan::bisaDiisi),
     * sementara tugasnya tetap berstatus 'aktif' dan penerima yang belum mengisi
     * terus menekan skor kinerja mereka.
     *
     * Guru yang belum mengisi tidak selalu bersalah — ada yang lalai, ada yang
     * kegiatannya memang tidak jalan — jadi perlakuannya DIPILIH superadmin tiap
     * kali menutup, bukan diputuskan sistem:
     *   • tidak_dinilai : dikeluarkan dari penilaian (tidak menambah/mengurangi skor)
     *   • gagal         : ditandai tidak terlaksana, skornya turun
     * Keduanya wajib beralasan, tercatat pelakunya, dan dikabari ke gurunya.
     *
     * Yang sudah 'selesai' TIDAK disentuh: status, persetujuan, dan vakasinya
     * tetap apa adanya. Penutupan ini juga tidak memberi vakasi kepada yang
     * belum mengisi — vakasi mandiri menuntut selesai + dilaporkan_pada.
     */
    public function selesaikan(TugasTambahan $tugasTambahan, Request $request)
    {
        $data = $request->validate([
            'perlakuan' => 'required|in:tidak_dinilai,gagal',
            'alasan'    => 'required|string|min:5|max:300',
        ], [], ['alasan' => 'alasan penutupan']);

        if ($tugasTambahan->status === 'selesai') {
            return back()->with('error', 'Tugas ini sudah berstatus selesai.');
        }
        if ($tugasTambahan->status === 'dibatalkan') {
            return back()->with('error',
                'Tugas ini sudah dibatalkan — aktifkan dulu bila memang ingin diselesaikan.');
        }

        $belum = $tugasTambahan->penugasan()
            ->with('tenagaPendidik.user')
            ->where('status_pengerjaan', '!=', 'selesai')
            ->get();

        DB::transaction(function () use ($tugasTambahan, $belum, $data) {
            $tugasTambahan->update(['status' => 'selesai']);

            foreach ($belum as $p) {
                if ($data['perlakuan'] === 'tidak_dinilai') {
                    $p->update([
                        'dikecualikan_penilaian' => true,
                        'alasan_pengecualian'    => $data['alasan'],
                        'dikecualikan_oleh'      => auth()->id(),
                        'dikecualikan_pada'      => TimezoneHelper::now(),
                    ]);
                } else {
                    $p->update([
                        'status_pengerjaan'  => 'tidak_selesai',
                        'disetujui'          => false,
                        'catatan_verifikasi' => 'Tidak terlaksana: ' . $data['alasan'],
                        'diverifikasi_oleh'  => auth()->id(),
                        'diputuskan_pada'    => TimezoneHelper::now(),
                    ]);
                }
            }
        });

        // Notifikasi di luar transaksi: kegagalan kirim tidak boleh membatalkan
        // keputusan yang sudah sah tersimpan.
        $judul = $tugasTambahan->judul;
        foreach ($belum as $p) {
            $data['perlakuan'] === 'tidak_dinilai'
                ? $this->kabariKeputusan($p, 'Tugas Ditutup Tanpa Penilaian',
                    "Tugas \"{$judul}\" ditutup dan TIDAK dinilai untuk Anda. Alasan: {$data['alasan']}",
                    'ditutup-bebas')
                : $this->kabariKeputusan($p, 'Tugas Ditutup — Ditandai Tidak Terlaksana',
                    "Tugas \"{$judul}\" ditutup dan ditandai tidak terlaksana untuk Anda. Alasan: {$data['alasan']}",
                    'ditutup-gagal');
        }

        $pesan = $belum->isEmpty()
            ? 'Tugas ditandai selesai. Semua penerima sudah mengisi.'
            : ($data['perlakuan'] === 'tidak_dinilai'
                ? "Tugas ditandai selesai. {$belum->count()} penerima yang belum mengisi dikeluarkan dari penilaian kinerja."
                : "Tugas ditandai selesai. {$belum->count()} penerima yang belum mengisi ditandai tidak terlaksana.");

        return back()->with('success', $pesan);
    }

    public function destroy(TugasTambahan $tugasTambahan)
    {
        $tugasTambahan->update(['status' => 'dibatalkan']);
        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.index')
            ->with('success', 'Tugas dibatalkan.');
    }

    /**
     * Assign guru tambahan ke tugas yang sudah berjalan.
     */
    /**
     * Unduh Berita Acara (PDF) satu tugas tambahan — lengkap dengan penerima,
     * penyelesaian, dan absensi kegiatannya. Dipakai untuk pelaporan.
     */
    public function beritaAcara(Request $request, TugasTambahan $tugasTambahan)
    {
        $svc = app(\App\Services\BeritaAcaraTugasService::class);

        return $svc->pdf($tugasTambahan, $request->user()->name ?? 'Administrator')
            ->download($svc->namaBerkas($tugasTambahan));
    }

    public function assign(TugasTambahan $tugasTambahan, Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_ids'   => 'required|array',
            'tenaga_pendidik_ids.*' => 'exists:tenaga_pendidik,id',
            'setting_vakasi_id'     => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'       => 'nullable|numeric|min:0',
        ]);

        $ditambahkan = 0;
        foreach ($data['tenaga_pendidik_ids'] as $id) {
            // Untuk absen_kegiatan: satu guru boleh menjadi pengabsen lebih dari sekali —
            // setiap baris penugasan mewakili satu sesi kegiatan yang berbeda.
            // Tidak ada batasan duplikasi; tiap assign = baris PenugasanTambahan baru.
            PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tugasTambahan->id,
                'tenaga_pendidik_id' => $id,
                'status_pengerjaan'  => 'belum',
                'setting_vakasi_id'  => $data['setting_vakasi_id'] ?? null,
                'vakasi_override'    => $data['vakasi_override'] ?? null,
            ]);
            $ditambahkan++;
        }

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $tugasTambahan->id)
            ->with('success', "{$ditambahkan} penugasan pengabsen berhasil ditambahkan.");
    }

    /**
     * Update vakasi untuk penugasan tertentu (per penerima).
     */
    /**
     * Tetapkan vakasi SATU PENERIMA — lewat VakasiTugasService.
     *
     * Dulu metode ini langsung `update()` tanpa alasan, tanpa jejak, dan tanpa
     * memeriksa apakah slipnya sudah terbit. Itu sebabnya nominal bisa
     * "direvisi" tanpa ada yang bisa menjelaskannya.
     */
    public function updateVakasiPenerima(
        PenugasanTambahan $penugasan, Request $request, \App\Services\VakasiTugasService $svc
    ) {
        $data = $request->validate([
            'vakasi_override' => 'nullable|numeric|min:0|max:100000000',
            'alasan'          => 'required|string|min:5|max:300',
            'kunci'           => 'boolean',
        ], [
            'alasan.required' => 'Alasan perubahan vakasi wajib diisi.',
            'alasan.min'      => 'Alasan terlalu singkat (minimal 5 huruf).',
        ]);

        try {
            $svc->tetapkanPenerima(
                $penugasan,
                $request->filled('vakasi_override') ? (float) $data['vakasi_override'] : null,
                $data['alasan'],
                $request->user()->id,
                (bool) ($data['kunci'] ?? true),
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $hasil = $svc->untukPenerima($penugasan->fresh('tugasTambahan'));

        return back()->with('success', 'Vakasi penerima ditetapkan: Rp '
            . number_format($hasil['nominal'], 0, ',', '.')
            . ($hasil['dikunci'] ? ' (dikunci, tidak lagi mengikuti setting).' : '.'));
    }

    /** Tetapkan vakasi TINGKAT TUGAS (berlaku bagi penerima tanpa nominal sendiri). */
    public function updateVakasiTugas(
        TugasTambahan $tugasTambahan, Request $request, \App\Services\VakasiTugasService $svc
    ) {
        $data = $request->validate([
            'vakasi_override' => 'nullable|numeric|min:0|max:100000000',
            'alasan'          => 'required|string|min:5|max:300',
            'kunci'           => 'boolean',
        ], [
            'alasan.required' => 'Alasan perubahan vakasi wajib diisi.',
            'alasan.min'      => 'Alasan terlalu singkat (minimal 5 huruf).',
        ]);

        try {
            $svc->tetapkanTugas(
                $tugasTambahan,
                $request->filled('vakasi_override') ? (float) $data['vakasi_override'] : null,
                $data['alasan'],
                $request->user()->id,
                (bool) ($data['kunci'] ?? true),
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $hasil = $svc->untukTugas($tugasTambahan->fresh());

        return back()->with('success', 'Vakasi tugas ditetapkan: Rp '
            . number_format($hasil['nominal'], 0, ',', '.')
            . ($hasil['dikunci'] ? ' (dikunci, tidak lagi mengikuti setting).' : '.'));
    }

    /**
     * Keputusan admin atas penerima yang tidak mengisi sampai tenggat.
     *
     * Dua pilihan, keduanya tercatat:
     *  - tidak_terlaksana : status 'tidak_selesai' → tanpa vakasi, dan dihitung
     *                       sebagai tugas tidak terpenuhi pada skor kinerja.
     *  - perpanjang       : beri tenggat tambahan khusus penerima ini sehingga
     *                       tugasnya kembali muncul & bisa diisi di aplikasi guru.
     */
    public function keputusanTenggat(PenugasanTambahan $penugasan, Request $request)
    {
        $data = $request->validate([
            'keputusan' => 'required|in:tidak_terlaksana,perpanjang,batalkan_keputusan',
            'tenggat'   => 'required_if:keputusan,perpanjang|nullable|date|after_or_equal:today',
            'alasan'    => 'required|string|min:5|max:300',
        ], [], ['alasan' => 'alasan keputusan']);

        $penugasan->loadMissing(['tugasTambahan', 'tenagaPendidik.user']);

        if ($penugasan->status_pengerjaan === 'selesai') {
            return back()->with('error', 'Tugas ini sudah selesai dikerjakan — tidak perlu keputusan tenggat.');
        }

        $judul = $penugasan->tugasTambahan?->judul ?? 'Tugas tambahan';

        if ($data['keputusan'] === 'tidak_terlaksana') {
            $penugasan->update([
                'status_pengerjaan'    => 'tidak_selesai',
                'tenggat_perpanjangan' => null,
                'alasan_perpanjangan'  => $data['alasan'],
                'diperpanjang_oleh'    => auth()->id(),
                'diputuskan_pada'      => TimezoneHelper::now(),
                'disetujui'            => false,
                'catatan_verifikasi'   => 'Tidak terlaksana: ' . $data['alasan'],
                'diverifikasi_oleh'    => auth()->id(),
            ]);

            $this->kabariKeputusan($penugasan, 'Tugas Ditandai Tidak Terlaksana',
                "Tugas \"{$judul}\" ditandai tidak terlaksana. Alasan: {$data['alasan']}", 'tidak-terlaksana');

            $pesan = 'Penugasan ditandai tidak terlaksana — tanpa vakasi & dihitung sebagai tugas tidak terpenuhi.';
        } elseif ($data['keputusan'] === 'perpanjang') {
            $penugasan->update([
                'status_pengerjaan'    => $penugasan->status_pengerjaan === 'tidak_selesai'
                    ? 'belum' : $penugasan->status_pengerjaan,
                'tenggat_perpanjangan' => $data['tenggat'],
                'alasan_perpanjangan'  => $data['alasan'],
                'diperpanjang_oleh'    => auth()->id(),
                'diputuskan_pada'      => TimezoneHelper::now(),
                'disetujui'            => null,
                'catatan_verifikasi'   => null,
            ]);

            $sampai = \Carbon\Carbon::parse($data['tenggat'])->locale('id')->isoFormat('D MMMM YYYY');
            $this->kabariKeputusan($penugasan, 'Waktu Tambahan Mengisi Tugas',
                "Tugas \"{$judul}\" diberi waktu tambahan sampai {$sampai}. Alasan: {$data['alasan']}", 'perpanjang');

            $pesan = "Waktu tambahan diberikan sampai {$sampai} — tugas kembali muncul di aplikasi guru.";
        } else {
            // Cabut keputusan: kembali mengikuti tenggat asli tugasnya.
            $penugasan->update([
                'status_pengerjaan'    => $penugasan->status_pengerjaan === 'tidak_selesai'
                    ? 'belum' : $penugasan->status_pengerjaan,
                'tenggat_perpanjangan' => null,
                'alasan_perpanjangan'  => $data['alasan'],
                'diperpanjang_oleh'    => auth()->id(),
                'diputuskan_pada'      => TimezoneHelper::now(),
                'disetujui'            => null,
                'catatan_verifikasi'   => null,
            ]);
            $pesan = 'Keputusan dibatalkan — penugasan kembali mengikuti tenggat asli tugas.';
        }

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $penugasan->tugas_tambahan_id)
            ->with('success', $pesan);
    }

    /** Beri tahu guru tentang keputusan tenggat (menghormati toggle notifikasi). */
    private function kabariKeputusan(PenugasanTambahan $penugasan, string $judul, string $pesan, string $kunci = ''): void
    {
        $user = $penugasan->tenagaPendidik?->user;
        if (!$user) return;

        \App\Services\NotifikasiService::event('tugas.baru', [
            'user'  => $user,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe'  => 'tugas_update',
            'data'  => ['type' => 'tugas', 'route' => '/tugas', 'penugasan_id' => $penugasan->id],
            // Kunci dedup memuat jenis keputusan: dua keputusan berbeda pada detik
            // yang sama (mis. tidak terlaksana lalu diperpanjang) harus dua notifikasi.
            'dedup' => 'tenggat-' . $penugasan->id . '-' . $kunci . '-' . now()->timestamp,
        ]);
    }

    /**
     * Verifikasi pengerjaan tugas.
     */
    public function verifikasi(PenugasanTambahan $penugasan, Request $request)
    {
        $data = $request->validate([
            'disetujui'          => 'required|boolean',
            'catatan_verifikasi' => 'nullable|string|max:500',
        ]);

        $penugasan->update([
            'disetujui'          => $data['disetujui'],
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh'  => auth()->id(),
        ]);

        // BUG FIX: Saat disetujui, pastikan status_pengerjaan = 'selesai' DAN
        // dilaporkan_pada terisi — karena hitungVakasiTugasTambahan() menyaring
        // WHERE status_pengerjaan = 'selesai' AND dilaporkan_pada IS NOT NULL.
        // Tanpa ini, vakasi guru tidak pernah masuk ke slip gaji meski sudah disetujui.
        if ($data['disetujui']) {
            $penugasan->update([
                'status_pengerjaan' => 'selesai',
                'dilaporkan_pada'   => $penugasan->dilaporkan_pada ?? TimezoneHelper::now(),
            ]);
        }

        $msg = $data['disetujui'] ? 'Tugas disetujui. Vakasi akan masuk ke slip gaji bulan ini.' : 'Tugas ditolak.';

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $penugasan->tugas_tambahan_id)
            ->with('success', $msg);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getGuruList(): \Illuminate\Support\Collection
    {
        return TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'jabatanGuru.jabatan'])
            ->get()
            ->map(function ($g) {
                // Jabatan aktif dari pivot (support rangkap)
                $jabatanAktif = $g->jabatanGuru
                    ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                    ->map(fn($jg) => $jg->jabatan?->nama_jabatan)
                    ->filter()->values();

                return [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $jabatanAktif->isNotEmpty()
                        ? $jabatanAktif->join(', ')
                        : ($g->jabatan?->nama_jabatan ?? '—'),
                    'jabatan_ids' => $g->jabatanGuru
                        ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                        ->pluck('jabatan_id')
                        ->toArray() ?: [$g->jabatan_id],
                    'foto'    => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                ];
            });
    }

    private function getVakasiList(): \Illuminate\Support\Collection
    {
        return SettingVakasi::aktif()
            ->byTipe('tugas_tambahan')
            ->orderBy('lingkup')
            ->orderBy('nama')
            ->get()
            ->map(fn($v) => [
                'id'           => $v->id,
                'nama'         => $v->nama,
                'nominal'      => $v->nominal,
                'satuan'       => $v->satuan,
                'lingkup'      => $v->lingkup ?? 'semua',
                'lingkup_label'=> $v->lingkup_label,
                'jabatan_ids'  => is_array($v->jabatan_ids) ? $v->jabatan_ids : [],
                'tenaga_pendidik_ids' => is_array($v->tenaga_pendidik_ids) ? $v->tenaga_pendidik_ids : [],
            ]);
    }

    private function getGuruIdsByJabatan(array $jabatanIds): \Illuminate\Support\Collection
    {
        if (empty($jabatanIds)) return collect();

        // Cari via pivot jabatan_guru dulu
        $viaJabatanGuru = JabatanGuru::whereIn('jabatan_id', $jabatanIds)
            ->whereNull('berlaku_selesai')
            ->pluck('tenaga_pendidik_id');

        // Fallback: via kolom jabatan_id lama
        $viaLama = TenagaPendidik::aktif()
            ->whereIn('jabatan_id', $jabatanIds)
            ->pluck('id');

        return $viaJabatanGuru->merge($viaLama)->unique()->values();
    }
}