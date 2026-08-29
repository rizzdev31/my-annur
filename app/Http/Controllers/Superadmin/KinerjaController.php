<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JabatanGuru;
use App\Models\LogKerjaHarian;
use App\Models\PunishmentKinerja;
use App\Models\RekapKinerjaBulanan;
use App\Models\SettingKinerja;
use App\Models\TenagaPendidik;
use App\Services\KinerjaCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KinerjaController extends Controller
{
    public function __construct(
        private readonly KinerjaCalculationService $kinerjaService
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // INDEX — Dashboard rekap kinerja bulanan
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $setting = SettingKinerja::getDefault();

        $logPending = LogKerjaHarian::with([
                'tenagaPendidik.user', 'tenagaPendidik.jabatan', 'tugasJabatan',
            ])
            ->where('status', 'submitted')
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->orderBy('tanggal')->get()
            ->map(fn($l) => $this->formatLog($l));

        $rekapList = RekapKinerjaBulanan::with([
                'tenagaPendidik.user', 'tenagaPendidik.jabatan',
            ])
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->orderByDesc('skor_total')->get()
            ->map(fn($r) => $this->formatRekap($r, $setting));

        return Inertia::render('Admin/SmartPayroll/Kinerja/Index', [
            'logPending'    => $logPending,
            'rekapList'     => $rekapList,
            'ringkasan'     => $this->kinerjaService->getRingkasanBulanIni($bulan, $tahun),
            'setting_aktif' => [
                'nama'               => $setting->nama,
                'bobot_absensi'      => $setting->bobot_absensi,
                'bobot_tugas'        => $setting->bobot_tugas,
                'bobot_administrasi' => $setting->bobot_administrasi,
                'grade_a'            => $setting->grade_a,
                'grade_b'            => $setting->grade_b,
                'grade_c'            => $setting->grade_c,
                'grade_d'            => $setting->grade_d,
            ],
            'bulan'   => $bulan,
            'tahun'   => $tahun,
            'filters' => $request->only(['bulan', 'tahun']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOG KERJA
    // ══════════════════════════════════════════════════════════════════════════

    public function logKerja(Request $request)
    {
        $bulan  = (int) ($request->bulan ?? now()->month);
        $tahun  = (int) ($request->tahun ?? now()->year);
        $guruId = $request->guru_id;
        $status = $request->status;

        $logs = LogKerjaHarian::with([
                'tenagaPendidik.user', 'tenagaPendidik.jabatan', 'tugasJabatan',
            ])
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->when($guruId, fn($q) => $q->where('tenaga_pendidik_id', $guruId))
            ->when($status,  fn($q) => $q->where('status', $status))
            ->orderByDesc('tanggal')->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        $logs->through(fn($l) => $this->formatLog($l));

        return Inertia::render('Admin/SmartPayroll/Kinerja/LogKerja', [
            'logs'     => $logs,
            'guruList' => TenagaPendidik::with('user')->aktif()->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user->name]),
            'bulan'    => $bulan,
            'tahun'    => $tahun,
            'filters'  => $request->only(['bulan', 'tahun', 'guru_id', 'status']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DETAIL GURU — Kinerja lengkap dengan breakdown 3 komponen
    // ══════════════════════════════════════════════════════════════════════════

    public function detailGuru(TenagaPendidik $guru, Request $request)
    {
        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $setting = SettingKinerja::getDefault();

        // Hitung rekap terbaru (simpan/update otomatis)
        $rekap = $this->kinerjaService->hitungRekap($guru, $bulan, $tahun);

        $logs = LogKerjaHarian::with('tugasJabatan')
            ->where('tenaga_pendidik_id', $guru->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')->get()
            ->map(fn($l) => $this->formatLog($l));

        // Tren 6 bulan untuk chart
        $riwayat = RekapKinerjaBulanan::where('tenaga_pendidik_id', $guru->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')
            ->limit(6)->get()
            ->map(fn($r) => [
                'bulan'             => $r->bulan,
                'tahun'             => $r->tahun,
                'label'             => Carbon::create($r->tahun, $r->bulan)->locale('id')->isoFormat('MMM YY'),
                'skor_total'        => $r->skor_total,
                'skor_absensi'      => $r->skor_absensi      ?? 0,
                'skor_tugas'        => $r->skor_tugas         ?? 0,
                'skor_administrasi' => $r->skor_administrasi  ?? 0,
                'grade'             => $setting->getGrade($r->skor_total),
                'label_grade'       => $setting->getLabelGrade($r->skor_total),
            ])
            ->reverse()->values();

        return Inertia::render('Admin/SmartPayroll/Kinerja/DetailGuru', [
            'guru' => [
                'id'      => $guru->id,
                'nama'    => $guru->user->name,
                'foto'    => $guru->user->foto ? asset('storage/' . $guru->user->foto) : null,
                'jabatan' => $guru->jabatan?->nama_jabatan ?? $guru->nama_jabatan_display ?? '—',
                'nip'     => $guru->nip,
            ],
            'rekap'         => $this->formatRekapDetail($rekap, $setting),
            'logs'          => $logs,
            'riwayat'       => $riwayat,
            'setting_aktif' => [
                'nama'               => $setting->nama,
                'bobot_absensi'      => $setting->bobot_absensi,
                'bobot_tugas'        => $setting->bobot_tugas,
                'bobot_administrasi' => $setting->bobot_administrasi,
                'nilai_hadir'        => $setting->nilai_hadir,
                'nilai_terlambat'    => $setting->nilai_terlambat,
                'nilai_izin'         => $setting->nilai_izin,
                'nilai_sakit'        => $setting->nilai_sakit,
                'nilai_dinas_luar'   => $setting->nilai_dinas_luar,
                'nilai_alfa'         => $setting->nilai_alfa,
                'grade_a'            => $setting->grade_a,
                'grade_b'            => $setting->grade_b,
                'grade_c'            => $setting->grade_c,
                'grade_d'            => $setting->grade_d,
            ],
            // Punishment kinerja periode ini
            'punishments' => PunishmentKinerja::with(['jabatan', 'dibuatOleh'])
                ->where('tenaga_pendidik_id', $guru->id)
                ->where('bulan', $bulan)->where('tahun', $tahun)
                ->latest()->get()->map(fn($p) => [
                    'id'          => $p->id,
                    'jenis'       => $p->jenis,
                    'jenis_label' => $p->jenis_label,
                    'nominal'     => $p->nominal,
                    'jabatan'     => $p->jabatan?->nama_jabatan,
                    'catatan'     => $p->catatan,
                    'oleh'        => $p->dibuatOleh?->name,
                    'tanggal'     => $p->created_at?->format('d M Y H:i'),
                ]),
            // Jabatan aktif yang diemban (untuk opsi pencopotan)
            'jabatan_diemban' => $guru->jabatanAktif->map(fn($jg) => [
                'jabatan_guru_id' => $jg->id,
                'jabatan_id'      => $jg->jabatan_id,
                'nama'            => $jg->jabatan?->nama_jabatan ?? '—',
                'utama'           => (bool) $jg->adalah_utama,
            ])->values(),
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PUNISHMENT KINERJA
    // ══════════════════════════════════════════════════════════════════════════

    public function punishmentStore(Request $request, TenagaPendidik $guru)
    {
        $data = $request->validate([
            'bulan'        => 'required|integer|between:1,12',
            'tahun'        => 'required|integer|min:2024',
            'jenis'        => 'required|in:potongan,evaluasi,peringatan,pencopotan',
            'nominal'      => 'required_if:jenis,potongan|nullable|numeric|min:0',
            'jabatan_id'   => 'required_if:jenis,pencopotan|nullable|exists:jabatan,id',
            'catatan'      => 'required|string|max:1000',
            'skor_kinerja' => 'nullable|numeric',
        ]);

        // Pencopotan → akhiri jabatan aktif guru (set berlaku_selesai)
        if ($data['jenis'] === 'pencopotan') {
            JabatanGuru::where('tenaga_pendidik_id', $guru->id)
                ->where('jabatan_id', $data['jabatan_id'])
                ->whereNull('berlaku_selesai')
                ->update(['berlaku_selesai' => now()]);
        }

        PunishmentKinerja::create([
            'tenaga_pendidik_id' => $guru->id,
            'bulan'        => $data['bulan'],
            'tahun'        => $data['tahun'],
            'jenis'        => $data['jenis'],
            'nominal'      => $data['jenis'] === 'potongan' ? $data['nominal'] : null,
            'jabatan_id'   => $data['jenis'] === 'pencopotan' ? $data['jabatan_id'] : null,
            'skor_kinerja' => $data['skor_kinerja'] ?? null,
            'catatan'      => $data['catatan'],
            'dibuat_oleh'  => auth()->id(),
        ]);

        return back()->with('success', 'Punishment kinerja diberikan.');
    }

    public function punishmentDestroy(PunishmentKinerja $punishment)
    {
        $punishment->delete();
        return back()->with('success', 'Punishment dihapus.');
    }

    /** Rekap semua punishment lintas guru per periode (sub-menu sidebar). */
    public function punishmentIndex(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        $rows = PunishmentKinerja::with(['tenagaPendidik.user', 'jabatan', 'dibuatOleh'])
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->latest()->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'guru_id'     => $p->tenaga_pendidik_id,
                'guru'        => $p->tenagaPendidik?->user?->name ?? '—',
                'foto'        => $p->tenagaPendidik?->user?->foto
                    ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
                'jenis'       => $p->jenis,
                'jenis_label' => $p->jenis_label,
                'nominal'     => $p->nominal,
                'jabatan'     => $p->jabatan?->nama_jabatan,
                'skor'        => $p->skor_kinerja,
                'catatan'     => $p->catatan,
                'oleh'        => $p->dibuatOleh?->name,
                'tanggal'     => $p->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/SmartPayroll/Kinerja/Punishment', [
            'punishments' => $rows,
            'stats' => [
                'total'         => $rows->count(),
                'potongan'      => $rows->where('jenis', 'potongan')->count(),
                'peringatan'    => $rows->where('jenis', 'peringatan')->count(),
                'evaluasi'      => $rows->where('jenis', 'evaluasi')->count(),
                'pencopotan'    => $rows->where('jenis', 'pencopotan')->count(),
                'total_potongan'=> (float) $rows->where('jenis', 'potongan')->sum('nominal'),
            ],
            'bulan'   => $bulan,
            'tahun'   => $tahun,
            'filters' => ['bulan' => $bulan, 'tahun' => $tahun],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AKSI
    // ══════════════════════════════════════════════════════════════════════════

    public function hitungRekap(Request $request)
    {
        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $total   = $this->kinerjaService->hitungRekapSemua($bulan, $tahun);
        $setting = SettingKinerja::getDefault();
        return back()->with('success',
            "Rekap kinerja {$total} guru dikalkulasi menggunakan \"{$setting->nama}\"."
        );
    }

    public function verifikasi(Request $request, LogKerjaHarian $log)
    {
        $request->validate(['catatan' => 'nullable|string|max:500']);
        $this->kinerjaService->verifikasiLog($log, $request->catatan);
        return back()->with('success', "Log kerja \"{$log->judul}\" diverifikasi.");
    }

    public function tolak(Request $request, LogKerjaHarian $log)
    {
        $request->validate(['catatan' => 'required|string|max:500'],
            ['catatan.required' => 'Alasan penolakan wajib diisi.']);
        $this->kinerjaService->tolakLog($log, $request->catatan);
        return back()->with('success', "Log kerja \"{$log->judul}\" ditolak.");
    }

    public function verifikasiBulk(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);
        $logs  = LogKerjaHarian::where('status', 'submitted')
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->get();
        foreach ($logs as $log) {
            $this->kinerjaService->verifikasiLog($log, 'Bulk verifikasi oleh admin');
        }
        return back()->with('success', "{$logs->count()} log kerja berhasil diverifikasi.");
    }

    public function catatanRekap(Request $request, RekapKinerjaBulanan $rekap)
    {
        $request->validate(['catatan' => 'required|string|max:1000']);
        $rekap->update([
            'catatan_superadmin' => $request->catatan,
            'dikaji_oleh'        => auth()->id(),
            'dikaji_pada'        => now(),
        ]);
        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    // ── Override / Reset kinerja (koreksi manual & keperluan demo) ─────────────

    /** Set skor_total secara manual lalu KUNCI agar tak tertimpa hitung ulang. */
    public function overrideSkor(Request $request, TenagaPendidik $guru)
    {
        $data = $request->validate([
            'bulan'      => 'required|integer|between:1,12',
            'tahun'      => 'required|integer|min:2020',
            'skor_total' => 'required|numeric|between:0,100',
            'catatan'    => 'nullable|string|max:1000',
        ]);

        $setting = SettingKinerja::getDefault();
        $rekap = RekapKinerjaBulanan::firstOrNew([
            'tenaga_pendidik_id' => $guru->id,
            'bulan'              => $data['bulan'],
            'tahun'              => $data['tahun'],
        ]);
        if (!$rekap->exists) $rekap->setting_kinerja_id = $setting->id;

        $rekap->skor_total         = round($data['skor_total'], 1);
        $rekap->catatan_superadmin = $data['catatan'] ?? $rekap->catatan_superadmin;
        $rekap->sudah_dikunci      = true;
        $rekap->dikaji_oleh        = auth()->id();
        $rekap->dikaji_pada        = now();
        $rekap->save();

        return back()->with('success', "Skor {$guru->user->name} di-override ke {$rekap->skor_total} & dikunci.");
    }

    /** Buka kunci lalu hitung ulang dari data sumber (kembali ke skor terhitung). */
    public function resetRekap(Request $request, TenagaPendidik $guru)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        RekapKinerjaBulanan::where('tenaga_pendidik_id', $guru->id)
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->update(['sudah_dikunci' => false]);
        $this->kinerjaService->hitungRekap($guru, $bulan, $tahun);

        return back()->with('success', "Kinerja {$guru->user->name} direset (dihitung ulang dari data).");
    }

    /** Reset massal 1 periode — buka semua kunci + hitung ulang. Berguna saat demo. */
    public function resetSemua(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        RekapKinerjaBulanan::where('bulan', $bulan)->where('tahun', $tahun)
            ->update(['sudah_dikunci' => false]);
        $total = $this->kinerjaService->hitungRekapSemua($bulan, $tahun);

        return back()->with('success', "Kinerja {$total} guru direset & dihitung ulang untuk periode ini.");
    }

    /** Raport kinerja 1 guru (siap cetak). */
    public function raport(TenagaPendidik $guru, Request $request)
    {
        $bulan   = (int) ($request->bulan ?? now()->month);
        $tahun   = (int) ($request->tahun ?? now()->year);
        $setting = SettingKinerja::getDefault();
        $rekap   = $this->kinerjaService->hitungRekap($guru, $bulan, $tahun);
        $guru->loadMissing(['user', 'jabatan']);

        return Inertia::render('Admin/SmartPayroll/Kinerja/Raport', [
            'lembaga' => 'Pondok Pesantren An-Nur',
            'guru'    => [
                'id'      => $guru->id,
                'nama'    => $guru->user?->name ?? ('Guru #' . $guru->id),
                'nip'     => $guru->nip,
                'jabatan' => $guru->jabatan?->nama_jabatan ?? '—',
                'foto'    => $guru->user?->foto ? asset('storage/' . $guru->user->foto) : null,
            ],
            'periode' => [
                'bulan' => $bulan, 'tahun' => $tahun,
                'label' => Carbon::create($tahun, $bulan)->locale('id')->isoFormat('MMMM YYYY'),
            ],
            'detail'  => $this->formatRekapDetail($rekap, $setting),
            'ambang'  => ['grade_a' => $setting->grade_a, 'grade_b' => $setting->grade_b, 'grade_c' => $setting->grade_c],
            'dicetak' => now()->locale('id')->isoFormat('D MMMM YYYY'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function formatRekap(RekapKinerjaBulanan $r, SettingKinerja $s): array
    {
        return [
            'id'                 => $r->id,
            'guru_id'            => $r->tenaga_pendidik_id, // utk link detail (binding {guru})
            'nama'               => $r->tenagaPendidik->user->name,
            'foto'               => $r->tenagaPendidik->user->foto ? asset('storage/'.$r->tenagaPendidik->user->foto) : null,
            'jabatan'            => $r->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
            'skor_absensi'       => $r->skor_absensi      ?? 0,
            'skor_tugas'         => $r->skor_tugas         ?? 0,
            'skor_administrasi'  => $r->skor_administrasi  ?? 0,
            'skor_piket'         => $r->skor_piket         ?? 0, // penyesuaian signed (+/−)
            'skor_total'         => $r->skor_total,
            'grade'              => $s->getGrade($r->skor_total),
            'label_grade'        => $s->getLabelGrade($r->skor_total),
            'badge_grade'        => $s->getBadgeGrade($r->skor_total),
            // backward compat
            'badge_skor'         => $s->getBadgeGrade($r->skor_total),
            'label_skor'         => $s->getLabelGrade($r->skor_total),
            'total_hadir'        => $r->total_hadir       ?? 0,
            'total_terlambat'    => $r->total_terlambat   ?? 0,
            'total_alfa'         => $r->total_alfa        ?? 0,
            'total_hari_kerja'   => $r->total_hari_kerja  ?? 0,
            'total_log'          => $r->total_log_submitted,
            'total_diverifikasi' => $r->total_log_diverifikasi,
            // Field yg dipakai bar di Index (sebelumnya tak dikirim → "%" kosong)
            'skor_keaktifan'     => $r->skor_keaktifan ?? $r->skor_administrasi ?? 0,
            'skor_penugasan'     => $r->skor_penugasan ?? $r->skor_tugas ?? 0,
            'total_durasi_jam'   => round(($r->total_durasi_menit ?? 0) / 60, 1),
            'catatan'            => $r->catatan_superadmin,
            'sudah_dikunci'      => $r->sudah_dikunci,
        ];
    }

    private function formatRekapDetail(RekapKinerjaBulanan $r, SettingKinerja $s): array
    {
        return [
            'skor_total'  => $r->skor_total,
            'grade'       => $s->getGrade($r->skor_total),
            'label_grade' => $s->getLabelGrade($r->skor_total),
            'badge_grade' => $s->getBadgeGrade($r->skor_total),
            'komponen'    => [
                'absensi' => [
                    'skor'           => $r->skor_absensi      ?? 0,
                    'bobot'          => $s->bobot_absensi,
                    'kontribusi'     => round(($r->skor_absensi ?? 0) * $s->bobot_absensi / 100, 2),
                    'hadir'          => $r->total_hadir        ?? 0,
                    'terlambat'      => $r->total_terlambat    ?? 0,
                    'izin'           => $r->total_izin         ?? 0,
                    'sakit'          => $r->total_sakit        ?? 0,
                    'alfa'           => $r->total_alfa         ?? 0,
                    'dinas_luar'     => $r->total_dinas_luar   ?? 0,
                    'hari_kerja'     => $r->total_hari_kerja   ?? 0,
                    'jp_jadwal'      => $r->total_jp_jadwal    ?? 0,
                    'jp_terlaksana'  => $r->total_jp_terlaksana?? 0,
                ],
                'tugas' => [
                    'skor'              => $r->skor_tugas         ?? 0,
                    'bobot'             => $s->bobot_tugas,
                    'kontribusi'        => round(($r->skor_tugas ?? 0) * $s->bobot_tugas / 100, 2),
                    'penugasan_total'   => $r->total_penugasan_diterima  ?? 0,
                    'penugasan_selesai' => $r->total_penugasan_selesai   ?? 0,
                    'jabatan_total'     => $r->total_realisasi_jabatan   ?? 0,
                    'jabatan_disetujui' => $r->total_realisasi_disetujui ?? 0,
                ],
                'administrasi' => [
                    'skor'             => $r->skor_administrasi  ?? 0,
                    'bobot'            => $s->bobot_administrasi,
                    'kontribusi'       => round(($r->skor_administrasi ?? 0) * $s->bobot_administrasi / 100, 2),
                    'sesi_jadwal'      => $r->total_sesi_jadwal     ?? 0,
                    'sesi_terlaksana'  => $r->total_sesi_terlaksana ?? 0,
                    'sesi_dilaporkan'  => $r->total_sesi_dilaporkan ?? 0,
                    'log_submitted'    => $r->total_log_submitted,
                    'log_diverifikasi' => $r->total_log_diverifikasi,
                    'durasi_jam'       => round(($r->total_durasi_menit ?? 0) / 60, 1),
                ],
                // PIKET = penyesuaian (+/−) di atas skor dasar, bukan komponen berbobot.
                'piket' => [
                    'penyesuaian' => $r->skor_piket ?? 0,
                ],
            ],
            // backward compat
            'skor_keaktifan'     => $r->skor_keaktifan,
            'skor_penugasan'     => $r->skor_penugasan,
            'total_log'          => $r->total_log_submitted,
            'total_diverifikasi' => $r->total_log_diverifikasi,
            'total_durasi_jam'   => round(($r->total_durasi_menit ?? 0) / 60, 1),
            'catatan'            => $r->catatan_superadmin,
            'sudah_dikunci'      => $r->sudah_dikunci,
        ];
    }

    private function formatLog(LogKerjaHarian $l): array
    {
        return [
            'id'            => $l->id,
            'nama_guru'     => $l->tenagaPendidik->user->name,
            'foto_guru'     => $l->tenagaPendidik->user->foto ? asset('storage/'.$l->tenagaPendidik->user->foto) : null,
            'jabatan'       => $l->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
            'tanggal'       => $l->tanggal->format('d M Y'),
            'judul'         => $l->judul,
            'deskripsi'     => $l->deskripsi,
            'kategori'      => $l->tugasJabatan?->nama_tugas ?? $l->kategori_custom ?? 'Umum',
            'durasi_format' => $l->durasi_format,
            'durasi_menit'  => $l->durasi_menit,
            'file_bukti'    => $l->file_bukti ? asset('storage/'.$l->file_bukti) : null,
            'status'        => $l->status,
            'status_badge'  => $l->status_badge,
            'catatan'       => $l->catatan_verifikasi,
            'created_at'    => $l->created_at->format('d M Y H:i'),
        ];
    }
}