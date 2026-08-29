<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\JadwalMengajar;
use App\Models\HariLibur;
use App\Models\KoreksiAbsensi;
use App\Models\PenugasanTambahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\AbsensiKalkulasiService;
use App\Services\TimezoneHelper;
use Carbon\Carbon;

class AbsensiController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.smart-payroll.absensi.harian');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABSENSI HARIAN
    // ══════════════════════════════════════════════════════════════════════════

    public function harian(Request $request)
    {
        $tanggal   = TimezoneHelper::tanggalDariRequest($request->tanggal);
        $jabatanId = $request->jabatan_id;
        $search    = $request->search;

        // Semua guru aktif
        $guruAktif = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->when($jabatanId, fn($q) => $q->where('jabatan_id', $jabatanId))
            ->when($search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->get();

        // Ambil absensi yang sudah ada hari ini
        $absensiAda = AbsensiHarian::with(['tenagaPendidik.user', 'dikoreksiOleh'])
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $guruAktif->pluck('id'))
            ->get()
            ->keyBy('tenaga_pendidik_id');

        // Cek hari libur
        $hariLibur = HariLibur::aktif()
            ->where('tanggal', '<=', $tanggal->toDateString())
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $tanggal->toDateString())
            )->first();

        // Ambil jadwal kerja untuk kalkulasi otomatis
        $namaHari = TimezoneHelper::namaHariDB($tanggal);
        $jamKerja = \App\Models\SettingJamKerja::getDefault();
        $jadwal   = $jamKerja?->getJamUntukHari($namaHari);

        // Bangun data per guru (termasuk yang belum absen)
        $data = $guruAktif->map(function ($guru) use ($absensiAda, $tanggal, $jadwal) {
            $absensi = $absensiAda->get($guru->id);

            // ── Kalkulasi status & terlambat otomatis ─────────────────────
            $status         = $absensi?->status ?? 'belum';
            $menitTerlambat = (int) ($absensi?->menit_terlambat ?? 0);

            // Hanya kalkulasi ulang jika ada jam masuk dan belum dikoreksi manual
            if ($absensi && $absensi->jam_masuk && !$absensi->is_koreksi && $jadwal) {
                $hasil          = AbsensiKalkulasiService::hitungStatus(
                    $absensi->jam_masuk, $tanggal->toDateString()
                );
                $status         = $hasil['status'];
                $menitTerlambat = $hasil['menit_terlambat'];

                // Simpan ke DB jika berbeda (sync otomatis)
                if ($status !== $absensi->status || $menitTerlambat !== ($absensi->menit_terlambat ?? 0)) {
                    $absensi->updateQuietly([
                        'status'          => $status,
                        'menit_terlambat' => $menitTerlambat,
                    ]);
                }
            }

            // ── Format jam H:i ─────────────────────────────────────────────
            $jamMasukFmt  = AbsensiKalkulasiService::formatJam($absensi?->jam_masuk);
            $jamPulangFmt = AbsensiKalkulasiService::formatJam($absensi?->jam_pulang);
            $labelTerlambat = AbsensiKalkulasiService::labelTerlambat($menitTerlambat);

            // ── Durasi kerja ───────────────────────────────────────────────
            $durasiLabel = null;
            if ($absensi?->jam_masuk && $absensi?->jam_pulang) {
                $masuk  = Carbon::parse($tanggal->toDateString().' '.$absensi->jam_masuk);
                $pulang = Carbon::parse($tanggal->toDateString().' '.$absensi->jam_pulang);
                $dur    = (int) $masuk->diffInMinutes($pulang);
                $dj     = (int) floor($dur / 60);
                $dm     = $dur % 60;
                $durasiLabel = "{$dj}j {$dm}m";
            }

            return [
                'id'               => $guru->id,
                'absensi_id'       => $absensi?->id,
                'nama'             => $guru->user->name,
                'nip'              => $guru->nip,
                'foto'             => $guru->user->foto ? asset('storage/'.$guru->user->foto) : null,
                'jabatan'          => $guru->jabatan?->nama_jabatan ?? '—',
                'jabatan_id'       => $guru->jabatan_id,
                // Absensi — kalkulasi otomatis
                'status'           => $status,
                'jam_masuk'        => $jamMasukFmt,      // H:i
                'jam_pulang'       => $jamPulangFmt,     // H:i
                'menit_terlambat'  => $menitTerlambat,
                'label_terlambat'  => $labelTerlambat,   // "1 jam 30 menit"
                'durasi_label'     => $durasiLabel,       // "6j 30m"
                'keterangan'       => $absensi?->keterangan,
                'is_koreksi'       => $absensi?->is_koreksi ?? false,
                'sudah_absen'      => $absensi !== null,
                // Info jadwal untuk referensi
                'jadwal_masuk'     => $jadwal['jam_masuk'] ?? null,
                'jadwal_pulang'    => $jadwal['jam_pulang'] ?? null,
                'toleransi'        => $jadwal['toleransi'] ?? 15,
            ];
        });

        $ringkasan = [
            'hadir'      => $data->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])->count(),
            'terlambat'  => $data->where('status', 'terlambat')->count(),
            'izin'       => $data->whereIn('status', ['izin', 'izin_sakit'])->count(),
            'sakit'      => $data->where('status', 'sakit')->count(),
            'alfa'       => $data->where('status', 'alfa')->count(),
            'libur'      => $data->where('status', 'libur')->count(),
            'belum'      => $data->where('status', 'belum')->count(),
            'total'      => $data->count(),
        ];

        return Inertia::render('Admin/SmartPayroll/Absensi/Harian', [
            'absensi'    => $data->values(),
            'ringkasan'  => $ringkasan,
            'tanggal'    => $tanggal->format('Y-m-d'),
            'hari'       => $tanggal->locale('id')->isoFormat('dddd, D MMMM Y'),
            'hari_libur' => $hariLibur ? ['nama' => $hariLibur->nama, 'id' => $hariLibur->id] : null,
            'jadwal'     => $jadwal ? [
                'jam_masuk'  => $jadwal['jam_masuk'],
                'jam_pulang' => $jadwal['jam_pulang'],
                'toleransi'  => $jadwal['toleransi'] ?? 15,
            ] : null,
            'jabatan'    => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'filters'    => $request->only(['tanggal', 'jabatan_id', 'search']),
        ]);
    }

    /**
     * Input / update absensi harian manual oleh superadmin.
     */
    public function storeHarian(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'required|date',
            'status'             => 'required|in:hadir,terlambat,izin,izin_sakit,sakit,alfa,libur,dinas_luar',
            'jam_masuk'          => 'nullable|date_format:H:i',
            'jam_pulang'         => 'nullable|date_format:H:i',
            'menit_terlambat'    => 'nullable|integer|min:0',
            'keterangan'         => 'nullable|string|max:500',
        ]);

        AbsensiHarian::updateOrCreate(
            [
                'tenaga_pendidik_id' => $data['tenaga_pendidik_id'],
                'tanggal'            => $data['tanggal'],
            ],
            array_merge($data, [
                'is_koreksi'     => true,
                'dikoreksi_oleh' => Auth::id(),
            ])
        );

        return back()->with('success', 'Absensi berhasil disimpan.')->with('preserveScroll', true);
    }

    /**
     * Input absensi massal (tandai semua yang belum absen).
     */
    public function massal(Request $request)
    {
        $data = $request->validate([
            'tanggal'    => 'required|date',
            'status'     => 'required|in:hadir,alfa,libur',
            'guru_ids'   => 'required|array',
            'guru_ids.*' => 'exists:tenaga_pendidik,id',
        ]);

        $jumlah = 0;
        foreach ($data['guru_ids'] as $guruId) {
            AbsensiHarian::firstOrCreate(
                ['tenaga_pendidik_id' => $guruId, 'tanggal' => $data['tanggal']],
                [
                    'status'         => $data['status'],
                    'is_koreksi'     => true,
                    'dikoreksi_oleh' => Auth::id(),
                ]
            );
            $jumlah++;
        }

        return back()->with('success', "{$jumlah} absensi berhasil di-input massal.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABSENSI MENGAJAR
    // ══════════════════════════════════════════════════════════════════════════

    public function mengajar(Request $request)
    {
        $tanggal = $request->tanggal ? Carbon::parse($request->tanggal) : Carbon::today();
        $search  = $request->search;

        // Jadwal mengajar hari ini berdasarkan nama hari
        $namaHari = TimezoneHelper::namaHariDB($tanggal);

        $jadwalHariIni = JadwalMengajar::with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'mataPelajaran',
                'tahunAjaran',
            ])
            ->where('hari', $namaHari)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->when($search, fn($q) => $q->whereHas('tenagaPendidik.user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->get();

        // Absensi mengajar yang sudah ada
        $absensiAda = AbsensiMengajar::whereDate('tanggal', $tanggal)
            ->with('digantikanOleh.user:id,name')
            ->get()
            ->keyBy('jadwal_mengajar_id');

        $data = $jadwalHariIni->map(function ($jadwal) use ($absensiAda, $tanggal) {
            $absensi = $absensiAda->get($jadwal->id);
            return [
                'jadwal_id'      => $jadwal->id,
                'absensi_id'     => $absensi?->id,
                'nama'           => $jadwal->tenagaPendidik->user->name,
                'foto'           => $jadwal->tenagaPendidik->user->foto
                    ? asset('storage/'.$jadwal->tenagaPendidik->user->foto) : null,
                'jabatan'        => $jadwal->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? '—',
                'kelas'          => $jadwal->kelas,
                'ruangan'        => $jadwal->ruangan,
                'jam_mulai'      => $jadwal->jam_mulai,
                'jam_selesai'    => $jadwal->jam_selesai,
                'jumlah_jp'      => $jadwal->jumlah_jp,
                // Status absensi
                'status'           => $absensi?->status ?? 'belum',
                'jam_mulai_aktual' => $absensi?->jam_mulai_aktual,
                'jp_terlaksana'    => $absensi?->jp_terlaksana,
                'materi'           => $absensi?->materi,
                'sudah_absen'      => $absensi !== null,
                'is_koreksi'       => $absensi?->is_koreksi ?? false,
                // Info pengganti (status = pengganti)
                'digantikan_oleh'  => $absensi?->digantikan_oleh,
                'pengganti_nama'   => $absensi?->digantikanOleh?->user?->name,
                'keterangan'       => $absensi?->keterangan,
            ];
        });

        $ringkasan = [
            'total'         => $data->count(),
            'terlaksana'    => $data->whereIn('status', ['hadir', 'terlaksana'])->count(),
            'pengganti'     => $data->where('status', 'pengganti')->count(),
            'izin'          => $data->where('status', 'izin')->count(),
            'tidak'         => $data->where('status', 'tidak_terlaksana')->count(),
            'belum'         => $data->where('status', 'belum')->count(),
            'total_jp'      => $data->sum('jumlah_jp'),
            'jp_terlaksana' => $data->sum('jp_terlaksana'),
        ];

        return Inertia::render('Admin/SmartPayroll/Absensi/Mengajar', [
            'absensi'   => $data->values(),
            'ringkasan' => $ringkasan,
            'tanggal'   => $tanggal->format('Y-m-d'),
            'hari'      => $tanggal->locale('id')->isoFormat('dddd, D MMMM Y'),
            'filters'   => $request->only(['tanggal', 'search']),
            'guruOpsi'  => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])
                ->sortBy('nama')->values(),
        ]);
    }

    /**
     * Input / update absensi mengajar oleh superadmin.
     */
    public function storeMengajar(Request $request)
    {
        $data = $request->validate([
            'jadwal_mengajar_id'  => 'required|exists:jadwal_mengajar,id',
            'tanggal'             => 'required|date',
            'status'              => 'required|in:terlaksana,tidak_terlaksana,pengganti,libur,izin',
            'jam_mulai_aktual'    => 'nullable|date_format:H:i',
            'jam_selesai_aktual'  => 'nullable|date_format:H:i',
            'jp_terlaksana'       => 'nullable|integer|min:0',
            'materi'              => 'nullable|string|max:500',
            'keterangan'          => 'nullable|string|max:500',
            'digantikan_oleh'     => 'nullable|required_if:status,pengganti|exists:tenaga_pendidik,id',
        ]);

        $jadwal = JadwalMengajar::findOrFail($data['jadwal_mengajar_id']);

        AbsensiMengajar::updateOrCreate(
            [
                'jadwal_mengajar_id' => $data['jadwal_mengajar_id'],
                'tanggal'            => $data['tanggal'],
            ],
            array_merge($data, [
                'tenaga_pendidik_id' => $jadwal->tenaga_pendidik_id,
                'is_koreksi'         => true,
                'dikoreksi_oleh'     => Auth::id(),
            ])
        );

        return back()->with('success', 'Absensi mengajar berhasil disimpan.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REKAP BULANAN
    // ══════════════════════════════════════════════════════════════════════════

    public function rekap(Request $request)
    {
        $bulan     = (int) ($request->bulan ?? now()->month);
        $tahun     = (int) ($request->tahun ?? now()->year);
        $jabatanId = $request->jabatan_id;
        $search    = $request->search;

        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        // Hitung hari kerja efektif (kurangi hari libur aktif)
        $hariLibur = HariLibur::aktif()
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $mulai)
            )->get();

        $hariLiburSet = collect();
        foreach ($hariLibur as $hl) {
            $s  = Carbon::parse(max($mulai->toDateString(), $hl->tanggal->toDateString()));
            $e  = Carbon::parse(min($selesai->toDateString(), ($hl->tanggal_selesai ?? $hl->tanggal)->toDateString()));
            while ($s->lte($e)) { $hariLiburSet->push($s->toDateString()); $s->addDay(); }
        }
        $totalHariKerja = $mulai->diffInDays($selesai) + 1 - $hariLiburSet->unique()->count();

        $rekap = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->when($jabatanId, fn($q) => $q->where('jabatan_id', $jabatanId))
            ->when($search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->get()
            ->map(function ($guru) use ($bulan, $tahun, $mulai, $selesai, $totalHariKerja) {
                $absensiHarian = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
                    ->whereBetween('tanggal', [$mulai, $selesai])
                    ->get();

                $absensiMengajar = AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
                    ->whereBetween('tanggal', [$mulai, $selesai])
                    ->get();

                $tugasSelesai = PenugasanTambahan::where('tenaga_pendidik_id', $guru->id)
                    ->where('status_pengerjaan', 'selesai')
                    ->where('disetujui', true)
                    ->whereHas('tugasTambahan', fn($q) =>
                        $q->whereBetween('tanggal_mulai', [$mulai, $selesai])
                    )->count();

                $hadir     = $absensiHarian->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])->count();
                $alfa      = $absensiHarian->where('status', 'alfa')->count();
                $terlambat = $absensiHarian->where('status', 'terlambat')->count();
                $izin      = $absensiHarian->whereIn('status', ['izin', 'izin_sakit', 'dinas_luar'])->count();
                $sakit     = $absensiHarian->where('status', 'sakit')->count();
                $libur     = $absensiHarian->where('status', 'libur')->count();
                // JP mengajar sendiri + JP sebagai pengganti (selaras payroll).
                $jpSendiri   = (int) $absensiMengajar->whereIn('status', ['hadir', 'terlaksana'])->sum('jp_terlaksana');
                $jpPengganti = (int) AbsensiMengajar::where('digantikan_oleh', $guru->id)
                    ->whereBetween('tanggal', [$mulai, $selesai])
                    ->where('status', 'pengganti')->where('jp_terlaksana', '>', 0)
                    ->sum('jp_terlaksana');
                $jpMengajar  = $jpSendiri + $jpPengganti;

                // Persentase kehadiran
                $pct = $totalHariKerja > 0
                    ? round($hadir / $totalHariKerja * 100, 1)
                    : 0;

                return [
                    'id'              => $guru->id,
                    'nama'            => $guru->user->name,
                    'nip'             => $guru->nip,
                    'jabatan'         => $guru->jabatan?->nama_jabatan ?? '—',
                    'hadir'           => $hadir,
                    'terlambat'       => $terlambat,
                    'izin'            => $izin,
                    'sakit'           => $sakit,
                    'alfa'            => $alfa,
                    'libur'           => $libur,
                    'jp_mengajar'     => $jpMengajar,
                    'jp_pengganti'    => $jpPengganti,
                    'tugas_selesai'   => $tugasSelesai,
                    'total_hari_kerja'=> $totalHariKerja,
                    'pct_hadir'       => $pct,
                ];
            })
            ->sortByDesc('hadir')
            ->values();

        return Inertia::render('Admin/SmartPayroll/Absensi/Rekap', [
            'rekap'            => $rekap,
            'bulan'            => $bulan,
            'tahun'            => $tahun,
            'total_hari_kerja' => $totalHariKerja,
            'jabatan'          => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'filters'          => $request->only(['bulan', 'tahun', 'jabatan_id', 'search']),
            'summary' => [
                'total_guru'  => $rekap->count(),
                'avg_hadir'   => $rekap->count() ? round($rekap->avg('hadir'), 1) : 0,
                'total_alfa'  => $rekap->sum('alfa'),
                'total_jp'    => $rekap->sum('jp_mengajar'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // KOREKSI ABSENSI
    // ══════════════════════════════════════════════════════════════════════════

    public function koreksi(Request $request)
    {
        $search    = $request->search;
        $guruId    = $request->guru_id;
        $bulan     = $request->bulan ?? now()->month;
        $tahun     = $request->tahun ?? now()->year;

        $log = KoreksiAbsensi::with(['tenagaPendidik.user', 'dikoreksiOleh'])
            ->when($guruId, fn($q) => $q->where('tenaga_pendidik_id', $guruId))
            ->when($bulan, fn($q) => $q->whereMonth('tanggal', $bulan))
            ->when($tahun, fn($q) => $q->whereYear('tanggal', $tahun))
            ->when($search, fn($q) => $q->whereHas('tenagaPendidik.user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn($k) => [
                'id'              => $k->id,
                'nama'            => $k->tenagaPendidik->user->name,
                'tanggal'         => $k->tanggal?->format('d M Y'),
                'tipe_absensi'    => $k->tipe_absensi,
                'field_dikoreksi' => $k->field_dikoreksi,
                'nilai_lama'      => $k->nilai_lama,
                'nilai_baru'      => $k->nilai_baru,
                'alasan'          => $k->alasan,
                'status'          => $k->status,
                'dikoreksi_oleh'  => $k->dikoreksiOleh?->name,
                'waktu'           => $k->created_at?->format('d M Y H:i'),
            ]);

        $guru = TenagaPendidik::aktif()->with('user')
            ->get()->map(fn($g) => ['id' => $g->id, 'nama' => $g->user->name]);

        return Inertia::render('Admin/SmartPayroll/Absensi/Koreksi', [
            'log'     => $log,
            'guru'    => $guru,
            'filters' => $request->only(['search', 'guru_id', 'bulan', 'tahun']),
            'bulan'   => (int) $bulan,
            'tahun'   => (int) $tahun,
        ]);
    }

    /**
     * Koreksi / update absensi harian.
     */
    public function koreksiHarian(Request $request, AbsensiHarian $absensi)
    {
        $data = $request->validate([
            'status'          => 'required|in:hadir,terlambat,izin,izin_sakit,sakit,alfa,libur,dinas_luar',
            'jam_masuk'       => 'nullable|date_format:H:i',
            'jam_pulang'      => 'nullable|date_format:H:i',
            'menit_terlambat' => 'nullable|integer|min:0',
            'keterangan'      => 'nullable|string|max:500',
            'alasan_koreksi'  => 'required|string|max:500',
        ]);

        $nilaiLama = $absensi->status;

        // Simpan log koreksi
        KoreksiAbsensi::create([
            'tenaga_pendidik_id' => $absensi->tenaga_pendidik_id,
            'tanggal'            => $absensi->tanggal,
            'tipe_absensi'       => 'harian',
            'absensi_harian_id'  => $absensi->id,
            'field_dikoreksi'    => 'status',
            'nilai_lama'         => $nilaiLama,
            'nilai_baru'         => $data['status'],
            'alasan'             => $data['alasan_koreksi'],
            'status'             => 'disetujui',
            'dikoreksi_oleh'     => Auth::id(),
        ]);

        // Update absensi
        $absensi->update([
            'status'          => $data['status'],
            'jam_masuk'       => $data['jam_masuk'] ?? $absensi->jam_masuk,
            'jam_pulang'      => $data['jam_pulang'] ?? $absensi->jam_pulang,
            'menit_terlambat' => $data['menit_terlambat'] ?? $absensi->menit_terlambat,
            'keterangan'      => $data['keterangan'] ?? $absensi->keterangan,
            'is_koreksi'      => true,
            'dikoreksi_oleh'  => Auth::id(),
        ]);

        return back()->with('success', 'Absensi berhasil dikoreksi.')->with('preserveScroll', true);
    }

    /**
     * Koreksi absensi mengajar.
     */
    public function koreksiMengajar(Request $request, AbsensiMengajar $absensi)
    {
        $data = $request->validate([
            'status'             => 'required|in:terlaksana,tidak_terlaksana,pengganti,libur,izin',
            'jp_terlaksana'      => 'nullable|integer|min:0',
            'jam_mulai_aktual'   => 'nullable|date_format:H:i',
            'jam_selesai_aktual' => 'nullable|date_format:H:i',
            'materi'             => 'nullable|string|max:500',
            'keterangan'         => 'nullable|string|max:500',
            'digantikan_oleh'    => 'nullable|required_if:status,pengganti|exists:tenaga_pendidik,id',
            'alasan_koreksi'     => 'required|string|max:500',
        ]);

        KoreksiAbsensi::create([
            'tenaga_pendidik_id'  => $absensi->tenaga_pendidik_id,
            'tanggal'             => $absensi->tanggal,
            'tipe_absensi'        => 'mengajar',
            'absensi_mengajar_id' => $absensi->id,
            'field_dikoreksi'     => 'status',
            'nilai_lama'          => $absensi->status,
            'nilai_baru'          => $data['status'],
            'alasan'              => $data['alasan_koreksi'],
            'status'              => 'disetujui',
            'dikoreksi_oleh'      => Auth::id(),
        ]);

        $absensi->update([
            'status'             => $data['status'],
            'jp_terlaksana'      => $data['jp_terlaksana'] ?? $absensi->jp_terlaksana,
            'jam_mulai_aktual'   => $data['jam_mulai_aktual'] ?? $absensi->jam_mulai_aktual,
            'jam_selesai_aktual' => $data['jam_selesai_aktual'] ?? $absensi->jam_selesai_aktual,
            'materi'             => $data['materi'] ?? $absensi->materi,
            'keterangan'         => $data['keterangan'] ?? $absensi->keterangan,
            // Pengganti: bila bukan status pengganti, kosongkan; bila pengganti, pakai input.
            'digantikan_oleh'    => $data['status'] === 'pengganti'
                ? ($data['digantikan_oleh'] ?? $absensi->digantikan_oleh)
                : null,
            'is_koreksi'         => true,
            'dikoreksi_oleh'     => Auth::id(),
        ]);

        return back()->with('success', 'Absensi mengajar berhasil dikoreksi.');
    }

    /**
     * Insert absensi manual (guru tidak ada record).
     */
    public function insertManual(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'required|date',
            'status'             => 'required|in:hadir,terlambat,izin,izin_sakit,sakit,alfa,libur,dinas_luar',
            'jam_masuk'          => 'nullable|date_format:H:i',
            'jam_pulang'         => 'nullable|date_format:H:i',
            'menit_terlambat'    => 'nullable|integer|min:0',
            'keterangan'         => 'nullable|string|max:500',
            'alasan'             => 'required|string|max:500',
        ]);

        // Cegah duplikat
        $existing = AbsensiHarian::where([
            'tenaga_pendidik_id' => $data['tenaga_pendidik_id'],
            'tanggal'            => $data['tanggal'],
        ])->first();

        if ($existing) {
            return back()->with('error', 'Absensi sudah ada. Gunakan fitur koreksi.');
        }

        $absensi = AbsensiHarian::create(array_merge($data, [
            'is_koreksi'     => true,
            'dikoreksi_oleh' => Auth::id(),
        ]));

        KoreksiAbsensi::create([
            'tenaga_pendidik_id' => $data['tenaga_pendidik_id'],
            'tanggal'            => $data['tanggal'],
            'tipe_absensi'       => 'harian',
            'absensi_harian_id'  => $absensi->id,
            'field_dikoreksi'    => 'status',
            'nilai_lama'         => null,
            'nilai_baru'         => $data['status'],
            'alasan'             => $data['alasan'],
            'status'             => 'disetujui',
            'dikoreksi_oleh'     => Auth::id(),
        ]);

        return back()->with('success', 'Absensi manual berhasil ditambahkan.');
    }
}