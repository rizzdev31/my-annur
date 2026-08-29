<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\HariLibur;
use App\Models\Jabatan;
use App\Models\Penggajian;
use App\Models\PeriodePenggajian;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\SettingJamKerja;
use App\Services\AbsensiKalkulasiService;
use App\Services\PayrollCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaporanController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // RINGKASAN
    // ══════════════════════════════════════════════════════════════════════════

    public function ringkasan(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        $periode = PeriodePenggajian::where('bulan', $bulan)
            ->where('tahun', $tahun)->first();

        return Inertia::render('Admin/SmartPayroll/Laporan/Ringkasan', [
            'periode'   => $periode,
            'totalGuru' => TenagaPendidik::aktif()->count(),
            'totalGaji' => $periode
                ? Penggajian::where('periode_penggajian_id', $periode->id)->sum('gaji_bersih')
                : 0,
            'bulan'     => $bulan,
            'tahun'     => $tahun,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LAPORAN ABSENSI HARIAN
    // ══════════════════════════════════════════════════════════════════════════

    public function absensi(Request $request)
    {
        $mode      = $request->mode ?? 'harian'; // harian | mingguan | bulanan
        $tanggal   = $request->tanggal   ? Carbon::parse($request->tanggal)   : Carbon::today();
        $bulan     = (int) ($request->bulan ?? now()->month);
        $tahun     = (int) ($request->tahun ?? now()->year);
        $mingguKe  = (int) ($request->minggu ?? $tanggal->weekOfMonth);
        $jabatanId = $request->jabatan_id;
        $search    = $request->search;

        // Data dasar guru aktif
        $guruQuery = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->when($jabatanId, fn($q) => $q->where('jabatan_id', $jabatanId))
            ->when($search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ));

        $data = match ($mode) {
            'harian'   => $this->laporanHarian($guruQuery->get(), $tanggal),
            'mingguan' => $this->laporanMingguan($guruQuery->get(), $bulan, $tahun, $mingguKe),
            'bulanan'  => $this->laporanBulanan($guruQuery->get(), $bulan, $tahun),
            default    => $this->laporanHarian($guruQuery->get(), $tanggal),
        };

        return Inertia::render('Admin/SmartPayroll/Laporan/Absensi', [
            'mode'      => $mode,
            'laporan'   => $data['laporan'],
            'ringkasan' => $data['ringkasan'],
            'meta'      => $data['meta'],
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'tanggal'   => $tanggal->toDateString(),
            'jabatan'   => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'filters'   => $request->only(['mode','bulan','tahun','tanggal','jabatan_id','search','minggu']),
        ]);
    }

    // ── Harian ────────────────────────────────────────────────────────────────

    private function laporanHarian($guru, Carbon $tanggal): array
    {
        $hariLibur = HariLibur::aktif()
            ->where('tanggal', '<=', $tanggal)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $tanggal))
            ->first();

        $absensiAda = AbsensiHarian::with('settingLokasi')
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->get()->keyBy('tenaga_pendidik_id');

        // Ambil jadwal kerja untuk kalkulasi terlambat otomatis
        $namaHari  = strtolower($tanggal->locale('en')->dayName);
        $jamKerja  = SettingJamKerja::getDefault();
        $jadwal    = $jamKerja?->getJamUntukHari($namaHari);

        $laporan = $guru->map(function ($g) use ($absensiAda, $tanggal, $jadwal) {
            $a = $absensiAda->get($g->id);

            // ── Kalkulasi status & terlambat via service ─────────────────────
            $status         = $a?->status ?? 'belum';
            $menitTerlambat = (int) ($a?->menit_terlambat ?? 0);

            // Hitung ulang jika ada jam masuk dan belum dikoreksi manual
            if ($a && $a->jam_masuk && !($a->is_koreksi ?? false)) {
                $hasil          = AbsensiKalkulasiService::hitungStatus(
                    $a->jam_masuk, $tanggal->toDateString()
                );
                $status         = $hasil['status'];
                $menitTerlambat = $hasil['menit_terlambat'];
            }

            // Format jam H:i
            $jamMasukFmt  = AbsensiKalkulasiService::formatJam($a?->jam_masuk);
            $jamPulangFmt = AbsensiKalkulasiService::formatJam($a?->jam_pulang);

            // ── Durasi kerja ─────────────────────────────────────────────────
            $durasiLabel = null;
            if ($a?->jam_masuk && $a?->jam_pulang) {
                $masuk   = Carbon::parse($tanggal->toDateString().' '.$a->jam_masuk);
                $pulang  = Carbon::parse($tanggal->toDateString().' '.$a->jam_pulang);
                $durMenit = (int) $masuk->diffInMinutes($pulang);
                $dJam    = (int) floor($durMenit / 60);
                $dMenit  = $durMenit % 60;
                $durasiLabel = "{$dJam}j {$dMenit}m";
            }

            // ── Label terlambat ──────────────────────────────────────────────
            $labelTerlambat = null;
            if ($menitTerlambat > 0) {
                $j = (int) floor($menitTerlambat / 60);
                $m = $menitTerlambat % 60;
                $labelTerlambat = $j > 0 ? "{$j} jam {$m} menit" : "{$menitTerlambat} menit";
            }

            // ── Label validasi lokasi ────────────────────────────────────────
            $labelLokasi = match ($a?->validasi_lokasi) {
                'valid_koordinat'  => 'GPS Valid',
                'valid_wifi'       => 'WiFi Valid',
                'valid_dinas_luar' => 'Dinas Luar',
                'valid_izin'       => 'Izin Aktif',
                'invalid'          => 'Di Luar Area',
                'bypass_admin'     => 'Input Admin',
                'tidak_diperiksa'  => 'Tanpa GPS',
                null               => null,
                default            => $a?->validasi_lokasi,
            };

            return [
                'id'               => $g->id,
                'nama'             => $g->user->name,
                'nip'              => $g->nip,
                'foto'             => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                'jabatan'          => $g->jabatan?->nama_jabatan ?? '—',
                'status'           => $status,
                'jam_masuk'        => $jamMasukFmt,
                'jam_pulang'       => $jamPulangFmt,
                'menit_terlambat'  => $menitTerlambat,
                'label_terlambat'  => $labelTerlambat,
                'durasi_label'     => $durasiLabel,
                'validasi_lokasi'  => $a?->validasi_lokasi,
                'label_lokasi'     => $labelLokasi,
                'lokasi_valid'     => in_array($a?->validasi_lokasi, [
                    'valid_koordinat','valid_wifi','valid_dinas_luar','valid_izin','bypass_admin'
                ]),
                'jarak_meter'      => $a?->jarak_meter ? round($a->jarak_meter) : null,
                'nama_wifi'        => $a?->nama_wifi,
                'keterangan'       => $a?->keterangan,
                'is_koreksi'       => $a?->is_koreksi ?? false,
                'foto_masuk_url'   => $a?->foto_masuk
                    ? asset('storage/'.$a->foto_masuk) : null,
                'foto_pulang_url'  => $a?->foto_pulang
                    ? asset('storage/'.$a->foto_pulang) : null,
            ];
        })->values();

        $ringkasan = [
            'hadir'                 => $laporan->whereIn('status', ['hadir','terlambat','dinas_luar'])->count(),
            'terlambat'             => $laporan->where('status', 'terlambat')->count(),
            'izin'                  => $laporan->whereIn('status', ['izin','izin_sakit'])->count(),
            'sakit'                 => $laporan->where('status', 'sakit')->count(),
            'alfa'                  => $laporan->where('status', 'alfa')->count(),
            'belum'                 => $laporan->where('status', 'belum')->count(),
            'total'                 => $laporan->count(),
            'total_menit_terlambat' => $laporan->sum('menit_terlambat'),
            'jadwal_masuk'          => $jadwal['jam_masuk'] ?? null,
            'jadwal_pulang'         => $jadwal['jam_pulang'] ?? null,
            'toleransi'             => $jadwal['toleransi'] ?? 15,
        ];

        return [
            'laporan'   => $laporan,
            'ringkasan' => $ringkasan,
            'meta'      => [
                'label'      => $tanggal->locale('id')->isoFormat('dddd, D MMMM Y'),
                'hari_libur' => $hariLibur?->nama,
            ],
        ];
    }

    // ── Mingguan ──────────────────────────────────────────────────────────────

    private function laporanMingguan($guru, int $bulan, int $tahun, int $mingguKe): array
    {
        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        // Hitung batas minggu ke-N dalam bulan
        $hariKerjaList = [];
        $current       = $mulai->copy();
        $mingguCount   = 1;
        $batasMulai = $batasSelesai = null;

        while ($current->lte($selesai)) {
            if ($current->isWeekday()) {
                if ($mingguCount === $mingguKe && !$batasMulai) {
                    $batasMulai = $current->copy();
                }
                if ($mingguCount === $mingguKe) {
                    $hariKerjaList[] = $current->toDateString();
                    $batasSelesai    = $current->copy();
                }
            }
            // Pindah minggu ketika Sabtu/Minggu
            if ($current->isSunday()) $mingguCount++;
            $current->addDay();
        }

        if (!$batasMulai) {
            return ['laporan' => collect(), 'ringkasan' => [], 'meta' => ['label' => 'Minggu tidak ditemukan']];
        }

        $absensiList = AbsensiHarian::whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->whereIn('tanggal', $hariKerjaList)
            ->get()->groupBy('tenaga_pendidik_id');

        $laporan = $guru->map(function ($g) use ($absensiList, $hariKerjaList) {
            $absensi   = $absensiList->get($g->id, collect());
            $hadir     = $absensi->whereIn('status', ['hadir','terlambat','dinas_luar'])->count();
            $terlambat = $absensi->where('status', 'terlambat')->count();
            $alfa      = $absensi->where('status', 'alfa')->count();
            $izin      = $absensi->whereIn('status', ['izin','izin_sakit'])->count();
            $sakit     = $absensi->where('status', 'sakit')->count();
            $menit     = (int) $absensi->sum('menit_terlambat');

            return [
                'id'              => $g->id,
                'nama'            => $g->user->name,
                'nip'             => $g->nip,
                'foto'            => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                'hadir'           => $hadir,
                'terlambat'       => $terlambat,
                'izin'            => $izin,
                'sakit'           => $sakit,
                'alfa'            => $alfa,
                'total_hari'      => count($hariKerjaList),
                'pct_hadir'       => count($hariKerjaList) > 0
                    ? round($hadir / count($hariKerjaList) * 100, 1) : 0,
                'menit_terlambat' => $menit,
                'detail'          => $absensi->map(fn($a) => [
                    'tanggal' => $a->tanggal?->format('d M'),
                    'status'  => $a->status,
                    'jam_masuk' => $a->jam_masuk,
                    'menit_terlambat' => $a->menit_terlambat ?? 0,
                ])->values(),
            ];
        })->values();

        $ringkasan = [
            'total_guru'            => $laporan->count(),
            'rata_hadir'            => round($laporan->avg('hadir'), 1),
            'total_alfa'            => $laporan->sum('alfa'),
            'total_menit_terlambat' => $laporan->sum('menit_terlambat'),
            'total_hari_kerja'      => count($hariKerjaList),
        ];

        return [
            'laporan'   => $laporan,
            'ringkasan' => $ringkasan,
            'meta'      => [
                'label'      => "Minggu ke-{$mingguKe} ({$batasMulai->format('d')}"
                              . "–{$batasSelesai->format('d M Y')})",
                'total_hari' => count($hariKerjaList),
            ],
        ];
    }

    // ── Bulanan ───────────────────────────────────────────────────────────────

    private function laporanBulanan($guru, int $bulan, int $tahun): array
    {
        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        // Hari kerja efektif
        $hariLibur = HariLibur::aktif()
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $mulai))
            ->get();

        $hariLiburSet = collect();
        foreach ($hariLibur as $hl) {
            $s = Carbon::parse(max($mulai->toDateString(), $hl->tanggal->toDateString()));
            $e = Carbon::parse(min($selesai->toDateString(), ($hl->tanggal_selesai ?? $hl->tanggal)->toDateString()));
            while ($s->lte($e)) {
                $hariLiburSet->push($s->toDateString());
                $s->addDay();
            }
        }
        $totalHariKerja = (int) ($mulai->diffInWeekdays($selesai) + 1
                        - $hariLiburSet->unique()->count());

        $absensiList = AbsensiHarian::whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get()->groupBy('tenaga_pendidik_id');

        $laporan = $guru->map(function ($g) use ($absensiList, $totalHariKerja) {
            $absensi   = $absensiList->get($g->id, collect());
            $hadir     = $absensi->whereIn('status', ['hadir','terlambat','dinas_luar'])->count();
            $terlambat = $absensi->where('status', 'terlambat')->count();
            $alfa      = $absensi->where('status', 'alfa')->count();
            $izin      = $absensi->whereIn('status', ['izin','izin_sakit'])->count();
            $sakit     = $absensi->where('status', 'sakit')->count();
            $libur     = $absensi->where('status', 'libur')->count();
            $menit     = (int) $absensi->sum('menit_terlambat');

            // Skor absensi (sama logika KinerjaCalculationService)
            $nilaiTotal  = ($hadir * 100) + ($terlambat * 75)
                         + ($izin * 60) + ($sakit * 70) + ($alfa * 0);
            $skorAbsensi = $totalHariKerja > 0
                ? min(100, round($nilaiTotal / ($totalHariKerja * 100) * 100, 1))
                : 100;

            return [
                'id'              => $g->id,
                'nama'            => $g->user->name,
                'nip'             => $g->nip,
                'foto'            => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                'hadir'           => $hadir,
                'terlambat'       => $terlambat,
                'izin'            => $izin,
                'sakit'           => $sakit,
                'alfa'            => $alfa,
                'libur'           => $libur,
                'menit_terlambat' => $menit,
                'label_terlambat' => $menit >= 60
                    ? floor($menit/60).' jam '.($menit%60).' menit'
                    : $menit.' menit',
                'total_hari_kerja'=> $totalHariKerja,
                'pct_hadir'       => $totalHariKerja > 0
                    ? round($hadir / $totalHariKerja * 100, 1) : 0,
                'skor_absensi'    => $skorAbsensi,
            ];
        })->sortByDesc('hadir')->values();

        $ringkasan = [
            'total_guru'            => $laporan->count(),
            'total_hari_kerja'      => $totalHariKerja,
            'rata_hadir'            => round($laporan->avg('hadir'), 1),
            'rata_pct_hadir'        => round($laporan->avg('pct_hadir'), 1),
            'total_alfa'            => $laporan->sum('alfa'),
            'total_terlambat'       => $laporan->sum('terlambat'),
            'total_menit_terlambat' => $laporan->sum('menit_terlambat'),
            'rata_skor_absensi'     => round($laporan->avg('skor_absensi'), 1),
        ];

        $namaBulan = Carbon::create($tahun, $bulan)->locale('id')->isoFormat('MMMM YYYY');
        return [
            'laporan'   => $laporan,
            'ringkasan' => $ringkasan,
            'meta'      => [
                'label'          => $namaBulan,
                'total_hari_kerja' => $totalHariKerja,
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PENGGAJIAN, VAKASI, DETAIL GURU, EXPORT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Laporan Penggajian — daftar slip gaji per periode (bulan), per guru.
     * Tiap kartu = slip gaji seorang guru; bisa dibuka & dicetak (format slip instansi).
     */
    public function penggajian(Request $request)
    {
        // Semua periode utk selektor
        $periodeOptions = PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')
            ->get()->map(fn($p) => [
                'id'         => $p->id,
                'label'      => $p->nama_bulan,
                'bulan'      => $p->bulan,
                'tahun'      => $p->tahun,
                'status'     => $p->status,
            ]);

        // Resolusi periode terpilih
        $periode = null;
        if ($request->periode_id) {
            $periode = PeriodePenggajian::find((int) $request->periode_id);
        }
        if (!$periode && $request->bulan && $request->tahun) {
            $periode = PeriodePenggajian::where('bulan', (int) $request->bulan)
                ->where('tahun', (int) $request->tahun)->first();
        }
        if (!$periode) {
            $periode = PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')->first();
        }

        $penggajian = collect();
        $stats = ['total_guru' => 0, 'total_pendapatan' => 0, 'total_potongan' => 0, 'total_bersih' => 0];

        if ($periode) {
            $rows = Penggajian::with(['tenagaPendidik.user', 'tenagaPendidik.jabatan'])
                ->where('periode_penggajian_id', $periode->id)->get();

            $penggajian = $rows->map(fn($pg) => [
                'id'               => $pg->id,
                'nama'             => $pg->tenagaPendidik?->user?->name ?? '—',
                'nip'              => $pg->tenagaPendidik?->nip,
                'foto'             => $pg->tenagaPendidik?->user?->foto
                    ? asset('storage/'.$pg->tenagaPendidik->user->foto) : null,
                'jabatan'          => $pg->tenagaPendidik?->jabatan?->nama_jabatan ?? '—',
                'total_pendapatan' => $pg->total_pendapatan,
                'total_potongan'   => $pg->total_potongan,
                'gaji_bersih'      => $pg->gaji_bersih,
                'status'           => $pg->status,
                'status_badge'     => $pg->status_badge,
            ])->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();

            $stats = [
                'total_guru'       => $rows->count(),
                'total_pendapatan' => (float) $rows->sum('total_pendapatan'),
                'total_potongan'   => (float) $rows->sum('total_potongan'),
                'total_bersih'     => (float) $rows->sum('gaji_bersih'),
            ];
        }

        return Inertia::render('Admin/SmartPayroll/Laporan/Penggajian', [
            'periodeOptions' => $periodeOptions,
            'periode'        => $periode ? [
                'id'         => $periode->id,
                'label'      => $periode->nama_bulan,
                'bulan'      => $periode->bulan,
                'tahun'      => $periode->tahun,
                'status'     => $periode->status,
            ] : null,
            'penggajian' => $penggajian,
            'stats'      => $stats,
            'filters'    => ['periode_id' => $periode?->id],
        ]);
    }

    /**
     * Slip gaji per guru (format slip instansi — dua kolom Penerimaan/Potongan).
     * Konten digerakkan dari data penggajian nyata; total bersifat otoritatif (kolom).
     */
    public function slipGaji(Penggajian $penggajian)
    {
        return Inertia::render(
            'Admin/SmartPayroll/Laporan/SlipGaji',
            \App\Services\SlipGajiBuilder::build($penggajian)
        );
    }

    /**
     * Laporan Vakasi — daftar honor vakasi per guru untuk satu periode.
     * Sumber data: tabel penggajian (Opsi A — otoritatif, hanya ada setelah
     * payroll digenerate). Tiap kartu = total vakasi seorang guru; bisa dibuka
     * jadi rincian transparan per komponen.
     */
    public function vakasi(Request $request)
    {
        $periodeOptions = PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')
            ->get()->map(fn($p) => [
                'id'     => $p->id,
                'label'  => $p->nama_bulan,
                'bulan'  => $p->bulan,
                'tahun'  => $p->tahun,
                'status' => $p->status,
            ]);

        $periode = null;
        if ($request->periode_id) {
            $periode = PeriodePenggajian::find((int) $request->periode_id);
        }
        if (!$periode) {
            $periode = PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')->first();
        }

        $vakasiList = collect();
        $stats = [
            'total_guru' => 0, 'grand_total' => 0, 'kehadiran' => 0, 'mengajar' => 0,
            'tugas_jabatan' => 0, 'tugas_tambahan' => 0, 'peserta_kegiatan' => 0,
        ];

        if ($periode) {
            $rows = Penggajian::with(['tenagaPendidik.user', 'tenagaPendidik.jabatan'])
                ->where('periode_penggajian_id', $periode->id)->get();

            $vakasiList = $rows->map(function ($pg) {
                $kehadiran       = (float) $pg->vakasi_absen_harian;
                $mengajar        = (float) $pg->vakasi_mengajar;
                $tugasJabatan    = (float) $pg->vakasi_tugas_jabatan;
                $tugasTambahan   = (float) $pg->vakasi_tugas_tambahan;
                $pesertaKegiatan = (float) ($pg->vakasi_peserta_kegiatan ?? 0);
                $lembur          = (float) ($pg->vakasi_lembur ?? 0);
                $total = $kehadiran + $mengajar + $tugasJabatan + $tugasTambahan + $pesertaKegiatan + $lembur;

                return [
                    'id'               => $pg->id,
                    'nama'             => $pg->tenagaPendidik?->user?->name ?? '—',
                    'nip'              => $pg->tenagaPendidik?->nip,
                    'foto'             => $pg->tenagaPendidik?->user?->foto
                        ? asset('storage/'.$pg->tenagaPendidik->user->foto) : null,
                    'jabatan'          => $pg->tenagaPendidik?->jabatan?->nama_jabatan ?? '—',
                    'kehadiran'        => $kehadiran,
                    'mengajar'         => $mengajar,
                    'tugas_jabatan'    => $tugasJabatan,
                    'tugas_tambahan'   => $tugasTambahan,
                    'peserta_kegiatan' => $pesertaKegiatan,
                    'lembur'           => $lembur,
                    'total_vakasi'     => $total,
                    'status'           => $pg->status,
                    'status_badge'     => $pg->status_badge,
                ];
            })->sortByDesc('total_vakasi')->values();

            $stats = [
                'total_guru'       => $vakasiList->where('total_vakasi', '>', 0)->count(),
                'grand_total'      => (float) $vakasiList->sum('total_vakasi'),
                'kehadiran'        => (float) $rows->sum('vakasi_absen_harian'),
                'mengajar'         => (float) $rows->sum('vakasi_mengajar'),
                'tugas_jabatan'    => (float) $rows->sum('vakasi_tugas_jabatan'),
                'tugas_tambahan'   => (float) $rows->sum('vakasi_tugas_tambahan'),
                'peserta_kegiatan' => (float) $rows->sum('vakasi_peserta_kegiatan'),
                'lembur'           => (float) $rows->sum('vakasi_lembur'),
            ];
        }

        return Inertia::render('Admin/SmartPayroll/Laporan/Vakasi', [
            'periodeOptions' => $periodeOptions,
            'periode'        => $periode ? [
                'id'     => $periode->id,
                'label'  => $periode->nama_bulan,
                'bulan'  => $periode->bulan,
                'tahun'  => $periode->tahun,
                'status' => $periode->status,
            ] : null,
            'vakasi'  => $vakasiList,
            'stats'   => $stats,
            'filters' => ['periode_id' => $periode?->id],
        ]);
    }

    /**
     * Rincian vakasi transparan per guru (1 periode) — dari detail_penggajian.
     * Dikelompokkan per komponen: kehadiran, mengajar, tugas jabatan,
     * tugas tambahan, peserta kegiatan. Printable.
     */
    public function vakasiDetail(Penggajian $penggajian)
    {
        $penggajian->load([
            'tenagaPendidik.user',
            'tenagaPendidik.jabatan',
            'periodePenggajian',
            'detailPenggajian',
        ]);

        $detail = $penggajian->detailPenggajian;

        // Pemetaan tipe detail → label komponen + kolom otoritatif
        $map = [
            'vakasi_absen'            => ['label' => 'Vakasi Kehadiran',        'col' => 'vakasi_absen_harian'],
            'vakasi_mengajar'         => ['label' => 'Vakasi Mengajar',         'col' => 'vakasi_mengajar'],
            'vakasi_tugas_jabatan'    => ['label' => 'Vakasi Tugas Jabatan',    'col' => 'vakasi_tugas_jabatan'],
            'vakasi_tugas_tambahan'   => ['label' => 'Vakasi Tugas Tambahan',   'col' => 'vakasi_tugas_tambahan'],
            'vakasi_peserta_kegiatan' => ['label' => 'Vakasi Peserta Kegiatan', 'col' => 'vakasi_peserta_kegiatan'],
            'vakasi_lembur'           => ['label' => 'Vakasi Lembur',           'col' => 'vakasi_lembur'],
        ];

        $sections = [];
        foreach ($map as $tipe => $cfg) {
            $rows = $detail->where('tipe', $tipe)->map(fn($d) => [
                'keterangan'       => $d->keterangan,
                'jumlah_satuan'    => $d->jumlah_satuan,
                'satuan'           => $d->satuan,
                'nilai_per_satuan' => (float) $d->nilai_per_satuan,
                'subtotal'         => (float) $d->subtotal,
            ])->values();

            $colVal = (float) ($penggajian->{$cfg['col']} ?? 0);

            // Lewati komponen yang memang kosong
            if ($rows->isEmpty() && $colVal <= 0) continue;

            // Fallback: ada nilai kolom tapi tak ada baris detail → 1 baris ringkas
            if ($rows->isEmpty() && $colVal > 0) {
                $rows = collect([[
                    'keterangan'       => $cfg['label'],
                    'jumlah_satuan'    => 1,
                    'satuan'           => '',
                    'nilai_per_satuan' => $colVal,
                    'subtotal'         => $colVal,
                ]]);
            }

            $sumRows = (float) $rows->sum('subtotal');
            $sections[] = [
                'key'      => $tipe,
                'label'    => $cfg['label'],
                'rows'     => $rows,
                'subtotal' => $sumRows != 0.0 ? $sumRows : $colVal,
                'count'    => $rows->count(),
            ];
        }

        $totalVakasi = (float) (
            $penggajian->vakasi_absen_harian
            + $penggajian->vakasi_mengajar
            + $penggajian->vakasi_tugas_jabatan
            + $penggajian->vakasi_tugas_tambahan
            + ($penggajian->vakasi_peserta_kegiatan ?? 0)
            + ($penggajian->vakasi_lembur ?? 0)
        );

        $instansi = \App\Services\SlipGajiBuilder::instansi();

        return Inertia::render('Admin/SmartPayroll/Laporan/VakasiDetail', [
            'instansi' => $instansi,
            'logo'     => $instansi['logo'],
            'guru'     => $this->guruHeader($penggajian->tenagaPendidik),
            'periode'  => [
                'id'    => $penggajian->periodePenggajian?->id,
                'label' => $penggajian->periodePenggajian?->nama_bulan,
            ],
            'vakasi' => [
                'id'           => $penggajian->id,
                'sections'     => $sections,
                'total'        => $totalVakasi,
                'status'       => $penggajian->status,
                'status_badge' => $penggajian->status_badge,
            ],
        ]);
    }

    /**
     * Halaman LAPORAN KEHADIRAN (menu tersendiri).
     * Pilih guru + periode (harian / mingguan / bulanan) → laporan per-tanggal
     * sesuai format kartu kehadiran. Sinkron penuh dgn logika absensi berjalan.
     */
    public function kehadiran(Request $request)
    {
        $guruId   = $request->guru_id ? (int) $request->guru_id : null;
        $guruList = $this->guruSelectorList();
        $p        = $this->resolvePeriode($request);

        $laporan = null;
        if ($guruId) {
            $guru = TenagaPendidik::with(['user', 'jabatan'])->find($guruId);
            if ($guru && $guru->user) {
                $data = $this->buildBarisKehadiran($guru, $p['mulai']->copy(), $p['selesai']->copy());
                $laporan = [
                    'guru'      => $this->guruHeader($guru),
                    'periode'   => ['mode' => $p['mode'], 'label' => $p['label']],
                    'rows'      => $data['rows'],
                    'ringkasan' => $data['ringkasan'],
                ];
            }
        }

        return Inertia::render('Admin/SmartPayroll/Laporan/Kehadiran', [
            'guruList' => $guruList,
            'laporan'  => $laporan,
            'filters'  => array_merge($p['filters'], ['guru_id' => $guruId]),
        ]);
    }

    /**
     * Halaman LAPORAN ABSENSI MENGAJAR (menu tersendiri).
     * Daftar seluruh sesi mengajar per guru pada periode terpilih:
     * No · Hari · Tanggal · Kelas · Mapel · Jam · JP · Tarif/JP · Total.
     * Tarif per JP & status berhak-bayar selaras dgn PayrollCalculationService.
     */
    public function mengajar(Request $request)
    {
        $guruId   = $request->guru_id ? (int) $request->guru_id : null;
        $guruList = $this->guruSelectorList();
        $p        = $this->resolvePeriode($request);

        $laporan = null;
        if ($guruId) {
            $guru = TenagaPendidik::with(['user', 'jabatan'])->find($guruId);
            if ($guru && $guru->user) {
                // KEBIJAKAN BARU: mengajar jadwal sendiri = 0 vakasi (masuk gaji pokok).
                // Vakasi mengajar HANYA untuk sesi yang diampu sebagai guru pengganti.
                // Guru yang tidak mengajar (digantikan / tidak terlaksana) kena potongan per sesi.
                $tarif = app(PayrollCalculationService::class)->tarifPerJpMengajar($guru);

                $potSetting = \App\Models\SettingPotongan::aktif()
                    ->where('tipe_pemicu', 'per_sesi_tidak_mengajar')->get()
                    ->first(fn($s) => $s->berlakuUntukGuru($guru));
                $potonganPerSesi = $potSetting ? (float) $potSetting->hitungNominal(0, 1) : 0.0;

                $mulai   = $p['mulai']->toDateString();
                $selesai = $p['selesai']->toDateString();

                // Sesi milik guru sendiri
                $sendiri = AbsensiMengajar::with(['jadwalMengajar.mataPelajaran'])
                    ->where('tenaga_pendidik_id', $guru->id)
                    ->whereBetween('tanggal', [$mulai, $selesai])->get();

                // Sesi yang diampu guru ini SEBAGAI PENGGANTI (jadwal milik guru lain)
                $sebagaiPengganti = AbsensiMengajar::with(['jadwalMengajar.mataPelajaran'])
                    ->where('digantikan_oleh', $guru->id)
                    ->where('status', 'pengganti')
                    ->where('jp_terlaksana', '>', 0)
                    ->whereBetween('tanggal', [$mulai, $selesai])->get();

                $buildRow = function ($a, string $jenis) {
                    $jadwal = $a->jadwalMengajar;
                    $jamMulai = $a->jam_mulai_aktual ? substr($a->jam_mulai_aktual, 0, 5)
                        : ($jadwal?->jam_mulai ? substr($jadwal->jam_mulai, 0, 5) : null);
                    $jamSelesai = $a->jam_selesai_aktual ? substr($a->jam_selesai_aktual, 0, 5)
                        : ($jadwal?->jam_selesai ? substr($jadwal->jam_selesai, 0, 5) : null);
                    return [
                        'hari'        => $a->tanggal->locale('id')->isoFormat('dddd'),
                        'tanggal'     => $a->tanggal->format('d/m/Y'),
                        'tanggal_raw' => $a->tanggal->toDateString(),
                        'jam_sort'    => $jamMulai ?? '00:00',
                        'kelas'       => $jadwal?->kelas ?? '—',
                        'mapel'       => $jadwal?->mataPelajaran?->nama ?? '—',
                        'jam'         => ($jamMulai && $jamSelesai) ? "$jamMulai – $jamSelesai" : '—',
                        'jp'          => (int) ($a->jp_terlaksana ?? 0),
                        'jenis'       => $jenis, // mengajar_sendiri | pengganti | dipotong | libur | izin | lain
                        'materi'      => $a->materi,
                    ];
                };

                $rows = collect();
                $jpSendiri = 0; $sesiSendiri = 0;
                $jpPengganti = 0; $sesiPengganti = 0; $vakasiPengganti = 0.0;
                $sesiDipotong = 0; $potonganTotal = 0.0;

                foreach ($sendiri as $a) {
                    switch ($a->status) {
                        case 'terlaksana':
                        case 'hadir':
                            $r = $buildRow($a, 'mengajar_sendiri');
                            $r['status_label'] = 'Mengajar (masuk gaji pokok)';
                            $r['subtotal'] = 0;
                            $jpSendiri += $r['jp']; $sesiSendiri++;
                            break;
                        case 'libur':
                            $r = $buildRow($a, 'libur');
                            $r['status_label'] = 'Libur';
                            $r['subtotal'] = 0;
                            break;
                        case 'izin':
                            $r = $buildRow($a, 'izin');
                            $r['status_label'] = 'Izin (tidak dipotong)';
                            $r['subtotal'] = 0;
                            break;
                        case 'pengganti':
                            $r = $buildRow($a, 'dipotong');
                            $r['status_label'] = 'Digantikan (dipotong)';
                            $r['subtotal'] = -$potonganPerSesi;
                            $sesiDipotong++; $potonganTotal += $potonganPerSesi;
                            break;
                        case 'tidak_terlaksana':
                            $r = $buildRow($a, 'dipotong');
                            $r['status_label'] = 'Tidak terlaksana (dipotong)';
                            $r['subtotal'] = -$potonganPerSesi;
                            $sesiDipotong++; $potonganTotal += $potonganPerSesi;
                            break;
                        default:
                            $r = $buildRow($a, 'lain');
                            $r['status_label'] = $this->statusMengajarLabel($a->status);
                            $r['subtotal'] = 0;
                    }
                    $rows->push($r);
                }

                foreach ($sebagaiPengganti as $a) {
                    $r = $buildRow($a, 'pengganti');
                    $r['status_label'] = 'Mengajar pengganti';
                    $r['subtotal'] = $r['jp'] * $tarif;
                    $jpPengganti += $r['jp']; $sesiPengganti++; $vakasiPengganti += $r['subtotal'];
                    $rows->push($r);
                }

                $rows = $rows->sortBy(fn($x) => $x['tanggal_raw'].' '.$x['jam_sort'])
                    ->values()
                    ->map(function ($x, $i) { $x['no'] = $i + 1; return $x; });

                $laporan = [
                    'guru'              => $this->guruHeader($guru),
                    'periode'           => ['mode' => $p['mode'], 'label' => $p['label']],
                    'tarif_per_jp'      => $tarif,
                    'potongan_per_sesi' => $potonganPerSesi,
                    'rows'              => $rows->values(),
                    'ringkasan'         => [
                        'total_pertemuan'       => $rows->count(),
                        'sesi_mengajar_sendiri' => $sesiSendiri,
                        'jp_mengajar_sendiri'   => $jpSendiri,
                        'sesi_pengganti'        => $sesiPengganti,
                        'jp_pengganti'          => $jpPengganti,
                        'vakasi_pengganti'      => $vakasiPengganti,
                        'sesi_dipotong'         => $sesiDipotong,
                        'potongan_total'        => $potonganTotal,
                        'net_mengajar'          => $vakasiPengganti - $potonganTotal,
                        'tarif_per_jp'          => $tarif,
                        'potongan_per_sesi'     => $potonganPerSesi,
                    ],
                ];
            }
        }

        return Inertia::render('Admin/SmartPayroll/Laporan/Mengajar', [
            'guruList' => $guruList,
            'laporan'  => $laporan,
            'filters'  => array_merge($p['filters'], ['guru_id' => $guruId]),
        ]);
    }

    /** Header info guru standar utk kartu laporan. */
    private function guruHeader(TenagaPendidik $guru): array
    {
        return [
            'id'      => $guru->id,
            'nama'    => $guru->user->name,
            'nip'     => $guru->nip,
            'email'   => $guru->user->email,
            'foto'    => $guru->user->foto ? asset('storage/'.$guru->user->foto) : null,
            'jabatan' => $guru->jabatan?->nama_jabatan ?? '—',
        ];
    }

    /** Daftar guru aktif utk selektor laporan. */
    private function guruSelectorList()
    {
        return TenagaPendidik::aktif()->with(['user', 'jabatan'])->get()
            ->map(fn($g) => [
                'id'      => $g->id,
                'nama'    => $g->user?->name,
                'nip'     => $g->nip,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                'foto'    => $g->user?->foto ? asset('storage/'.$g->user->foto) : null,
            ])
            ->filter(fn($g) => $g['nama'] !== null)
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Resolusi periode laporan dari request → [mulai, selesai, label, filters].
     * mode: harian (rentang awal–akhir fleksibel) | mingguan | bulanan.
     */
    private function resolvePeriode(Request $request): array
    {
        $mode    = in_array($request->mode, ['harian', 'mingguan', 'bulanan'])
            ? $request->mode : 'bulanan';
        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $tanggal = $request->tanggal ? Carbon::parse($request->tanggal) : Carbon::today();

        // Mode harian = rentang tanggal fleksibel (awal–akhir)
        $tglAwal  = $request->tanggal_awal
            ? Carbon::parse($request->tanggal_awal)
            : ($request->tanggal ? Carbon::parse($request->tanggal) : Carbon::today());
        $tglAkhir = $request->tanggal_akhir
            ? Carbon::parse($request->tanggal_akhir)
            : $tglAwal->copy();
        if ($tglAkhir->lt($tglAwal)) {
            [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
        }
        if ($tglAwal->diffInDays($tglAkhir) > 366) {
            $tglAkhir = $tglAwal->copy()->addDays(366);
        }

        [$mulai, $selesai, $label] = match ($mode) {
            'harian' => [
                $tglAwal->copy()->startOfDay(),
                $tglAkhir->copy()->endOfDay(),
                $tglAwal->isSameDay($tglAkhir)
                    ? $tglAwal->copy()->locale('id')->isoFormat('dddd, D MMMM YYYY')
                    : $tglAwal->copy()->locale('id')->isoFormat('D MMM YYYY')
                        .' – '.$tglAkhir->copy()->locale('id')->isoFormat('D MMM YYYY'),
            ],
            'mingguan' => [
                $tanggal->copy()->startOfWeek(Carbon::MONDAY),
                $tanggal->copy()->endOfWeek(Carbon::SUNDAY),
                $tanggal->copy()->startOfWeek(Carbon::MONDAY)->locale('id')->isoFormat('D MMM')
                    .' – '.$tanggal->copy()->endOfWeek(Carbon::SUNDAY)->locale('id')->isoFormat('D MMM YYYY'),
            ],
            default => [
                Carbon::create($tahun, $bulan, 1)->startOfMonth(),
                Carbon::create($tahun, $bulan, 1)->endOfMonth(),
                Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY'),
            ],
        };

        return [
            'mode'    => $mode,
            'mulai'   => $mulai,
            'selesai' => $selesai,
            'label'   => $label,
            'filters' => [
                'mode'          => $mode,
                'bulan'         => $bulan,
                'tahun'         => $tahun,
                'tanggal'       => $tanggal->toDateString(),
                'tanggal_awal'  => $tglAwal->toDateString(),
                'tanggal_akhir' => $tglAkhir->toDateString(),
            ],
        ];
    }

    private function statusMengajarLabel(string $s): string
    {
        return [
            'terlaksana'       => 'Terlaksana',
            'hadir'            => 'Terlaksana',
            'tidak_terlaksana' => 'Tidak Terlaksana',
            'pengganti'        => 'Pengganti',
            'libur'            => 'Libur',
            'izin'             => 'Izin',
        ][$s] ?? ucfirst($s);
    }

    /**
     * Laporan kehadiran per-guru (deep link / cetak), baris per tanggal 1 bulan.
     */
    public function detailGuru(Request $request, TenagaPendidik $guru)
    {
        $guru->loadMissing(['user', 'jabatan']);

        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        $data = $this->buildBarisKehadiran($guru, $mulai->copy(), $selesai->copy());

        return Inertia::render('Admin/SmartPayroll/Laporan/DetailGuru', [
            'guru' => [
                'id'      => $guru->id,
                'nama'    => $guru->user->name,
                'nip'     => $guru->nip,
                'email'   => $guru->user->email,
                'foto'    => $guru->user->foto ? asset('storage/'.$guru->user->foto) : null,
                'jabatan' => $guru->jabatan?->nama_jabatan ?? '—',
            ],
            'periode' => [
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'nama_bulan' => $mulai->locale('id')->isoFormat('MMMM YYYY'),
            ],
            'rows'      => $data['rows'],
            'ringkasan' => $data['ringkasan'],
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'filters'   => $request->only(['bulan', 'tahun']),
        ]);
    }

    /**
     * Bangun baris kehadiran per-tanggal + ringkasan untuk satu guru pada
     * rentang [$mulai, $selesai]. Dipakai oleh kehadiran() & detailGuru().
     *
     * @return array{rows: \Illuminate\Support\Collection, ringkasan: array}
     */
    private function buildBarisKehadiran(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        $today = Carbon::today();

        $absensiByTgl = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get()->keyBy(fn($a) => $a->tanggal->toDateString());

        // Peta tanggal libur (nama libur per tanggal)
        $hariLiburList = HariLibur::aktif()
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $mulai))
            ->get();
        $liburMap = [];
        foreach ($hariLiburList as $hl) {
            $s = Carbon::parse(max($mulai->toDateString(), $hl->tanggal->toDateString()));
            $e = Carbon::parse(min($selesai->toDateString(), ($hl->tanggal_selesai ?? $hl->tanggal)->toDateString()));
            while ($s->lte($e)) { $liburMap[$s->toDateString()] = $hl->nama; $s->addDay(); }
        }

        $jamKerja  = SettingJamKerja::getDefault();
        $mapHariDb = [
            'Monday'=>'senin','Tuesday'=>'selasa','Wednesday'=>'rabu','Thursday'=>'kamis',
            'Friday'=>'jumat','Saturday'=>'sabtu','Sunday'=>'ahad',
        ];

        $rows = collect();
        $cnt  = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'dinas_luar'=>0,
                 'alfa'=>0,'libur'=>0,'off'=>0,'cuti'=>0,'belum'=>0];
        $totalMenitTerlambat = 0;
        $totalHariKerja      = 0;

        $cursor = $mulai->copy();
        while ($cursor->lte($selesai)) {
            $tglStr      = $cursor->toDateString();
            $hariDb      = $mapHariDb[$cursor->format('l')];
            $jadwal      = $jamKerja?->getJamUntukHari($hariDb);
            $isHariKerja = $jadwal !== null;
            $namaLibur   = $liburMap[$tglStr] ?? null;
            $a           = $absensiByTgl->get($tglStr);

            $jamMasuk = $jamPulang = '-';
            $keterangan = null;
            $menit = 0;

            if ($a) {
                $status = $a->status;
                $menit  = (int) ($a->menit_terlambat ?? 0);
                // Rekalkulasi keterlambatan hanya utk hadir/terlambat & belum dikoreksi
                if ($a->jam_masuk && !($a->is_koreksi ?? false)
                    && in_array($a->status, ['hadir', 'terlambat'])) {
                    $h = AbsensiKalkulasiService::hitungStatus($a->jam_masuk, $tglStr);
                    $status = $h['status'];
                    $menit  = (int) $h['menit_terlambat'];
                }
                $jamMasuk   = AbsensiKalkulasiService::formatJam($a->jam_masuk)  ?? '-';
                $jamPulang  = AbsensiKalkulasiService::formatJam($a->jam_pulang) ?? '-';
                $keterangan = $a->keterangan;
            } elseif ($namaLibur) {
                $status = 'libur';
                $keterangan = $namaLibur;
            } elseif (!$isHariKerja) {
                $status = 'off';
            } elseif ($cursor->lt($today)) {
                $status = 'alfa';
            } else {
                $status = 'belum';
            }

            // Hari kerja tanpa record → tampilkan jadwal sebagai referensi jam
            if (!$a && $isHariKerja && $jadwal) {
                $jamMasuk  = substr($jadwal['jam_masuk'] ?? '', 0, 5) ?: '-';
                $jamPulang = substr($jadwal['jam_pulang'] ?? '', 0, 5) ?: '-';
            }

            if (isset($cnt[$status])) $cnt[$status]++;
            $totalMenitTerlambat += $menit;
            if ($isHariKerja && !$namaLibur) $totalHariKerja++;

            $rows->push([
                'hari'            => $cursor->locale('id')->isoFormat('dddd'),
                'tanggal'         => $cursor->format('d/m/Y'),
                'tanggal_label'   => $cursor->locale('id')->isoFormat('D MMM'),
                'tanggal_raw'     => $tglStr,
                'is_weekend'      => $cursor->isWeekend(),
                'jam_masuk'       => $jamMasuk,
                'jam_pulang'      => $jamPulang,
                'shift'           => '-',
                'status'          => $status,
                'status_label'    => $this->statusLabel($status),
                'menit_terlambat' => $menit,
                'label_terlambat' => $menit > 0
                    ? ($menit >= 60 ? floor($menit/60).'j '.($menit%60).'m' : $menit.'m')
                    : null,
                'keterangan'      => $keterangan,
            ]);

            $cursor->addDay();
        }

        $hadirGab    = $cnt['hadir'] + $cnt['terlambat'] + $cnt['dinas_luar'];
        $hadirOnTime = $cnt['hadir'] + $cnt['dinas_luar'];
        $izinGab     = $cnt['izin'] + $cnt['cuti'];

        // Skor & predikat (selaras KinerjaCalculationService)
        $nilaiTotal = ($hadirOnTime * 100) + ($cnt['terlambat'] * 75)
                    + ($izinGab * 60) + ($cnt['sakit'] * 70) + ($cnt['alfa'] * 0);
        $skor = $totalHariKerja > 0
            ? min(100, round($nilaiTotal / ($totalHariKerja * 100) * 100, 1)) : 100;
        $predikat = $skor >= 90 ? 'SANGAT BAIK'
                  : ($skor >= 75 ? 'BAIK'
                  : ($skor >= 60 ? 'CUKUP' : 'KURANG'));

        return [
            'rows'      => $rows->values(),
            'ringkasan' => [
                'alpha'                 => $cnt['alfa'],
                'terlambat'             => $cnt['terlambat'],
                'terlambat_jam'         => intdiv($totalMenitTerlambat, 60),
                'terlambat_menit'       => $totalMenitTerlambat % 60,
                'total_menit_terlambat' => $totalMenitTerlambat,
                'sakit'                 => $cnt['sakit'],
                'izin'                  => $izinGab,
                'hadir'                 => $hadirGab,
                'libur'                 => $cnt['libur'],
                'off'                   => $cnt['off'],
                'total_hari_kerja'      => $totalHariKerja,
                'skor'                  => $skor,
                'predikat'              => $predikat,
            ],
        ];
    }

    private function statusLabel(string $s): string
    {
        return [
            'hadir'      => 'Hadir',
            'terlambat'  => 'Terlambat',
            'izin'       => 'Izin',
            'izin_sakit' => 'Izin Sakit',
            'cuti'       => 'Cuti',
            'sakit'      => 'Sakit',
            'dinas_luar' => 'Dinas Luar',
            'alfa'       => 'Alfa',
            'libur'      => 'Libur',
            'off'        => 'Off',
            'belum'      => 'Belum',
        ][$s] ?? ucfirst($s);
    }

    public function exportDetailGuru(Request $request, TenagaPendidik $guru)
    {
        return back()->with('info', 'Fitur export segera tersedia.');
    }

    public function export(Request $request, string $tipe)
    {
        return back()->with('info', "Export laporan {$tipe} segera tersedia.");
    }
}