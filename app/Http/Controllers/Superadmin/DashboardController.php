<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\Lembur;
use App\Models\PunishmentKinerja;
use App\Models\RekapKinerjaBulanan;
use App\Models\SettingKinerja;
use App\Models\TenagaPendidik;
use App\Models\TugasTambahan;
use App\Models\PeriodePenggajian;
use App\Models\Penggajian;
// Fitur pesantren yang dimonitor di dashboard
use App\Models\AbsensiMengajar;
use App\Models\AbsensiKegiatan;
use App\Models\SetoranTahfidz;
use App\Models\TahsinPenilaian;
use App\Models\TugasTasmi;
use App\Models\ControllingAbsensi;
use App\Models\OutboxLaporan;
use App\Models\PiketJadwal;
use App\Models\PiketPenilaian;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET admin/dashboard/live — hanya bagian yang benar-benar berubah tiap menit.
     *
     * Dashboard dulu memuat SELURUH payload (tren 7 hari, tren gaji 6 periode,
     * gantt, distribusi kinerja, 14 query monitoring) hanya saat halaman dibuka,
     * dan tidak pernah menyegarkan diri — padahal kartunya menulis "real-time".
     * Endpoint ringan ini yang dipanggil berkala oleh halaman.
     */
    public function live()
    {
        return response()->json($this->dataLive() + ['success' => true]);
    }

    /**
     * Data volatil: absensi hari ini, monitoring fitur, antrian, periode berjalan.
     * Di-cache 15 detik supaya beberapa admin yang membuka dashboard bersamaan
     * tidak menggandakan beban query.
     */
    private function dataLive(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('dashboard.live', 15, function () {
            $today     = Carbon::today();
            $totalGuru = TenagaPendidik::aktif()->count();
            $absen     = $this->ringkasanAbsensiHariIni($today, $totalGuru);
            $periode   = PeriodePenggajian::untukTanggal($today->toDateString())
                ?? PeriodePenggajian::orderByDesc('tanggal_selesai')->first();

            return [
                'absensi'         => $absen,
                'monitoringFitur' => $this->monitoringFitur($today),
                'antrian'         => $this->antrian(),
                'periode'         => $periode ? [
                    'nama'   => $periode->nama_bulan,
                    'mulai'  => $periode->tanggal_mulai?->format('d M'),
                    'sampai' => $periode->tanggal_selesai?->format('d M Y'),
                    'status' => $periode->status,
                    'sisa_hari' => $periode->tanggal_selesai
                        ? max(0, $today->diffInDays($periode->tanggal_selesai, false)) : null,
                ] : null,
                'diperbarui_pada' => now()->format('H:i:s'),
                'diperbarui_iso'  => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Ringkasan absensi hari ini — memakai COUNT di SQL, bukan memuat seluruh
     * baris beserta relasinya seperti sebelumnya.
     *
     * "Belum absen" dulu = totalGuru − (hadir+izin+sakit), sehingga pada hari
     * yang liburnya berbeda per guru angkanya membengkak. Kini guru yang hari itu
     * memang libur / dibebaskan absen harian dikeluarkan dari penyebut.
     */
    private function ringkasanAbsensiHariIni(Carbon $today, int $totalGuru): array
    {
        $per = AbsensiHarian::whereDate('tanggal', $today)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $hadir     = (int) (($per['hadir'] ?? 0) + ($per['dinas_luar'] ?? 0));
        $terlambat = (int) ($per['terlambat'] ?? 0);
        $izin      = (int) (($per['izin'] ?? 0) + ($per['izin_sakit'] ?? 0));
        $sakit     = (int) ($per['sakit'] ?? 0);
        $alfa      = (int) ($per['alfa'] ?? 0);
        $libur     = (int) ($per['libur'] ?? 0);
        $hadirTotal = $hadir + $terlambat;

        // Guru yang hari ini tidak punya kewajiban absen harian (libur mingguan,
        // libur individu, atau dibebaskan) tidak dihitung sebagai "belum absen".
        $namaHari = \App\Services\TimezoneHelper::namaHariDB($today);
        $wajib = TenagaPendidik::aktif()->get()
            ->filter(fn ($g) => $g->jadwalHari($namaHari, $today->toDateString()) !== null)
            ->count();

        $tercatat = $hadirTotal + $izin + $sakit + $alfa;
        $belum    = max(0, $wajib - $tercatat);

        return [
            'total_guru'   => $totalGuru,
            'wajib_absen'  => $wajib,
            'hadir'        => $hadir,
            'terlambat'    => $terlambat,
            'izin'         => $izin,
            'sakit'        => $sakit,
            'alfa'         => $alfa,
            'libur'        => $libur,
            'hadir_total'  => $hadirTotal,
            'belum'        => $belum,
            'persen_hadir' => $wajib > 0 ? (int) round($hadirTotal / $wajib * 100) : 0,
            'donut' => [
                ['label' => 'Hadir',     'value' => $hadir,     'color' => '#059669'],
                ['label' => 'Terlambat', 'value' => $terlambat, 'color' => '#D97706'],
                ['label' => 'Izin',      'value' => $izin,      'color' => '#0284C7'],
                ['label' => 'Sakit',     'value' => $sakit,     'color' => '#7C3AED'],
                ['label' => 'Alfa',      'value' => $alfa,      'color' => '#DC2626'],
                ['label' => 'Belum',     'value' => $belum,     'color' => '#CBD5E1'],
            ],
        ];
    }

    /** Antrian yang menunggu tindakan admin — angka yang paling sering dicek. */
    private function antrian(): array
    {
        // Status menunggu pada pengajuan izin bernama 'pending' (bukan 'diajukan'
        // seperti pada lembur) — beda penamaan antar modul.
        $izinPending = \App\Models\PengajuanIzin::where('status', 'pending')->count();
        $verifTugas  = \App\Models\PenugasanTambahan::where('status_pengerjaan', 'selesai')
            ->whereNull('disetujui')->count();
        $lewatTenggat = \App\Models\PenugasanTambahan::with('tugasTambahan')
            ->whereHas('tugasTambahan', fn ($q) => $q->where('status', 'aktif'))
            ->whereIn('status_pengerjaan', ['belum', 'sedang'])
            ->get()->filter(fn ($p) => $p->lewatTenggat())->count();

        return [
            ['label' => 'Izin menunggu',      'value' => $izinPending,
             'url' => route('admin.smart-payroll.pengajuan-izin.index'), 'tone' => 'blue'],
            ['label' => 'Lembur menunggu',    'value' => Lembur::where('status', 'diajukan')->count(),
             'url' => route('admin.smart-payroll.lembur.index'), 'tone' => 'violet'],
            ['label' => 'Verifikasi tugas',   'value' => $verifTugas,
             'url' => route('admin.smart-payroll.tugas-tambahan.index'), 'tone' => 'amber'],
            ['label' => 'Tugas lewat tenggat', 'value' => $lewatTenggat,
             'url' => route('admin.smart-payroll.tugas-tambahan.index'), 'tone' => 'rose'],
            ['label' => 'Sanggah piket',      'value' => PiketPenilaian::where('status_sanggah', 'diajukan')->count(),
             'url' => route('admin.piket.sanggah.index'), 'tone' => 'emerald'],
            ['label' => 'Laporan gagal kirim', 'value' => OutboxLaporan::where('status', 'failed')->count(),
             'url' => route('admin.smart-habbit.outbox.index'), 'tone' => 'gray'],
        ];
    }

    public function index()
    {
        $today   = Carbon::today();
        $setting = SettingKinerja::getDefault();
        $totalGuru = TenagaPendidik::aktif()->count();

        // Kinerja mengikuti PERIODE PENGGAJIAN (26→25), bukan bulan kalender:
        // pada tanggal 26–31 dashboard dulu menampilkan distribusi bulan lama
        // yang skornya sudah dibekukan, bukan periode yang sedang berjalan.
        $periodeBerjalan = PeriodePenggajian::untukTanggal($today->toDateString());
        $bulan = (int) ($periodeBerjalan->bulan ?? $today->month);
        $tahun = (int) ($periodeBerjalan->tahun ?? $today->year);

        $live         = $this->dataLive();
        $absen        = $live['absensi'];
        $hadirTotal   = $absen['hadir_total'];
        $terlambat    = $absen['terlambat'];
        $izin         = $absen['izin'];
        $sakit        = $absen['sakit'];
        $belum        = $absen['belum'];
        $donutAbsensi = $absen['donut'];

        // ── Tren kehadiran 7 hari terakhir ───────────────────────────────────
        // Pengelompokan dikerjakan SQL, bukan memfilter koleksi di PHP: kolom
        // `tanggal` di-cast ke Carbon, sehingga membandingkannya dengan string
        // tanggal ('2026-09-15') tidak pernah cocok dan seluruh batang grafik
        // jatuh ke nol meskipun datanya ada.
        $mulai7 = $today->copy()->subDays(6);
        $hadirPerHari = AbsensiHarian::query()
            ->whereBetween('tanggal', [$mulai7->toDateString(), $today->toDateString()])
            ->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])
            ->selectRaw('DATE(tanggal) as tgl, COUNT(*) as jml')
            ->groupBy('tgl')->pluck('jml', 'tgl');

        $trenKehadiran = [];
        for ($d = $mulai7->copy(); $d->lte($today); $d->addDay()) {
            $tgl = $d->toDateString();
            $h   = (int) ($hadirPerHari[$tgl] ?? 0);
            $trenKehadiran[] = [
                'label'   => $d->locale('id')->isoFormat('dd'),
                'tanggal' => $d->locale('id')->isoFormat('D MMM'),
                'hadir'   => $h,
                // Penyebut disamakan dengan donut di sebelahnya (seluruh guru
                // aktif) agar kedua kartu tidak menyebut persentase berbeda
                // untuk hari yang sama.
                'persen'  => $totalGuru > 0 ? min(100, (int) round($h / $totalGuru * 100)) : 0,
            ];
        }

        // ── Tren gaji bersih 6 periode ───────────────────────────────────────
        $periodes = PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')->limit(6)->get();
        $trenGaji = $periodes->map(fn($p) => [
            'label' => Carbon::create($p->tahun, $p->bulan)->locale('id')->isoFormat('MMM YY'),
            'nilai' => (float) Penggajian::where('periode_penggajian_id', $p->id)->sum('gaji_bersih'),
        ])->reverse()->values();

        // ── Distribusi kinerja bulan ini ─────────────────────────────────────
        $rekap = RekapKinerjaBulanan::where('bulan', $bulan)->where('tahun', $tahun)->get();
        $grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
        foreach ($rekap as $r) {
            $g = $setting ? $setting->getGrade($r->skor_total) : 'E';
            if (isset($grades[$g])) $grades[$g]++;
        }
        $kinerjaDistribusi = collect($grades)->map(fn($v, $k) => [
            'grade' => $k, 'jumlah' => $v,
            'warna' => ['A' => '#059669', 'B' => '#0284C7', 'C' => '#F59E0B', 'D' => '#EA580C', 'E' => '#DC2626'][$k],
        ])->values();
        $rataKinerja = round($rekap->avg('skor_total') ?? 0, 1);

        // ── Gantt timeline bulan ini ─────────────────────────────────────────
        $startM = $today->copy()->startOfMonth();
        $endM   = $today->copy()->endOfMonth();
        $jmlHari = $endM->day;
        $weekends = [];
        for ($d = $startM->copy(); $d->lte($endM); $d->addDay()) {
            if ($d->isWeekend()) $weekends[] = $d->day;
        }
        $clampDay = function ($date) use ($startM, $endM) {
            $c = Carbon::parse($date);
            if ($c->lt($startM)) $c = $startM->copy();
            if ($c->gt($endM))   $c = $endM->copy();
            return (int) $c->day;
        };
        $gantt = collect();

        // Periode penggajian yang menyentuh bulan ini
        PeriodePenggajian::where('tanggal_mulai', '<=', $endM)
            ->where('tanggal_selesai', '>=', $startM)->get()
            ->each(function ($p) use (&$gantt, $clampDay) {
                $m = $clampDay($p->tanggal_mulai);
                $s = $clampDay($p->tanggal_selesai);
                $gantt->push([
                    'label' => $p->nama, 'kategori' => 'Penggajian', 'warna' => '#4F46E5',
                    'mulai' => $m, 'durasi' => max(1, $s - $m + 1),
                    'ket' => $p->status,
                ]);
            });

        // Tugas tambahan aktif yang menyentuh bulan ini
        TugasTambahan::aktif()
            ->where('tanggal_mulai', '<=', $endM)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $startM))
            ->orderBy('tanggal_mulai')->limit(8)->get()
            ->each(function ($t) use (&$gantt, $clampDay, $endM) {
                $m = $clampDay($t->tanggal_mulai);
                $s = $clampDay($t->tanggal_selesai ?? $endM);
                $gantt->push([
                    'label' => $t->judul, 'kategori' => 'Tugas', 'warna' => '#0D9488',
                    'mulai' => $m, 'durasi' => max(1, $s - $m + 1),
                    'ket' => 'tugas tambahan',
                ]);
            });

        // Lembur bulan ini
        Lembur::whereBetween('tanggal', [$startM->toDateString(), $endM->toDateString()])
            ->orderBy('tanggal')->limit(8)->get()
            ->each(function ($l) use (&$gantt, $clampDay) {
                $m = $clampDay($l->tanggal);
                $gantt->push([
                    'label' => $l->judul, 'kategori' => 'Lembur', 'warna' => '#DB2777',
                    'mulai' => $m, 'durasi' => 1, 'ket' => $l->status,
                ]);
            });

        // ── Perlu perhatian: kinerja terendah ────────────────────────────────
        // Eager load sekali (dulu loadMissing di dalam map → 5 query terpisah).
        $rekap->loadMissing('tenagaPendidik.user');
        $kinerjaRendah = $rekap->sortBy('skor_total')->take(5)->map(function ($r) use ($setting) {
            return [
                'guru_id' => $r->tenaga_pendidik_id,
                'nama'    => $r->tenagaPendidik?->user?->name ?? '—',
                'skor'    => $r->skor_total,
                'grade'   => $setting ? $setting->getGrade($r->skor_total) : '—',
            ];
        })->values();

        // ── Periode terkini: yang MENCAKUP hari ini (periode 26→25), bukan
        //    yang terakhir dibuat ────────────────────────────────────────────
        $periode = $periodeBerjalan ?? PeriodePenggajian::orderByDesc('tanggal_selesai')->first();
        $periodeTerkini = null;
        if ($periode) {
            $totalFinal = Penggajian::where('periode_penggajian_id', $periode->id)
                ->whereIn('status', ['final', 'dibayar'])->count();
            $periodeTerkini = [
                'id'              => $periode->id,
                'nama'            => $periode->nama,
                'tanggal_mulai'   => Carbon::parse($periode->tanggal_mulai)->format('d M Y'),
                'tanggal_selesai' => Carbon::parse($periode->tanggal_selesai)->format('d M Y'),
                'status'          => $periode->status,
                'total_guru'      => $totalGuru,
                'total_final'     => $totalFinal,
                'gaji_bersih'     => (float) Penggajian::where('periode_penggajian_id', $periode->id)->sum('gaji_bersih'),
            ];
        }

        // ── Monitoring fitur pesantren: lihat monitoringFitur() (dipakai juga
        //    oleh endpoint live agar tidak ada dua versi logika) ────────────────
        $monitoringFitur = $live['monitoringFitur'];

        return Inertia::render('Admin/Dashboard', $this->payloadAwal(
            $live, $monitoringFitur, $totalGuru, $hadirTotal, $terlambat, $izin, $sakit, $belum,
            $donutAbsensi, $trenKehadiran, $trenGaji, $kinerjaDistribusi, $rataKinerja,
            $jmlHari, $today, $gantt, $weekends, $kinerjaRendah, $periodeTerkini, $bulan, $tahun, $startM, $endM
        ));
    }

    /** Susun payload awal halaman (data volatil + data analitik). */
    private function payloadAwal(
        array $live, array $monitoringFitur, int $totalGuru, int $hadirTotal, int $terlambat,
        int $izin, int $sakit, int $belum, array $donutAbsensi, array $trenKehadiran, $trenGaji,
        $kinerjaDistribusi, float $rataKinerja, int $jmlHari, Carbon $today, $gantt, array $weekends,
        $kinerjaRendah, ?array $periodeTerkini, int $bulan, int $tahun, Carbon $startM, Carbon $endM
    ): array {
        return [
            'live'            => $live,
            'monitoringFitur' => $monitoringFitur,
            'stats' => [
                'total_guru'           => $totalGuru,
                'wajib_absen'          => $live['absensi']['wajib_absen'],
                'hadir_hari_ini'       => $hadirTotal,
                'persen_hadir'         => $live['absensi']['persen_hadir'],
                'tidak_hadir_hari_ini' => $belum,
                'terlambat_hari_ini'   => $terlambat,
                'izin_hari_ini'        => $izin,
                'sakit_hari_ini'       => $sakit,
                'rata_kinerja'         => $rataKinerja,
                'lembur_bulan_ini'     => Lembur::whereBetween('tanggal', [$startM->toDateString(), $endM->toDateString()])->count(),
                'punishment_bulan_ini' => PunishmentKinerja::where('bulan', $bulan)->where('tahun', $tahun)->count(),
                'periode_aktif'        => $live['periode']['nama'] ?? null,
            ],
            'donutAbsensi'      => $donutAbsensi,
            'trenKehadiran'     => $trenKehadiran,
            'trenGaji'          => $trenGaji,
            'kinerjaDistribusi' => $kinerjaDistribusi,
            'gantt'             => [
                'hari' => $jmlHari,
                'bulan_label' => $today->locale('id')->isoFormat('MMMM YYYY'),
                'hari_ini' => $today->day, 'weekends' => $weekends, 'items' => $gantt->values(),
            ],
            'kinerjaRendah'  => $kinerjaRendah,
            'periodeTerkini' => $periodeTerkini,
        ];
    }

    /** Kartu monitoring fitur pesantren — dipakai halaman & endpoint live. */
    private function monitoringFitur(Carbon $today): array
    {
        $tgl = $today->toDateString();

        $penggantiQ      = AbsensiMengajar::where('status', 'pengganti')->whereDate('tanggal', $tgl);
        $penggantiTotal  = (clone $penggantiQ)->count();
        $penggantiBelum  = (clone $penggantiQ)->whereNull('jam_selesai_aktual')->count();

        $tahfidzHariIni  = SetoranTahfidz::whereDate('tanggal', $tgl)->count();
        $tahsinHariIni   = TahsinPenilaian::whereDate('tanggal', $tgl)->count();
        $tasmiPending    = TugasTasmi::where('status', 'ditugaskan')->count();

        $ctrlHariIni     = ControllingAbsensi::whereDate('tanggal', $tgl)->count();
        $ctrlAlert       = ControllingAbsensi::whereDate('tanggal', $tgl)->whereIn('status', ['telat', 'alpha'])->count();

        $eksekusiHariIni = OutboxLaporan::whereIn('jenis', ['pelanggaran', 'apresiasi', 'konselor'])->whereDate('created_at', $tgl)->count();
        $eksekusiPending = OutboxLaporan::whereIn('jenis', ['pelanggaran', 'apresiasi', 'konselor'])->whereIn('status', ['pending', 'failed'])->count();

        $piketHariIni    = PiketJadwal::whereDate('tanggal', $tgl)->count();
        $sanggahPending  = PiketPenilaian::where('status_sanggah', 'diajukan')->count();

        $kegiatanAktif   = AbsensiKegiatan::where('status', 'berlangsung')->count();
        $outboxFailed    = OutboxLaporan::where('status', 'failed')->count();

        $monitoringFitur = [
            ['label' => 'Tahfidz', 'icon' => 'book', 'tone' => 'violet',
             'value' => $tahfidzHariIni, 'satuan' => 'setoran hari ini',
             'alert' => $tasmiPending, 'alert_label' => 'tasmi menunggu',
             'url' => route('admin.smart-education.tahfidz-monitoring.index')],

            ['label' => 'Tahsin', 'icon' => 'book', 'tone' => 'indigo',
             'value' => $tahsinHariIni, 'satuan' => 'penilaian hari ini',
             'alert' => 0, 'alert_label' => null,
             'url' => route('admin.smart-education.tahsin-monitoring.index')],

            ['label' => 'Guru Pengganti', 'icon' => 'swap', 'tone' => 'amber',
             'value' => $penggantiTotal, 'satuan' => 'sesi pengganti hari ini',
             'alert' => $penggantiBelum, 'alert_label' => 'belum diabsen',
             'url' => route('admin.smart-payroll.absensi.mengajar')],

            ['label' => 'Smart Controlling', 'icon' => 'scan', 'tone' => 'blue',
             'value' => $ctrlHariIni, 'satuan' => 'scan hari ini',
             'alert' => $ctrlAlert, 'alert_label' => 'telat/alpha',
             'url' => route('admin.smart-habbit.controlling.rekap')],

            ['label' => 'Smart Eksekusi', 'icon' => 'flag', 'tone' => 'rose',
             'value' => $eksekusiHariIni, 'satuan' => 'laporan hari ini',
             'alert' => $eksekusiPending, 'alert_label' => 'perlu dikirim/gagal',
             'url' => route('admin.smart-habbit.eksekusi.index')],

            ['label' => 'Guru Piket', 'icon' => 'shield', 'tone' => 'emerald',
             'value' => $piketHariIni, 'satuan' => 'petugas hari ini',
             'alert' => $sanggahPending, 'alert_label' => 'sanggah menunggu',
             'url' => route('admin.piket.sanggah.index')],

            ['label' => 'Absensi Kegiatan', 'icon' => 'users', 'tone' => 'teal',
             'value' => $kegiatanAktif, 'satuan' => 'kegiatan berlangsung',
             'alert' => 0, 'alert_label' => null,
             'url' => route('admin.smart-payroll.absensi-kegiatan.index')],

            ['label' => 'Integrasi RamahAnak', 'icon' => 'link', 'tone' => $outboxFailed > 0 ? 'rose' : 'gray',
             'value' => $outboxFailed, 'satuan' => 'laporan gagal kirim',
             'alert' => $outboxFailed, 'alert_label' => $outboxFailed > 0 ? 'perlu dicek' : null,
             'url' => route('admin.smart-habbit.outbox.index')],
        ];

        return $monitoringFitur;
    }
}
