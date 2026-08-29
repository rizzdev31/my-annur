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
    public function index()
    {
        $today   = Carbon::today();
        $bulan   = $today->month;
        $tahun   = $today->year;
        $setting = SettingKinerja::getDefault();
        $totalGuru = TenagaPendidik::aktif()->count();

        // ── Absensi hari ini ─────────────────────────────────────────────────
        $absensiHariIni = AbsensiHarian::with(['tenagaPendidik.user', 'tenagaPendidik.jabatan'])
            ->whereDate('tanggal', $today)->get();

        $hadir     = $absensiHariIni->whereIn('status', ['hadir', 'dinas_luar'])->count();
        $terlambat = $absensiHariIni->where('status', 'terlambat')->count();
        $izin      = $absensiHariIni->whereIn('status', ['izin', 'izin_sakit'])->count();
        $sakit     = $absensiHariIni->where('status', 'sakit')->count();
        $hadirTotal = $hadir + $terlambat;
        $belum     = max(0, $totalGuru - ($hadirTotal + $izin + $sakit));

        $donutAbsensi = [
            ['label' => 'Hadir',     'value' => $hadir,     'color' => '#059669'],
            ['label' => 'Terlambat', 'value' => $terlambat, 'color' => '#F59E0B'],
            ['label' => 'Izin',      'value' => $izin,      'color' => '#0284C7'],
            ['label' => 'Sakit',     'value' => $sakit,     'color' => '#7C3AED'],
            ['label' => 'Belum',     'value' => $belum,     'color' => '#CBD5E1'],
        ];

        // ── Tren kehadiran 7 hari terakhir ───────────────────────────────────
        $mulai7 = $today->copy()->subDays(6);
        $absen7 = AbsensiHarian::whereBetween('tanggal', [$mulai7->toDateString(), $today->toDateString()])
            ->get(['tanggal', 'status']);
        $trenKehadiran = [];
        for ($d = $mulai7->copy(); $d->lte($today); $d->addDay()) {
            $tgl = $d->toDateString();
            $h = $absen7->where('tanggal', $tgl)
                ->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])->count();
            $trenKehadiran[] = [
                'label'  => $d->locale('id')->isoFormat('dd'),
                'hadir'  => $h,
                'persen' => $totalGuru > 0 ? round($h / $totalGuru * 100) : 0,
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
        $kinerjaRendah = $rekap->sortBy('skor_total')->take(5)->map(function ($r) use ($setting) {
            $r->loadMissing('tenagaPendidik.user');
            return [
                'guru_id' => $r->tenaga_pendidik_id,
                'nama'    => $r->tenagaPendidik?->user?->name ?? '—',
                'skor'    => $r->skor_total,
                'grade'   => $setting ? $setting->getGrade($r->skor_total) : '—',
            ];
        })->values();

        // ── Antrian verifikasi (pending) ─────────────────────────────────────
        $tugasPending  = TugasTambahan::aktif()->count(); // ringkas
        $lemburPending = Lembur::where('status', 'diajukan')->count();

        // ── Periode terkini ──────────────────────────────────────────────────
        $periode = PeriodePenggajian::latest()->first();
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

        // ── Monitoring fitur pesantren (real-time hari ini + antrian) ────────────
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

        return Inertia::render('Admin/Dashboard', [
            'monitoringFitur' => $monitoringFitur,
            'stats' => [
                'total_guru'         => $totalGuru,
                'hadir_hari_ini'     => $hadirTotal,
                'persen_hadir'       => $totalGuru > 0 ? round($hadirTotal / $totalGuru * 100) : 0,
                'tidak_hadir_hari_ini' => $belum,
                'terlambat_hari_ini' => $terlambat,
                'izin_hari_ini'      => $izin,
                'sakit_hari_ini'     => $sakit,
                'rata_kinerja'       => $rataKinerja,
                'lembur_bulan_ini'   => Lembur::whereBetween('tanggal', [$startM->toDateString(), $endM->toDateString()])->count(),
                'punishment_bulan_ini' => PunishmentKinerja::where('bulan', $bulan)->where('tahun', $tahun)->count(),
                'lembur_pending'     => $lemburPending,
                'periode_aktif'      => $periode?->nama,
            ],
            'donutAbsensi'      => $donutAbsensi,
            'trenKehadiran'     => $trenKehadiran,
            'trenGaji'          => $trenGaji,
            'kinerjaDistribusi' => $kinerjaDistribusi,
            'gantt'             => ['hari' => $jmlHari, 'bulan_label' => $today->locale('id')->isoFormat('MMMM YYYY'), 'hari_ini' => $today->day, 'weekends' => $weekends, 'items' => $gantt->values()],
            'kinerjaRendah'     => $kinerjaRendah,
            'periodeTerkini'    => $periodeTerkini,
        ]);
    }
}
