<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\KoreksiAbsensi;
use App\Models\LogKerjaHarian;
use App\Models\PenugasanTambahan;
use App\Models\RealisasiTugasJabatan;
use App\Models\RekapKinerjaBulanan;
use App\Models\TenagaPendidik;
use App\Models\TugasJabatan;
use App\Models\TugasTambahan;
use App\Models\Jabatan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD MONITORING HARIAN
    // Satu halaman untuk melihat semua aktivitas tenaga pendidik hari ini
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $tanggal   = $request->tanggal ? Carbon::parse($request->tanggal) : Carbon::today();
        $jabatanId = $request->jabatan_id;
        $search    = $request->search;

        // Semua guru aktif
        $guru = TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'jabatanGuru.jabatan'])
            ->when($jabatanId, fn($q) => $q->where('jabatan_id', $jabatanId))
            ->when($search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->get();

        $guruIds = $guru->pluck('id');

        // Load semua data harian sekaligus (efisien)
        $absensiHarian = AbsensiHarian::whereIn('tenaga_pendidik_id', $guruIds)
            ->whereDate('tanggal', $tanggal)->get()->keyBy('tenaga_pendidik_id');

        $namaHari = strtolower($tanggal->locale('en')->dayName);
        $absensiMengajar = AbsensiMengajar::with('jadwalMengajar.mataPelajaran')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->whereDate('tanggal', $tanggal)->get()->groupBy('tenaga_pendidik_id');

        $logKerja = LogKerjaHarian::with('tugasJabatan')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->whereDate('tanggal', $tanggal)->get()->groupBy('tenaga_pendidik_id');

        $tugasTambahan = PenugasanTambahan::with('tugasTambahan')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->whereHas('tugasTambahan', fn($q) =>
                $q->where('tanggal_mulai', '<=', $tanggal)
                 ->where(fn($q2) => $q2->whereNull('tanggal_selesai')
                     ->orWhere('tanggal_selesai', '>=', $tanggal))
            )->get()->groupBy('tenaga_pendidik_id');

        $realisasiJabatan = RealisasiTugasJabatan::with('tugasJabatan')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->whereDate('tanggal', $tanggal)->get()->groupBy('tenaga_pendidik_id');

        // Jadwal mengajar hari ini (yang seharusnya ada)
        $jadwalHariIni = JadwalMengajar::with('mataPelajaran')
            ->where('hari', $namaHari)->where('is_aktif', true)
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->get()->groupBy('tenaga_pendidik_id');

        // Hari libur
        $hariLibur = HariLibur::aktif()
            ->where('tanggal', '<=', $tanggal)->where(fn($q) =>
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal)
            )->first();

        // Bangun data per guru
        $data = $guru->map(function ($g) use (
            $absensiHarian, $absensiMengajar, $logKerja, $tugasTambahan,
            $realisasiJabatan, $jadwalHariIni
        ) {
            $ah = $absensiHarian->get($g->id);
            $am = $absensiMengajar->get($g->id, collect());
            $lk = $logKerja->get($g->id, collect());
            $tt = $tugasTambahan->get($g->id, collect());
            $rj = $realisasiJabatan->get($g->id, collect());
            $jh = $jadwalHariIni->get($g->id, collect());

            // Skor aktivitas harian sederhana
            $skorAbsen  = $ah ? ($ah->status === 'hadir' ? 30 : ($ah->status === 'terlambat' ? 20 : 0)) : 0;
            $skorMengajar = $jh->count() > 0
                ? round($am->whereIn('status', ['hadir', 'terlaksana'])->count() / $jh->count() * 40)
                : 0;
            $skorLog    = min($lk->where('status', 'submitted')->count() * 10, 20);
            $skorTugas  = min($tt->where('status_pengerjaan', 'selesai')->count() * 10, 10);
            $skor       = $skorAbsen + $skorMengajar + $skorLog + $skorTugas;

            return [
                'id'     => $g->id,
                'nama'   => $g->user->name,
                'foto'   => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                'jabatan'=> $g->jabatan?->nama_jabatan ?? '—',
                'nip'    => $g->nip,

                // Absensi harian
                'absensi' => [
                    'id'              => $ah?->id,
                    'status'          => $ah?->status ?? 'belum',
                    'jam_masuk'       => $ah?->jam_masuk,
                    'jam_pulang'      => $ah?->jam_pulang,
                    'menit_terlambat' => $ah?->menit_terlambat ?? 0,
                    'is_koreksi'      => $ah?->is_koreksi ?? false,
                ],

                // Mengajar
                'mengajar' => [
                    'jadwal_count'  => $jh->count(),
                    'selesai_count' => $am->whereIn('status', ['hadir', 'terlaksana'])->count(),
                    'jp_terlaksana' => (int) $am->sum('jp_terlaksana'),
                    'jp_jadwal'     => (int) $jh->sum('jumlah_jp'),
                    'detail' => $am->map(fn($a) => [
                        'id'             => $a->id,
                        'jadwal_id'      => $a->jadwal_mengajar_id,
                        'mata_pelajaran' => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                        'kelas'          => $a->jadwalMengajar?->kelas ?? '—',
                        'status'         => $a->status,
                        'jp_terlaksana'  => $a->jp_terlaksana,
                        'materi'         => $a->materi,
                    ])->values(),
                ],

                // Log kerja harian
                'log_kerja' => [
                    'total'         => $lk->count(),
                    'submitted'     => $lk->where('status', 'submitted')->count(),
                    'diverifikasi'  => $lk->where('status', 'diverifikasi')->count(),
                    'durasi_jam'    => round($lk->sum('durasi_menit') / 60, 1),
                    'detail' => $lk->map(fn($l) => [
                        'id'        => $l->id,
                        'judul'     => $l->judul,
                        'tugas'     => $l->tugasJabatan?->nama_tugas ?? $l->kategori_custom,
                        'durasi'    => $l->durasi_format,
                        'status'    => $l->status,
                    ])->values(),
                ],

                // Tugas tambahan aktif hari ini
                'tugas_tambahan' => [
                    'aktif'   => $tt->count(),
                    'selesai' => $tt->where('status_pengerjaan', 'selesai')->count(),
                    'detail' => $tt->map(fn($t) => [
                        'id'          => $t->id,
                        'judul'       => $t->tugasTambahan?->judul ?? '—',
                        'status'      => $t->status_pengerjaan,
                        'disetujui'   => $t->disetujui,
                    ])->values(),
                ],

                // Realisasi tugas jabatan
                'tugas_jabatan' => [
                    'selesai' => $rj->count(),
                    'detail' => $rj->map(fn($r) => [
                        'id'         => $r->id,
                        'nama_tugas' => $r->tugasJabatan?->nama_tugas ?? '—',
                        'disetujui'  => $r->disetujui,
                        'keterangan' => $r->keterangan,
                    ])->values(),
                ],

                // Skor aktivitas harian
                'skor_harian' => min($skor, 100),
            ];
        });

        // Ringkasan global
        $summary = [
            'total_guru'     => $data->count(),
            'hadir'          => $data->where('absensi.status', 'hadir')->count()
                              + $data->where('absensi.status', 'terlambat')->count(),
            'belum_absen'    => $data->where('absensi.status', 'belum')->count(),
            'alfa'           => $data->where('absensi.status', 'alfa')->count(),
            'total_jp'       => $data->sum('mengajar.jp_terlaksana'),
            'log_pending'    => $data->sum('log_kerja.submitted'),
            'tugas_selesai'  => $data->sum('tugas_tambahan.selesai'),
        ];

        return Inertia::render('Admin/SmartPayroll/Absensi/Monitoring', [
            'data'      => $data->sortBy('nama')->values(),
            'summary'   => $summary,
            'tanggal'   => $tanggal->format('Y-m-d'),
            'hari'      => $tanggal->locale('id')->isoFormat('dddd, D MMMM Y'),
            'hari_libur'=> $hariLibur ? $hariLibur->only(['id', 'nama']) : null,
            'jabatan'   => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'filters'   => $request->only(['tanggal', 'jabatan_id', 'search']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DETAIL GURU — Kinerja lengkap per tenaga pendidik
    // ══════════════════════════════════════════════════════════════════════════

    public function detailGuru(Request $request, TenagaPendidik $guru)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);
        $mulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        $guru->load(['user', 'jabatan', 'jabatanGuru.jabatan']);

        // Absensi harian bulan ini
        $absensiHarian = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderBy('tanggal')->get();

        // Hitung hari kerja efektif
        $hariLiburCount = HariLibur::hitungHariLiburDalamRentang($mulai->toDateString(), $selesai->toDateString());
        $hariKerja = $mulai->diffInDays($selesai) + 1 - $hariLiburCount;

        // Absensi mengajar bulan ini
        $absensiMengajar = AbsensiMengajar::with('jadwalMengajar.mataPelajaran')
            ->where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderBy('tanggal')->get();

        // Log kerja harian
        $logKerja = LogKerjaHarian::with('tugasJabatan')
            ->where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderByDesc('tanggal')->get();

        // Tugas tambahan
        $tugasTambahan = PenugasanTambahan::with(['tugasTambahan', 'diverifikasiOleh'])
            ->where('tenaga_pendidik_id', $guru->id)
            ->whereHas('tugasTambahan', fn($q) =>
                $q->where('tanggal_mulai', '<=', $selesai)
                  ->where(fn($q2) => $q2->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $mulai))
            )->get();

        // Realisasi tugas jabatan
        $realisasiJabatan = RealisasiTugasJabatan::with(['tugasJabatan', 'diverifikasiOleh'])
            ->where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->orderByDesc('tanggal')->get();

        // Rekap kinerja bulanan
        $rekapKinerja = RekapKinerjaBulanan::where('tenaga_pendidik_id', $guru->id)
            ->where('bulan', $bulan)->where('tahun', $tahun)->first();

        // Log koreksi
        $logKoreksi = KoreksiAbsensi::where('tenaga_pendidik_id', $guru->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->with('dikoreksiOleh')->orderByDesc('created_at')->get();

        // Ringkasan absensi
        $ringkasanAbsensi = [
            'hari_kerja'  => $hariKerja,
            'hadir'       => $absensiHarian->whereIn('status', ['hadir','terlambat','dinas_luar'])->count(),
            'terlambat'   => $absensiHarian->where('status', 'terlambat')->count(),
            'izin'        => $absensiHarian->whereIn('status', ['izin','izin_sakit'])->count(),
            'sakit'       => $absensiHarian->where('status', 'sakit')->count(),
            'alfa'        => $absensiHarian->where('status', 'alfa')->count(),
            'libur'       => $absensiHarian->where('status', 'libur')->count(),
            'pct_hadir'   => $hariKerja > 0
                ? round($absensiHarian->whereIn('status', ['hadir','terlambat','dinas_luar'])->count() / $hariKerja * 100, 1)
                : 0,
        ];

        return Inertia::render('Admin/SmartPayroll/Absensi/DetailGuru', [
            'guru'             => [
                'id'     => $guru->id,
                'nama'   => $guru->user->name,
                'nip'    => $guru->nip,
                'foto'   => $guru->user->foto ? asset('storage/'.$guru->user->foto) : null,
                'jabatan'=> $guru->jabatan?->nama_jabatan ?? '—',
                'jabatan_tambahan' => $guru->jabatanGuru->map(fn($jg) => $jg->jabatan->nama_jabatan)->join(', '),
            ],
            'ringkasan_absensi'=> $ringkasanAbsensi,
            'absensi_harian'   => $absensiHarian->map(fn($a) => [
                'id'              => $a->id,
                'tanggal'         => $a->tanggal->format('d M Y'),
                'tanggal_raw'     => $a->tanggal->format('Y-m-d'),
                'status'          => $a->status,
                'jam_masuk'       => $a->jam_masuk,
                'jam_pulang'      => $a->jam_pulang,
                'menit_terlambat' => $a->menit_terlambat,
                'is_koreksi'      => $a->is_koreksi,
                'keterangan'      => $a->keterangan,
            ])->values(),
            'absensi_mengajar' => $absensiMengajar->map(fn($a) => [
                'id'             => $a->id,
                'tanggal'        => $a->tanggal->format('d M Y'),
                'mata_pelajaran' => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                'kelas'          => $a->jadwalMengajar?->kelas ?? '—',
                'status'         => $a->status,
                'jp_terlaksana'  => $a->jp_terlaksana,
                'materi'         => $a->materi,
            ])->values(),
            'log_kerja'        => $logKerja->map(fn($l) => [
                'id'       => $l->id,
                'tanggal'  => $l->tanggal->format('d M Y'),
                'judul'    => $l->judul,
                'tugas'    => $l->tugasJabatan?->nama_tugas ?? $l->kategori_custom,
                'durasi'   => $l->durasi_format,
                'status'   => $l->status,
            ])->values(),
            'tugas_tambahan'   => $tugasTambahan->map(fn($t) => [
                'id'           => $t->id,
                'judul'        => $t->tugasTambahan?->judul ?? '—',
                'status'       => $t->status_pengerjaan,
                'disetujui'    => $t->disetujui,
                'laporan'      => $t->laporan,
                'dilaporkan'   => $t->dilaporkan_pada?->format('d M Y H:i'),
                'diverifikasi' => $t->diverifikasiOleh?->name,
            ])->values(),
            'realisasi_jabatan'=> $realisasiJabatan->map(fn($r) => [
                'id'         => $r->id,
                'tanggal'    => $r->tanggal->format('d M Y'),
                'nama_tugas' => $r->tugasJabatan?->nama_tugas ?? '—',
                'disetujui'  => $r->disetujui,
                'keterangan' => $r->keterangan,
            ])->values(),
            'rekap_kinerja'    => $rekapKinerja ? [
                'skor_keaktifan'    => $rekapKinerja->skor_keaktifan,
                'skor_penugasan'    => $rekapKinerja->skor_penugasan,
                'skor_total'        => $rekapKinerja->skor_total,
                'label_skor'        => $rekapKinerja->label_skor,
                'catatan'           => $rekapKinerja->catatan_superadmin,
            ] : null,
            'log_koreksi'      => $logKoreksi->map(fn($k) => [
                'id'             => $k->id,
                'tanggal'        => $k->tanggal?->format('d M Y'),
                'tipe'           => $k->tipe_absensi,
                'nilai_lama'     => $k->nilai_lama,
                'nilai_baru'     => $k->nilai_baru,
                'alasan'         => $k->alasan,
                'dikoreksi_oleh' => $k->dikoreksiOleh?->name,
                'waktu'          => $k->created_at?->format('d M Y H:i'),
            ])->values(),
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // KOREKSI — Handle semua jenis koreksi dari satu endpoint
    // ══════════════════════════════════════════════════════════════════════════

    public function koreksi(Request $request)
    {
        $data = $request->validate([
            'tipe'               => 'required|in:harian,mengajar,tugas_jabatan,tugas_tambahan',
            'referensi_id'       => 'required|integer',
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'required|date',
            'field'              => 'required|string',
            'nilai_baru'         => 'required|string',
            'alasan'             => 'required|string|max:500',
            // Field opsional per tipe
            'jam_masuk'          => 'nullable|date_format:H:i',
            'jam_pulang'         => 'nullable|date_format:H:i',
            'menit_terlambat'    => 'nullable|integer|min:0',
            'jp_terlaksana'      => 'nullable|integer|min:0',
            'materi'             => 'nullable|string',
            'keterangan'         => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data) {
            $nilaiLama = null;
            $refId     = null;

            switch ($data['tipe']) {
                case 'harian':
                    $record = AbsensiHarian::findOrFail($data['referensi_id']);
                    $nilaiLama = $record->{$data['field']};
                    $record->update(array_filter([
                        $data['field']    => $data['nilai_baru'],
                        'jam_masuk'       => $data['jam_masuk'] ?? null,
                        'jam_pulang'      => $data['jam_pulang'] ?? null,
                        'menit_terlambat' => $data['menit_terlambat'] ?? null,
                        'keterangan'      => $data['keterangan'] ?? null,
                        'is_koreksi'      => true,
                        'dikoreksi_oleh'  => Auth::id(),
                    ], fn($v) => $v !== null));
                    $refId = $record->id;
                    break;

                case 'mengajar':
                    $record = AbsensiMengajar::findOrFail($data['referensi_id']);
                    $nilaiLama = $record->{$data['field']};
                    $record->update(array_filter([
                        $data['field']       => $data['nilai_baru'],
                        'jp_terlaksana'      => $data['jp_terlaksana'] ?? null,
                        'materi'             => $data['materi'] ?? null,
                        'keterangan'         => $data['keterangan'] ?? null,
                        'is_koreksi'         => true,
                        'dikoreksi_oleh'     => Auth::id(),
                    ], fn($v) => $v !== null));
                    $refId = $record->id;
                    break;

                case 'tugas_jabatan':
                    $record = RealisasiTugasJabatan::findOrFail($data['referensi_id']);
                    $nilaiLama = $record->disetujui ? 'disetujui' : 'pending';
                    $record->update([
                        'disetujui'         => $data['nilai_baru'] === 'disetujui',
                        'keterangan'        => $data['keterangan'] ?? $record->keterangan,
                        'diverifikasi_oleh' => Auth::id(),
                    ]);
                    $refId = $record->id;
                    break;

                case 'tugas_tambahan':
                    $record = PenugasanTambahan::findOrFail($data['referensi_id']);
                    $nilaiLama = $record->status_pengerjaan;
                    $record->update([
                        'status_pengerjaan' => $data['nilai_baru'],
                        'disetujui'         => $data['nilai_baru'] === 'selesai',
                        'catatan_verifikasi'=> $data['keterangan'] ?? null,
                        'diverifikasi_oleh' => Auth::id(),
                    ]);
                    $refId = $record->id;
                    break;
            }

            // Log koreksi
            KoreksiAbsensi::create([
                'tenaga_pendidik_id'    => $data['tenaga_pendidik_id'],
                'tanggal'               => $data['tanggal'],
                'tipe_absensi'          => $data['tipe'],
                'absensi_harian_id'     => $data['tipe'] === 'harian'  ? $refId : null,
                'absensi_mengajar_id'   => $data['tipe'] === 'mengajar'? $refId : null,
                'realisasi_tugas_id'    => $data['tipe'] === 'tugas_jabatan' ? $refId : null,
                'penugasan_tambahan_id' => $data['tipe'] === 'tugas_tambahan' ? $refId : null,
                'field_dikoreksi'       => $data['field'],
                'nilai_lama'            => $nilaiLama,
                'nilai_baru'            => $data['nilai_baru'],
                'alasan'                => $data['alasan'],
                'status'                => 'disetujui',
                'dikoreksi_oleh'        => Auth::id(),
            ]);
        });

        return back()->with('success', 'Koreksi berhasil disimpan.')->with('preserveScroll', true);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VERIFIKASI LOG KERJA dari halaman monitoring
    // ══════════════════════════════════════════════════════════════════════════

    public function verifikasiLog(Request $request, LogKerjaHarian $log)
    {
        $data = $request->validate([
            'aksi'    => 'required|in:verifikasi,tolak',
            'catatan' => 'nullable|string|max:500',
        ]);

        $log->update([
            'status'               => $data['aksi'] === 'verifikasi' ? 'diverifikasi' : 'ditolak',
            'diverifikasi_oleh'    => Auth::id(),
            'catatan_verifikasi'   => $data['catatan'],
            'verified_at'          => now(),
        ]);

        $label = $data['aksi'] === 'verifikasi' ? 'diverifikasi' : 'ditolak';
        return back()->with('success', "Log kerja {$label}.")->with('preserveScroll', true);
    }
}