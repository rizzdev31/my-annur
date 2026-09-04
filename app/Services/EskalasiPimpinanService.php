<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\LiburTendik;
use App\Models\Pengawas;
use App\Models\PengajuanIzin;
use App\Models\RekapKinerjaBulanan;
use App\Models\TenagaPendidik;
use Carbon\Carbon;

/**
 * ESKALASI KE PIMPINAN — kirim RINGKASAN anomali ke tiap pengawas, hanya untuk
 * modul & cakupan guru yang diberikan superadmin.
 *
 * Prinsip:
 *  - AGREGAT: satu notifikasi per jenis per hari (bukan per guru) → tidak spam.
 *  - DEDUP  : kunci per jenis+tanggal, jadi aman dijalankan tiap jam.
 *  - Hormati gerbang PengawasService (cakupan & diri sendiri dikecualikan).
 */
class EskalasiPimpinanService
{
    /** Ambang skor kinerja yang dianggap perlu perhatian. */
    public const AMBANG_KINERJA = 70;

    public function __construct(private PengawasService $pengawas) {}

    /** Jalankan untuk semua pengawas aktif. Return jumlah notifikasi terkirim. */
    public function jalankan(?string $tanggal = null): int
    {
        $tgl   = $tanggal ? Carbon::parse($tanggal) : TimezoneHelper::today();
        $today = $tgl->toDateString();
        $now   = TimezoneHelper::now();
        $kirim = 0;

        // Libur nasional/pesantren → tak ada eskalasi absen/mengajar.
        $liburUmum = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $today)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today))
            ->exists();

        foreach (Pengawas::aktif()->with('tenagaPendidik.user')->get() as $p) {
            $user = $p->tenagaPendidik?->user;
            if (!$user) continue;

            $guruIds = $this->pengawas->idGuruDiawasi($p->tenaga_pendidik_id);
            if (empty($guruIds)) continue;

            $modul = (array) ($p->modul ?? []);

            if (!$liburUmum && in_array('absen_harian', $modul, true)) {
                $kirim += $this->belumAbsen($user, $guruIds, $tgl, $now);
            }
            if (!$liburUmum && in_array('absen_mengajar', $modul, true)) {
                $kirim += $this->sesiTerlewat($user, $guruIds, $tgl, $now);
            }
            if (in_array('perizinan', $modul, true)) {
                $kirim += $this->izinMenunggu($user, $guruIds, $today, (bool) $p->boleh_setujui_izin);
            }
            if (in_array('kinerja', $modul, true)) {
                $kirim += $this->kinerjaRendah($user, $guruIds, $tgl);
            }
        }

        return $kirim;
    }

    /** Guru yang belum absen padahal jam masuk (+toleransi) sudah lewat. */
    private function belumAbsen($user, array $guruIds, Carbon $tgl, Carbon $now): int
    {
        $today    = $tgl->toDateString();
        $namaHari = TimezoneHelper::namaHariDB($tgl);

        $sudah = AbsensiHarian::whereDate('tanggal', $today)
            ->whereIn('tenaga_pendidik_id', $guruIds)->pluck('tenaga_pendidik_id')->flip();
        $izin = PengajuanIzin::where('status', 'disetujui')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->where('tanggal_mulai', '<=', $today)->where('tanggal_selesai', '>=', $today)
            ->pluck('tenaga_pendidik_id')->flip();

        $nama = [];
        foreach (TenagaPendidik::with('user:id,name')->whereIn('id', $guruIds)->get() as $g) {
            if ($sudah->has($g->id) || $izin->has($g->id)) continue;
            if (LiburTendik::isLibur($g->id, $today)) continue;

            $jk = $g->jamKerjaAktif();
            $jadwal = $jk?->getJamUntukHari($namaHari);
            if (!$jadwal) continue;                       // libur mingguan guru ini

            $batas = Carbon::parse($today . ' ' . $jadwal['jam_masuk'], TimezoneHelper::TZ)
                ->addMinutes((int) ($jadwal['toleransi'] ?? 15));
            if ($now->lte($batas)) continue;              // belum lewat batas → belum dieskalasi

            $nama[] = $g->user?->name ?? ('Guru #' . $g->id);
        }
        if (empty($nama)) return 0;

        return $this->kirim($user, 'Belum absen masuk',
            count($nama) . ' guru belum absen masuk hari ini: ' . $this->ringkasNama($nama),
            'absen-' . $today, 'peringatan');
    }

    /** Sesi mengajar yang jamnya sudah lewat tapi tak tercatat / tak terlaksana. */
    private function sesiTerlewat($user, array $guruIds, Carbon $tgl, Carbon $now): int
    {
        $today    = $tgl->toDateString();
        $namaHari = TimezoneHelper::namaHariDB($tgl);

        $jadwal = JadwalMengajar::with(['tenagaPendidik.user:id,name', 'mataPelajaran:id,nama'])
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->where('hari', $namaHari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->get();
        if ($jadwal->isEmpty()) return 0;

        $absensi = AbsensiMengajar::whereDate('tanggal', $today)
            ->whereIn('jadwal_mengajar_id', $jadwal->pluck('id'))
            ->get()->keyBy('jadwal_mengajar_id');

        $nama = [];
        foreach ($jadwal as $j) {
            $selesai = Carbon::parse($today . ' ' . $j->jam_selesai, TimezoneHelper::TZ);
            if ($now->lte($selesai)) continue;            // sesi belum berakhir

            $a = $absensi->get($j->id);
            // Bermasalah bila TIDAK ada catatan sama sekali, atau ditandai tidak terlaksana.
            if ($a && !in_array($a->status, ['tidak_terlaksana'], true)) continue;

            $nama[] = ($j->tenagaPendidik?->user?->name ?? 'Guru')
                . ' (' . ($j->mataPelajaran?->nama ?? 'KBM') . ' ' . $j->kelas . ')';
        }
        if (empty($nama)) return 0;

        return $this->kirim($user, 'Sesi mengajar terlewat',
            count($nama) . ' sesi belum tercatat hari ini: ' . $this->ringkasNama($nama),
            'mengajar-' . $today, 'peringatan');
    }

    /** Pengajuan izin guru binaan yang masih menunggu keputusan. */
    private function izinMenunggu($user, array $guruIds, string $today, bool $bolehSetujui): int
    {
        $n = PengajuanIzin::where('status', 'pending')->whereIn('tenaga_pendidik_id', $guruIds)->count();
        if ($n === 0) return 0;

        return $this->kirim($user, 'Izin menunggu keputusan',
            "$n pengajuan izin guru binaan Anda menunggu"
                . ($bolehSetujui ? ' persetujuan Anda.' : ' keputusan admin.'),
            'izin-' . $today, $bolehSetujui ? 'peringatan' : 'info');
    }

    /** Skor kinerja bulan LALU di bawah ambang (dievaluasi sekali per bulan). */
    private function kinerjaRendah($user, array $guruIds, Carbon $tgl): int
    {
        $lalu  = $tgl->copy()->subMonthNoOverflow();
        $bulan = (int) $lalu->month;
        $tahun = (int) $lalu->year;

        $rendah = RekapKinerjaBulanan::with('tenagaPendidik.user:id,name')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->whereNotNull('skor_total')->where('skor_total', '<', self::AMBANG_KINERJA)
            ->get();
        if ($rendah->isEmpty()) return 0;

        $nama = $rendah->map(fn($r) => ($r->tenagaPendidik?->user?->name ?? 'Guru')
            . ' (' . round((float) $r->skor_total, 1) . ')')->all();

        return $this->kirim($user, 'Kinerja di bawah ambang',
            count($nama) . ' guru berskor < ' . self::AMBANG_KINERJA . " pada $bulan/$tahun: " . $this->ringkasNama($nama),
            "kinerja-$tahun-$bulan", 'peringatan');
    }

    /** Ringkas daftar nama agar pesan tak kepanjangan. */
    private function ringkasNama(array $nama, int $maks = 5): string
    {
        $tampil = array_slice($nama, 0, $maks);
        $sisa   = count($nama) - count($tampil);
        return implode(', ', $tampil) . ($sisa > 0 ? " (+$sisa lainnya)" : '');
    }

    private function kirim($user, string $judul, string $pesan, string $dedup, string $tipe): int
    {
        return NotifikasiService::event('eskalasi.pimpinan', [
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe'  => $tipe,
            'dedup' => $dedup,
            'data'  => ['type' => 'monitoring', 'route' => '/monitoring'],
        ], [$user]);
    }
}
