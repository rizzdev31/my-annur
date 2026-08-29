<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\PenugasanTambahan;
use App\Models\PiketJadwal;
use App\Models\HariLibur;
use App\Services\AbsensiWindowService;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Ringkasan dashboard guru — agregasi real-time dari fitur yang berjalan
 * (absen harian, mengajar hari ini, kinerja bulan, izin, tugas, piket).
 */
class DashboardApiController extends Controller
{
    public function ringkasan(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 404);
        }

        $today = TimezoneHelper::today();
        $tgl   = $today->toDateString();
        $now   = TimezoneHelper::now();
        $hari  = TimezoneHelper::namaHariDB($today);
        $bulan = (int) $now->format('n');
        $tahun = (int) $now->format('Y');

        // ── Absen harian ──────────────────────────────────────────────────────
        // Sumber TUNGGAL (sama dgn halaman Absensi) → Beranda & Absensi selalu sepakat
        // soal tanggal kerja efektif + window check-in yang terhubung ke jam kerja.
        $absenStatus = AbsensiWindowService::statusAbsen($tp, $request->device_date);
        $absen       = $absenStatus['absen'];

        // ── Jadwal mengajar hari ini (semua tipe) ─────────────────────────────
        $jadwalHariIni = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])
            ->where('tenaga_pendidik_id', $tp->id)->where('hari', $hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderBy('jam_mulai')->get();

        $amToday = AbsensiMengajar::where('tenaga_pendidik_id', $tp->id)->whereDate('tanggal', $tgl)
            ->pluck('status', 'jadwal_mengajar_id');

        $totalJadwal = $jadwalHariIni->count();
        $sudahAbsen  = $jadwalHariIni->filter(fn($j) => $amToday->has($j->id))->count();
        $jpHariIni   = (int) $jadwalHariIni->sum('jumlah_jp');

        $next = $jadwalHariIni->first(function ($j) use ($tgl, $now, $amToday) {
            if ($amToday->has($j->id)) return false;
            $end = Carbon::parse("$tgl {$j->jam_selesai}", TimezoneHelper::TZ);
            return $now->lte($end);
        });

        // ── Bulan ini ─────────────────────────────────────────────────────────
        $amBulan = AbsensiMengajar::where('tenaga_pendidik_id', $tp->id)
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->get();
        $jpBulan        = (int) $amBulan->whereIn('status', ['hadir', 'terlaksana', 'libur', 'izin'])->sum('jp_terlaksana');
        $sesiTerlaksana = $amBulan->where('status', 'terlaksana')->count();

        // ── Lainnya ───────────────────────────────────────────────────────────
        $izinPending = PengajuanIzin::where('tenaga_pendidik_id', $tp->id)->where('status', 'pending')->count();
        $tugasAktif  = PenugasanTambahan::where('tenaga_pendidik_id', $tp->id)
            ->where('disetujui', true)->where('status_pengerjaan', '!=', 'selesai')->count();
        $isPiket     = PiketJadwal::where('tenaga_pendidik_id', $tp->id)->whereDate('tanggal', $tgl)->exists();

        // Breakdown progres tugas tambahan (untuk kartu "Progres Tugas")
        $tugasAll = PenugasanTambahan::where('tenaga_pendidik_id', $tp->id)
            ->where('disetujui', true)->get(['status_pengerjaan']);
        $tugasTotal    = $tugasAll->count();
        $tugasSelesai  = $tugasAll->where('status_pengerjaan', 'selesai')->count();
        $tugasProses   = $tugasAll->where('status_pengerjaan', 'sedang')->count();
        $tugasTertunda = $tugasAll->whereIn('status_pengerjaan', ['belum', 'tidak_selesai'])->count();
        $tugasPersen   = $tugasTotal > 0 ? (int) round($tugasSelesai / $tugasTotal * 100) : 0;

        // ── Perlu perhatian per fitur (badge real-time) ─────────────────────────
        $belum = $jadwalHariIni->filter(fn($j) => !$amToday->has($j->id));
        $tipe = fn($j) => $j->mataPelajaran?->tipe ?? 'reguler';
        $mengajarPerlu = $belum->filter(fn($j) => in_array($tipe($j), ['reguler', null], true) || $tipe($j) === 'reguler')->count();
        $tahfidzPerlu  = $belum->filter(fn($j) => $tipe($j) === 'tahfidz')->count();
        $tahsinPerlu   = $belum->filter(fn($j) => $tipe($j) === 'tahsin')->count();

        $penggantiPerlu = AbsensiMengajar::where('digantikan_oleh', $tp->id)->whereDate('tanggal', $tgl)
            ->where('status', 'pengganti')->where('jp_terlaksana', 0)->count();
        $tasmiPerlu = \App\Models\TugasTasmi::where('penguji_id', $tp->id)->where('status', 'ditugaskan')->count();

        $nama = $request->user()->name ?? '';
        $eksekusiPending = \App\Models\OutboxLaporan::whereIn('jenis', ['pelanggaran', 'apresiasi', 'konselor'])
            ->whereIn('status', ['pending', 'failed'])
            ->when($nama !== '', fn($q) => $q->where('actor', 'like', "%{$nama}%"))->count();

        return response()->json(['success' => true, 'data' => [
            'tanggal'   => $tgl,
            'hari_label'=> $today->locale('id')->isoFormat('dddd, D MMMM Y'),
            'is_libur'  => HariLibur::isLibur($tgl),
            'absen_harian' => [
                'sudah_masuk'  => $absenStatus['sudah_checkin'],
                'sudah_pulang' => $absenStatus['sudah_checkout'],
                'jam_masuk'    => $absen?->jam_masuk ? substr($absen->jam_masuk, 0, 5) : null,
                'jam_pulang'   => $absen?->jam_pulang ? substr($absen->jam_pulang, 0, 5) : null,
                'status'       => $absenStatus['status'],
                // Window check-in terhubung jam kerja (buka 30 mnt sebelum jam masuk):
                'boleh_checkin'          => $absenStatus['boleh_checkin'],
                'bisa_checkin_mulai'     => $absenStatus['bisa_checkin_mulai'],
                'menit_menunggu_checkin' => $absenStatus['menit_menunggu_checkin'],
                'jadwal_masuk'           => $absenStatus['jam_masuk'],
                'jadwal_pulang'          => $absenStatus['jam_pulang'],
                'is_libur'               => $absenStatus['is_libur'],
            ],
            'mengajar' => [
                'total'       => $totalJadwal,
                'sudah_absen' => $sudahAbsen,
                'jp_hari_ini' => $jpHariIni,
                'daftar'      => $jadwalHariIni->map(fn($j) => [
                    'mapel'       => $j->mataPelajaran?->nama ?? '—',
                    'kelas'       => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                    'jam_mulai'   => substr($j->jam_mulai, 0, 5),
                    'jam_selesai' => substr($j->jam_selesai, 0, 5),
                    'tipe'        => $j->mataPelajaran?->tipe ?? 'reguler',
                    'sudah_absen' => $amToday->has($j->id),
                    'is_next'     => $next && $next->id === $j->id,
                ])->values(),
                'berikutnya'  => $next ? [
                    'mapel' => $next->mataPelajaran?->nama ?? '—',
                    'kelas' => $next->kelasRel?->nama ?? $next->kelas ?? '—',
                    'jam'   => substr($next->jam_mulai, 0, 5) . '–' . substr($next->jam_selesai, 0, 5),
                    'tipe'  => $next->mataPelajaran?->tipe ?? 'reguler',
                ] : null,
            ],
            'bulan_ini' => [
                'jp'              => $jpBulan,
                'sesi_terlaksana' => $sesiTerlaksana,
                'label'           => $now->locale('id')->isoFormat('MMMM Y'),
            ],
            'izin_pending' => $izinPending,
            'tugas_aktif'  => $tugasAktif,
            'tugas' => [
                'total'    => $tugasTotal,
                'selesai'  => $tugasSelesai,
                'proses'   => $tugasProses,
                'tertunda' => $tugasTertunda,
                'persen'   => $tugasPersen,
            ],
            'is_piket'     => $isPiket,
            // Badge "perlu perhatian" per fitur penting.
            'perlu_perhatian' => [
                'mengajar'  => $mengajarPerlu,
                'tahfidz'   => $tahfidzPerlu,
                'tahsin'    => $tahsinPerlu,
                'pengganti' => $penggantiPerlu,
                'tasmi'     => $tasmiPerlu,
                'eksekusi'  => $eksekusiPending,
            ],
        ]]);
    }
}
