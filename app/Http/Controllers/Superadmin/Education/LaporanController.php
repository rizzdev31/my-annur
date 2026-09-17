<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\Kelas;
use App\Models\Santri;
use App\Models\TenagaPendidik;
use App\Models\Surah;
use App\Models\HafalanJuz;
use App\Models\HafalanSantri;
use App\Models\SetoranTahfidz;
use App\Models\SettingTahsinMateri;
use App\Models\TahsinPenilaian;
use App\Models\TahunAjaran;
use App\Services\RekapKehadiranSantriService;
use App\Services\TahfidzService;
use App\Services\TahsinService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class LaporanController extends Controller
{
    // Alamat & telp sama untuk kedua sekolah (sesuai kop resmi).
    private const ALAMAT = 'Jl. H. Ahmad Dahlan No.1, Desa Penatarsewu, '
        . 'Kec. Tanggulangin, Kab. Sidoarjo';
    private const TELP   = '(031) 8052928';
    private const MAJLIS = 'Majlis Pendidikan Dasar Menengah dan Pendidikan Non Formal '
        . 'Cabang Muhammadiyah Tanggulangin Sidoarjo';

    /** Data kop resmi per sekolah untuk header (navbar) & footer laporan. */
    private function kopData(): array
    {
        return [
            [
                'key'    => 'smp',
                'brand'  => 'MUBOSTA',
                'nama'   => 'SMP Muhammadiyah 9 Boarding School Tanggulangin',
                'majlis' => self::MAJLIS,
                'alamat' => self::ALAMAT,
                'telp'   => self::TELP,
                'nss'    => '202050207222',
                'npsn'   => '69972212',
                'logo'   => $this->aset('img/kop/mubosta.png'),
                'badges' => array_values(array_filter([
                    $this->aset('img/kop/smp-badge-1.png'),
                    $this->aset('img/kop/smp-badge-2.png'),
                    $this->aset('img/kop/smp-badge-3.png'),
                ])),
            ],
            [
                'key'    => 'ma',
                'brand'  => 'MA eMAS',
                'nama'   => 'Double Program Learning SMA Muhammadiyah 2 Sidoarjo',
                'majlis' => self::MAJLIS,
                'alamat' => self::ALAMAT,
                'telp'   => self::TELP,
                'nss'    => null,
                'npsn'   => null,
                'logo'   => $this->aset('img/kop/maemas.png'),
                'badges' => array_values(array_filter([
                    $this->aset('img/kop/mubaligh-preneur.png'),
                ])),
            ],
        ];
    }

    /** URL aset publik bila file ada; null bila belum disediakan (slot logo aman kosong). */
    private function aset(string $path): ?string
    {
        return file_exists(public_path($path)) ? asset($path) : null;
    }

    /**
     * Hub laporan Smart Education + Laporan Jurnal Pembelajaran (per kelas).
     */
    public function index(Request $request)
    {
        $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)   : Carbon::today()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::today();
        $kelasId = $request->kelas_id ? (int) $request->kelas_id : null;
        $guruId  = $request->guru_id  ? (int) $request->guru_id  : null;

        $kelas = $kelasId ? Kelas::find($kelasId) : null;
        $rows  = collect();

        if ($kelas || $guruId) {
            $sesi = AbsensiMengajar::query()
                ->when($kelas,  fn($q) => $q->whereHas('jadwalMengajar', fn($j) => $j->where('kelas_id', $kelas->id)))
                ->when($guruId, fn($q) => $q->where('tenaga_pendidik_id', $guruId))
                // Tanpa kelas spesifik → batasi ke kelas SEKOLAH (konsisten laporan pembelajaran)
                ->when(!$kelas, fn($q) => $q->whereHas('jadwalMengajar.kelasRel', fn($k) => $k->where('jenis', 'sekolah')))
                ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
                ->with([
                    'jadwalMengajar.mataPelajaran',
                    'jadwalMengajar.kelasRel:id,nama',
                    'tenagaPendidik.user',
                    'absensiSantri.santri:id,nama_lengkap',
                ])
                ->orderBy('tanggal')->orderBy('jam_mulai_aktual')
                ->get();

            $rows = $sesi->values()->map(function ($a, $i) {
                $byStatus = $a->absensiSantri->groupBy('status');
                $namaBy = fn($st) => $byStatus->get($st)?->map(fn($x) => $x->santri?->nama_lengkap)
                    ->filter()->values()->all() ?? [];

                return [
                    'no'        => $i + 1,
                    'tanggal'   => Carbon::parse($a->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                    'guru'      => $a->tenagaPendidik?->user?->name ?? '—',
                    'nip'       => $a->tenagaPendidik?->nip ?? '—',
                    'kelas'     => $a->jadwalMengajar?->kelasRel?->nama ?? $a->jadwalMengajar?->kelas ?? '—',
                    'mapel'     => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                    'deskripsi' => $a->materi ?: '—',
                    'kehadiran' => [
                        'total'      => $a->absensiSantri->count(),
                        'hadir'      => $byStatus->get('hadir')?->count() ?? 0,
                        'telat'      => $byStatus->get('telat')?->count() ?? 0,
                        'alpha'      => $byStatus->get('alpha')?->count() ?? 0,
                        'telat_nama' => $namaBy('telat'),
                        'alpha_nama' => $namaBy('alpha'),
                        'terisi'     => $a->absensiSantri->isNotEmpty(),
                    ],
                ];
            });
        }

        $guru = $guruId ? TenagaPendidik::with('user:id,name')->find($guruId) : null;

        return Inertia::render('Admin/SmartEducation/Laporan/Index', [
            'rows'   => $rows,
            'kelas'  => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama, 'tingkat' => $kelas->tingkat] : null,
            'guru'   => $guru ? ['id' => $guru->id, 'nama' => $guru->user?->name ?? '—'] : null,
            'filter' => [
                'kelas_id' => $kelasId,
                'guru_id'  => $guruId,
                'dari'     => $dari->toDateString(),
                'sampai'   => $sampai->toDateString(),
            ],
            'periodeLabel' => $dari->locale('id')->isoFormat('D MMM YYYY')
                . ' – ' . $sampai->locale('id')->isoFormat('D MMM YYYY'),
            'tanggalCetak' => Carbon::today()->locale('id')->isoFormat('D MMMM YYYY'),
            'kelasOpsi'    => Kelas::aktif()->sekolah()->orderBy('nama')->get(['id', 'nama']),
            'guruOpsi'     => TenagaPendidik::where('is_aktif', true)->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name])
                ->filter(fn($g) => !empty($g['nama']))
                ->sortBy('nama')->values(),
            'kopOpsi'      => $this->kopData(),
        ]);
    }

    // ── Kop & logo bersama ────────────────────────────────────────────────
    private function kopPayload(): array
    {
        return [
            'tanggalCetak' => Carbon::today()->locale('id')->isoFormat('D MMMM YYYY'),
            'kopOpsi'      => $this->kopData(),
        ];
    }

    private function santriOpsi(?int $kelasId)
    {
        if (!$kelasId) return collect();
        return Santri::aktif()->anggotaKelas($kelasId)
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);
    }

    /** Opsi periode = tahun ajaran (label + semester). */
    private function tahunAjaranOpsi()
    {
        return TahunAjaran::orderByDesc('tanggal_mulai')->get(['id', 'nama', 'semester'])
            ->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->nama . ' — ' . ucfirst($t->semester ?? ''),
            ])->values();
    }

    /** Resolusi periode dari ta_id → [TahunAjaran|null, dari, sampai, label]. */
    private function resolvePeriode(?int $taId): array
    {
        $ta = $taId ? TahunAjaran::find($taId) : null;
        if (!$ta) return [null, null, null, 'Semua (kumulatif)'];
        return [
            $ta,
            $ta->tanggal_mulai ? Carbon::parse($ta->tanggal_mulai)->toDateString() : null,
            $ta->tanggal_selesai ? Carbon::parse($ta->tanggal_selesai)->toDateString() : null,
            $ta->nama . ' — Semester ' . ucfirst($ta->semester ?? ''),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // LAPORAN TAHFIDZ — per kelas (semua santri) / per anak (1 santri)
    // ══════════════════════════════════════════════════════════════════════
    public function tahfidz(Request $request)
    {
        $kelasId  = $request->kelas_id ? (int) $request->kelas_id : null;
        $santriId = $request->santri_id ? (int) $request->santri_id : null;
        $taId     = $request->ta_id ? (int) $request->ta_id : null;
        $kelas    = $kelasId ? Kelas::find($kelasId) : null;
        $santri   = $santriId ? Santri::find($santriId) : null;
        $totalQ   = (int) Surah::sum('jumlah_ayat');

        [, $dari, $sampai, $periodeLabel] = $this->resolvePeriode($taId);
        $applyP = function ($q) use ($dari, $sampai) {
            if ($dari)   $q->whereDate('tanggal', '>=', $dari);
            if ($sampai) $q->whereDate('tanggal', '<=', $sampai);
            return $q;
        };

        $mode = $santri ? 'anak' : ($kelas ? 'kelas' : null);
        $rows = collect();
        $detail = null;

        if ($mode === 'kelas') {
            $list = Santri::aktif()->anggotaKelas($kelas->id)
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);
            $ids = $list->pluck('id');
            $hafalan = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
            $juz = HafalanJuz::whereIn('santri_id', $ids)->get()->groupBy('santri_id');
            // Perolehan hafalan baru (ziyadah) dalam periode + jumlah murojaah per santri
            $ayatBaru = (clone $applyP(SetoranTahfidz::whereIn('santri_id', $ids)->where('jenis', 'ziyadah')))
                ->selectRaw('santri_id, SUM(jumlah_ayat) as a')->groupBy('santri_id')->pluck('a', 'santri_id');
            $murojaah = (clone $applyP(SetoranTahfidz::whereIn('santri_id', $ids)
                    ->whereIn('jenis', ['murojaah_wajib', 'murojaah_tambahan'])))
                ->selectRaw('santri_id, COUNT(*) as c')->groupBy('santri_id')->pluck('c', 'santri_id');
            // Ringkasan: rata-rata nilai + catatan terakhir (dalam periode) per santri
            $rataNilai = (clone $applyP(SetoranTahfidz::whereIn('santri_id', $ids)->whereNotNull('nilai')))
                ->selectRaw('santri_id, AVG(nilai) as r')->groupBy('santri_id')->pluck('r', 'santri_id');
            $catatanAkhir = $applyP(SetoranTahfidz::whereIn('santri_id', $ids)->whereNotNull('catatan'))
                ->orderByDesc('tanggal')->orderByDesc('id')->get(['santri_id', 'catatan'])
                ->groupBy('santri_id')->map(fn($g) => $g->first()->catatan);
            $rows = $list->values()->map(function ($s, $i) use ($hafalan, $juz, $totalQ, $ayatBaru, $murojaah, $rataNilai, $catatanAkhir) {
                $h = $hafalan->get($s->id); $jz = $juz->get($s->id) ?? collect();
                $total = $h?->total_ayat ?? 0;
                return [
                    'no'          => $i + 1,
                    'nama'        => $s->nama_lengkap,
                    'nip'         => $s->nip,
                    'total_ayat'  => $total,
                    'persen'      => $totalQ > 0 ? round($total / $totalQ * 100, 2) : 0,
                    'juz_selesai' => $jz->whereIn('status', ['selesai', 'tasmi_lulus'])->count(),
                    'ayat_baru'   => (int) ($ayatBaru[$s->id] ?? 0),
                    'murojaah'    => (int) ($murojaah[$s->id] ?? 0),
                    'rata_nilai'  => isset($rataNilai[$s->id]) ? round((float) $rataNilai[$s->id], 1) : null,
                    'catatan_terakhir' => $catatanAkhir[$s->id] ?? null,
                ];
            });
        } elseif ($mode === 'anak') {
            $svc = new TahfidzService();
            $status = $svc->statusSantri($santri->id);
            $namaSurah = Surah::pluck('nama', 'nomor');
            $juzMap = HafalanJuz::where('santri_id', $santri->id)->get()->keyBy('juz');
            $juzGrid = collect(range(1, 30))->map(fn($n) => [
                'juz' => $n, 'status' => $juzMap->get($n)?->status ?? 'belum',
            ]);

            $setoran = $applyP(SetoranTahfidz::where('santri_id', $santri->id)->with('tenagaPendidik.user:id,name'))
                ->orderByDesc('tanggal')->orderByDesc('id')->get();

            // Rekap per jenis (jumlah setoran, ayat, rata-rata nilai) dalam periode
            $recap = collect(['ziyadah', 'murojaah_wajib', 'murojaah_tambahan', 'tasmi'])->map(function ($j) use ($setoran) {
                $grp = $setoran->where('jenis', $j);
                $bernilai = $grp->whereNotNull('nilai');
                return [
                    'jenis' => $j,
                    'label' => $this->jenisLabel($j),
                    'count' => $grp->count(),
                    'ayat'  => (int) $grp->sum('jumlah_ayat'),
                    'rata'  => $bernilai->count() ? round($bernilai->avg('nilai'), 1) : null,
                ];
            });

            $riwayat = $setoran->take(120)->map(fn($r) => [
                'tanggal' => Carbon::parse($r->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'jenis'   => $r->jenis,
                'label'   => $this->jenisLabel($r->jenis),
                'rentang' => ($namaSurah[$r->surah_mulai] ?? '?') . ' ' . $r->ayat_mulai
                    . ' – ' . ($namaSurah[$r->surah_selesai] ?? '?') . ' ' . $r->ayat_selesai,
                'jumlah_ayat' => $r->jumlah_ayat, 'juz' => $r->juz_mulai,
                'nilai' => $r->nilai, 'lulus' => $r->lulus,
                'catatan' => $r->catatan,
                'guru'    => $r->tenagaPendidik?->user?->name ?? '—',
                // Untuk tasmi', guru pencatat = penguji yang ditunjuk.
                'penguji' => $r->jenis === 'tasmi' ? ($r->tenagaPendidik?->user?->name ?? '—') : null,
            ])->values();

            $detail = [
                'santri'    => ['nama' => $santri->nama_lengkap, 'nip' => $santri->nip],
                'status'    => $status,
                'juz_grid'  => $juzGrid,
                'recap'     => $recap,
                'ayat_baru' => (int) $setoran->where('jenis', 'ziyadah')->sum('jumlah_ayat'),
                'riwayat'   => $riwayat,
            ];
        }

        return Inertia::render('Admin/SmartEducation/Laporan/Tahfidz', array_merge($this->kopPayload(), [
            'mode'         => $mode,
            'kelas'        => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
            'rows'         => $rows,
            'detail'       => $detail,
            'filter'       => ['kelas_id' => $kelasId, 'santri_id' => $santriId, 'ta_id' => $taId],
            'periodeLabel' => $periodeLabel,
            'kelasOpsi'    => Kelas::aktif()->tahfidz()->orderBy('nama')->get(['id', 'nama']),
            'santriOpsi'   => $this->santriOpsi($kelasId),
            'tahunAjaranOpsi' => $this->tahunAjaranOpsi(),
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // LAPORAN KEHADIRAN PEMBELAJARAN SANTRI — rekap bulanan absensi santri
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Berbeda dengan Laporan Jurnal (sisi guru: sesi, materi, JP), laporan ini
     * menjawab pertanyaan wali & pimpinan: berapa kali santri ini benar-benar
     * mengikuti pembelajaran bulan ini, dan berapa persennya.
     *
     * Tiga tingkat tampilan memakai satu sumber hitungan yang sama
     * (RekapKehadiranSantriService), sehingga total di ringkasan selalu
     * rekonsiliasi dengan rincian di bawahnya:
     *   - tanpa filter      → peringkat seluruh kelas;
     *   - kelas dipilih     → daftar santri kelas tersebut;
     *   - santri dipilih    → rincian per kelas/mapel + daftar ketidakhadiran.
     */
    public function kehadiranSantri(Request $request, RekapKehadiranSantriService $svc)
    {
        $bulan    = (int) ($request->bulan ?: Carbon::today()->month);
        $tahun    = (int) ($request->tahun ?: Carbon::today()->year);
        $kelasId  = $request->kelas_id  ? (int) $request->kelas_id  : null;
        $santriId = $request->santri_id ? (int) $request->santri_id : null;

        $kelas  = $kelasId  ? Kelas::find($kelasId)   : null;
        $santri = $santriId ? Santri::find($santriId) : null;
        $mode   = $santri ? 'anak' : ($kelas ? 'kelas' : 'ringkas');

        // Filter santri berlaku lintas kelas: seorang santri lazimnya mengikuti
        // kelas reguler sekaligus kelas tahfidz/tahsin, dan laporannya harus
        // memuat seluruh pembelajaran yang ia ikuti.
        $baris = $svc->baris($tahun, $bulan, $santri ? null : $kelasId, $santriId);

        $namaSantri = Santri::whereIn('id', $baris->pluck('santri_id')->unique())
            ->get(['id', 'nip', 'nama_lengkap'])->keyBy('id');

        $perKelas  = $mode === 'ringkas' ? $svc->perKelas($baris) : collect();
        $perSantri = $mode === 'kelas'   ? $svc->perSantri($baris, $namaSantri) : collect();

        $detail = null;
        if ($mode === 'anak') {
            $detail = [
                'santri' => [
                    'nama' => $santri->nama_lengkap,
                    'nip'  => $santri->nip,
                ],
                'total'     => $svc->hitung($baris),
                'sesi'      => $baris->pluck('sesi_id')->unique()->count(),
                'per_kelas' => $baris->whereNotNull('kelas_id')->groupBy('kelas_id')
                    ->map(fn($g, $kid) => array_merge([
                        'kelas' => $g->first()->kelas_nama ?? ('Kelas ' . $kid),
                        'mapel' => $g->pluck('mapel_nama')->filter()->unique()->values()->all(),
                    ], $svc->hitung($g)))
                    ->sortBy('persen_efektif')->values(),
                // Hanya ketidakhadiran yang dirinci — itulah yang perlu ditindak;
                // kehadiran sudah terwakili angka totalnya.
                'ketidakhadiran' => $baris->whereIn('status', ['alpha', 'izin', 'sakit'])
                    ->sortByDesc('tanggal')->values()
                    ->map(fn($r) => [
                        'tanggal' => Carbon::parse($r->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                        'kelas'   => $r->kelas_nama ?? '—',
                        'mapel'   => $r->mapel_nama ?? '—',
                        'guru'    => $r->guru_nama ?? '—',
                        'status'  => $r->status,
                    ]),
            ];
        }

        $rekapTotal = $svc->hitung($baris);

        return Inertia::render('Admin/SmartEducation/Laporan/KehadiranSantri', array_merge($this->kopPayload(), [
            'mode'   => $mode,
            'kelas'  => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
            'filter' => [
                'bulan'     => $bulan,
                'tahun'     => $tahun,
                'kelas_id'  => $kelasId,
                'santri_id' => $santriId,
            ],
            'periodeLabel' => Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY'),
            'ringkasan'    => array_merge($rekapTotal, [
                'sesi'   => $baris->pluck('sesi_id')->unique()->count(),
                'santri' => $baris->pluck('santri_id')->unique()->count(),
                'kelas'  => $baris->pluck('kelas_id')->filter()->unique()->count(),
            ]),
            'perKelas'   => $perKelas,
            'perSantri'  => $perSantri,
            'detail'     => $detail,
            'bulanOpsi'  => collect(range(1, 12))->map(fn($b) => [
                'value' => $b,
                'label' => Carbon::create(2000, $b, 1)->locale('id')->isoFormat('MMMM'),
            ]),
            'tahunOpsi'  => collect(range(Carbon::today()->year - 2, Carbon::today()->year + 1)),
            'kelasOpsi'  => Kelas::aktif()->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'santriOpsi' => $this->santriOpsi($kelasId),
            'ambang'     => [
                'rajin'     => RekapKehadiranSantriService::AMBANG_RAJIN,
                'perhatian' => RekapKehadiranSantriService::AMBANG_PERHATIAN,
            ],
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // REKAP MENGAJAR GURU TAHFIDZ & TAHSIN — harian / mingguan / bulanan
    // ══════════════════════════════════════════════════════════════════════
    public function mengajarQuran(Request $request, \App\Services\RekapMengajarQuranService $svc)
    {
        [$mode, $mulai, $selesai, $label, $filterPeriode] = $this->periodeRekap($request);
        $tipe   = in_array($request->tipe, \App\Services\RekapMengajarQuranService::TIPE, true) ? $request->tipe : null;
        $guruId = $request->guru_id ? (int) $request->guru_id : null;

        $rekap  = $svc->rekap($mulai, $selesai, $tipe, $guruId);
        $detail = $guruId ? $svc->detail($guruId, $mulai, $selesai, $tipe) : null;

        // Guru yang relevan: pemegang jadwal tahfidz/tahsin aktif (+ yang muncul di rekap sbg inval).
        $guruOpsi = TenagaPendidik::aktif()->with('user:id,name')
            ->whereHas('jadwalMengajar', fn ($j) => $j->where('is_aktif', true)
                ->whereHas('mataPelajaran', fn ($m) => $m->whereIn('tipe', ['tahfidz', 'tahsin'])))
            ->get()->map(fn ($g) => ['id' => $g->id, 'nama' => $g->user?->name])
            ->filter(fn ($g) => $g['nama'])->sortBy('nama')->values();

        return Inertia::render('Admin/SmartEducation/Laporan/MengajarQuran', array_merge($this->kopPayload(), [
            'filter'       => array_merge($filterPeriode, ['tipe' => $tipe, 'guru_id' => $guruId]),
            'periodeLabel' => $label,
            'baris'        => $rekap['baris'],
            'total'        => $rekap['total'],
            'detail'       => $detail,
            'guru'         => $guruId ? ['id' => $guruId, 'nama' => TenagaPendidik::with('user:id,name')->find($guruId)?->user?->name] : null,
            'guruOpsi'     => $guruOpsi,
        ]));
    }

    /** Periode rekap: harian (rentang), mingguan (Senin–Minggu), bulanan. */
    private function periodeRekap(Request $request): array
    {
        $mode = in_array($request->mode, ['harian', 'mingguan', 'bulanan'], true) ? $request->mode : 'bulanan';
        $hariIni = Carbon::today();

        if ($mode === 'harian') {
            $a = $request->filled('dari') ? Carbon::parse($request->dari) : $hariIni->copy();
            $b = $request->filled('sampai') ? Carbon::parse($request->sampai) : $a->copy();
            if ($b->lt($a)) [$a, $b] = [$b, $a];
            if ($a->diffInDays($b) > 92) $b = $a->copy()->addDays(92);
            $label = $a->isSameDay($b)
                ? $a->locale('id')->isoFormat('dddd, D MMMM YYYY')
                : $a->locale('id')->isoFormat('D MMM YYYY') . ' – ' . $b->locale('id')->isoFormat('D MMM YYYY');
            return [$mode, $a->startOfDay(), $b->endOfDay(), $label,
                ['mode' => $mode, 'dari' => $a->toDateString(), 'sampai' => $b->toDateString()]];
        }

        if ($mode === 'mingguan') {
            $t = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : $hariIni->copy();
            $a = $t->copy()->startOfWeek(Carbon::MONDAY);
            $b = $t->copy()->endOfWeek(Carbon::SUNDAY);
            return [$mode, $a, $b,
                'Minggu ' . $a->locale('id')->isoFormat('D MMM') . ' – ' . $b->locale('id')->isoFormat('D MMM YYYY'),
                ['mode' => $mode, 'tanggal' => $t->toDateString()]];
        }

        $bulan = (int) ($request->bulan ?: $hariIni->month);
        $tahun = (int) ($request->tahun ?: $hariIni->year);
        $a = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        return [$mode, $a, $a->copy()->endOfMonth(), $a->locale('id')->isoFormat('MMMM YYYY'),
            ['mode' => $mode, 'bulan' => $bulan, 'tahun' => $tahun]];
    }

    private function jenisLabel(string $j): string
    {
        return [
            'ziyadah'           => 'Hafalan Baru',
            'murojaah_wajib'    => 'Murojaah Wajib',
            'murojaah_tambahan' => 'Murojaah Tambahan',
            'tasmi'             => "Tasmi'",
        ][$j] ?? ucfirst(str_replace('_', ' ', $j));
    }

    // ══════════════════════════════════════════════════════════════════════
    // LAPORAN TAHSIN — per kelas / per anak
    // ══════════════════════════════════════════════════════════════════════
    public function tahsin(Request $request)
    {
        $kelasId  = $request->kelas_id ? (int) $request->kelas_id : null;
        $santriId = $request->santri_id ? (int) $request->santri_id : null;
        $taId     = $request->ta_id ? (int) $request->ta_id : null;
        $kelas    = $kelasId ? Kelas::find($kelasId) : null;
        $santri   = $santriId ? Santri::find($santriId) : null;

        [, $dari, $sampai, $periodeLabel] = $this->resolvePeriode($taId);
        $applyP = function ($q) use ($dari, $sampai) {
            if ($dari)   $q->whereDate('tanggal', '>=', $dari);
            if ($sampai) $q->whereDate('tanggal', '<=', $sampai);
            return $q;
        };

        $mode = $santri ? 'anak' : ($kelas ? 'kelas' : null);
        $rows = collect();
        $detail = null;

        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');

        if ($mode === 'kelas') {
            $list = Santri::aktif()->anggotaKelas($kelas->id)
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'tahsin_level']);
            $ids = $list->pluck('id');
            $lulus = TahsinPenilaian::whereIn('santri_id', $ids)->where('lulus', true)
                ->selectRaw('santri_id, level, COUNT(*) as jml')->groupBy('santri_id', 'level')->get();
            // Ringkasan: rata nilai (periode) + catatan terakhir (dari log riwayat) per santri
            $rataNilai = $applyP(\App\Models\TahsinPenilaianRiwayat::whereIn('santri_id', $ids)->whereNotNull('nilai'))
                ->selectRaw('santri_id, AVG(nilai) as r')->groupBy('santri_id')->pluck('r', 'santri_id');
            $catatanAkhir = $applyP(\App\Models\TahsinPenilaianRiwayat::whereIn('santri_id', $ids)->whereNotNull('catatan'))
                ->orderByDesc('tanggal')->orderByDesc('id')->get(['santri_id', 'catatan'])
                ->groupBy('santri_id')->map(fn($g) => $g->first()->catatan);
            $rows = $list->values()->map(function ($s, $i) use ($materiTotal, $lulus, $rataNilai, $catatanAkhir) {
                $lv = $s->tahsin_level ?? 1;
                $total = (int) ($materiTotal[$lv] ?? 0);
                $sudah = (int) ($lulus->where('santri_id', $s->id)->where('level', $lv)->first()->jml ?? 0);
                return [
                    'no' => $i + 1, 'nama' => $s->nama_lengkap, 'nip' => $s->nip, 'level' => $lv,
                    'materi_total' => $total, 'materi_lulus' => $sudah,
                    'level_selesai' => $total > 0 && $sudah >= $total,
                    'rata_nilai' => isset($rataNilai[$s->id]) ? round((float) $rataNilai[$s->id], 1) : null,
                    'catatan_terakhir' => $catatanAkhir[$s->id] ?? null,
                ];
            });
        } elseif ($mode === 'anak') {
            $level = $santri->tahsin_level ?? 1;
            $lulusLv = TahsinPenilaian::where('santri_id', $santri->id)->where('lulus', true)
                ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');
            // Rekap nilai lintas level (dari LOG riwayat) untuk evaluasi jangka panjang.
            $statLv = \App\Models\TahsinPenilaianRiwayat::where('santri_id', $santri->id)
                ->selectRaw('level, COUNT(*) as c, AVG(nilai) as r')->groupBy('level')->get()->keyBy('level');
            $levelGrid = collect(range(1, \App\Services\TahsinService::LEVEL_MAX))->map(fn($lv) => [
                'level' => $lv, 'label' => \App\Services\TahsinService::levelLabel($lv),
                'total' => (int) ($materiTotal[$lv] ?? 0), 'lulus' => (int) ($lulusLv[$lv] ?? 0),
                'penilaian' => (int) ($statLv[$lv]->c ?? 0),
                'rata' => isset($statLv[$lv]->r) ? round((float) $statLv[$lv]->r, 1) : null,
                'status' => $lv < $level ? 'lewat' : ($lv === $level ? 'berjalan' : 'belum'),
            ]);
            // Riwayat = LOG penuh (setiap perubahan nilai tersimpan, untuk evaluasi).
            $riwayat = $applyP(\App\Models\TahsinPenilaianRiwayat::where('santri_id', $santri->id)
                    ->with(['materi:id,nama', 'tenagaPendidik.user:id,name']))
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(150)->get()
                ->map(fn($p) => [
                    'tanggal' => Carbon::parse($p->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                    'level' => $p->level, 'materi' => $p->materi?->nama ?? '—', 'nilai' => $p->nilai, 'lulus' => $p->lulus,
                    'catatan' => $p->catatan,
                    'guru'  => $p->tenagaPendidik?->user?->name ?? '—',
                ])->values();
            $nilaiAda = $riwayat->whereNotNull('nilai');
            $svcTahsin = new TahsinService();
            $detail = ['santri' => ['nama' => $santri->nama_lengkap, 'nip' => $santri->nip, 'level' => $level],
                'materi' => $svcTahsin->materiSantri($santri->id, $level),
                'materi_tambahan' => collect($svcTahsin->materiTambahanSantri($santri->id, 60))->map(fn($t) => $t + [
                    'tanggal_label' => $t['tanggal'] ? Carbon::parse($t['tanggal'])->locale('id')->isoFormat('dd, D MMM YYYY') : null,
                ])->values(),
                'level_grid' => $levelGrid, 'riwayat' => $riwayat,
                'rekap' => [
                    'penilaian' => $riwayat->count(),
                    'lulus'     => $riwayat->where('lulus', true)->count(),
                    'rata'      => $nilaiAda->count() ? round($nilaiAda->avg('nilai'), 1) : null,
                ]];
        }

        return Inertia::render('Admin/SmartEducation/Laporan/Tahsin', array_merge($this->kopPayload(), [
            'mode'         => $mode,
            'kelas'        => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama, 'level' => $kelas->level_tahsin] : null,
            'rows'         => $rows,
            'detail'       => $detail,
            'filter'       => ['kelas_id' => $kelasId, 'santri_id' => $santriId, 'ta_id' => $taId],
            'periodeLabel' => $periodeLabel,
            'kelasOpsi'    => Kelas::aktif()->tahsin()->orderBy('nama')->get(['id', 'nama']),
            'santriOpsi'   => $this->santriOpsi($kelasId),
            'tahunAjaranOpsi' => $this->tahunAjaranOpsi(),
        ]));
    }
}
