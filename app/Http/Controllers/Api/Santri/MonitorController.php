<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\AbsensiSantri;
use App\Models\ControllingAbsensi;
use App\Models\IzinSantri;
use App\Models\SetoranTahfidz;
use App\Models\SmartHealthLaporan;
use App\Models\TahsinPenilaianRiwayat;
use App\Models\TugasTasmi;
use App\Models\TugasTasnif;
use App\Services\TahfidzService;
use App\Services\TahsinService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal Santri — MONITORING READ-ONLY. Semua query di-scope ke santri token.
 */
class MonitorController extends Controller
{
    private function periode(Request $request): array
    {
        $now = $request->filled('bulan') ? Carbon::parse($request->bulan . '-01') : Carbon::now();
        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $now->locale('id')->isoFormat('MMMM YYYY')];
    }

    /** GET /api/santri/beranda — profil + statistik detail (chart/tabel). */
    public function beranda(Request $request): JsonResponse
    {
        $s = $request->user();
        [$dari, $sampai, $label] = $this->periode($request);

        // KBM bulan ini (breakdown)
        $kbm = AbsensiSantri::where('santri_id', $s->id)
            ->whereHas('absensiMengajar', fn($q) => $q->whereBetween('tanggal', [$dari, $sampai]))
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $kbmTotal = (int) $kbm->sum();
        $kbmHadir = (int) ($kbm['hadir'] ?? 0);

        // Smart Controlling bulan ini (breakdown)
        $ctrl = ControllingAbsensi::where('santri_id', $s->id)->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $ctrlTotal = (int) $ctrl->sum();

        // Tren kehadiran KBM 6 bulan terakhir
        $mulai6 = now()->copy()->subMonths(5)->startOfMonth();
        $trenRaw = AbsensiSantri::where('absensi_santri.santri_id', $s->id)
            ->join('absensi_mengajar', 'absensi_mengajar.id', '=', 'absensi_santri.absensi_mengajar_id')
            ->where('absensi_mengajar.tanggal', '>=', $mulai6->toDateString())
            ->selectRaw("DATE_FORMAT(absensi_mengajar.tanggal,'%Y-%m') ym,
                SUM(absensi_santri.status='hadir') hadir,
                SUM(absensi_santri.status='telat') telat,
                SUM(absensi_santri.status='alpha') alpha,
                COUNT(*) total")
            ->groupBy('ym')->get()->keyBy('ym');
        $tren = collect(range(0, 5))->map(function ($i) use ($mulai6, $trenRaw) {
            $m = $mulai6->copy()->addMonths($i);
            $r = $trenRaw->get($m->format('Y-m'));
            $tot = (int) ($r->total ?? 0);
            return [
                'bulan' => $m->locale('id')->isoFormat('MMM'),
                'hadir' => (int) ($r->hadir ?? 0), 'telat' => (int) ($r->telat ?? 0), 'alpha' => (int) ($r->alpha ?? 0),
                'total' => $tot, 'persen' => $tot ? round(((int) ($r->hadir ?? 0)) / $tot * 100) : 0,
            ];
        })->values();

        // Tahfidz + Tahsin
        $stat = (new TahfidzService())->statusSantri($s->id);
        $tasmiLulus = TugasTasmi::where('santri_id', $s->id)->where('status', 'selesai')->where('lulus', true)->count();
        $level = (int) ($s->tahsin_level ?? 1);
        $materiTotal = \App\Models\SettingTahsinMateri::where('is_aktif', true)->where('level', $level)->count();
        $materiLulus = \App\Models\TahsinPenilaian::where('santri_id', $s->id)->where('level', $level)->where('lulus', true)->count();
        $tasnifLulus = TugasTasnif::where('santri_id', $s->id)->where('status', 'selesai')->where('lulus', true)->count();

        // Counter modul lain
        $izinTotal = IzinSantri::where('santri_id', $s->id)->count();
        $izinDisetujui = IzinSantri::where('santri_id', $s->id)->where('status', 'disetujui')->count();
        $kesehatanAktif = SmartHealthLaporan::where('santri_id', $s->id)->whereIn('status', ['menunggu', 'dalam_pengecekan'])->count();

        $kelas = $s->kelas()->first();

        return response()->json(['success' => true, 'data' => [
            'santri' => [
                'nama' => $s->nama_lengkap, 'nis' => $s->nip, 'foto' => $s->foto ? url('storage/' . $s->foto) : null,
                'kelas' => $kelas?->nama, 'program' => $s->program_quran, 'tahsin_level' => $level,
                'tahsin_label' => TahsinService::levelLabel($level),
            ],
            'bulan' => $label,
            'kbm' => [
                'hadir' => $kbmHadir, 'telat' => (int) ($kbm['telat'] ?? 0), 'alpha' => (int) ($kbm['alpha'] ?? 0),
                'total' => $kbmTotal, 'persen_hadir' => $kbmTotal ? round($kbmHadir / $kbmTotal * 100) : 0,
            ],
            'controlling' => [
                'hadir' => (int) ($ctrl['hadir'] ?? 0), 'telat' => (int) ($ctrl['telat'] ?? 0), 'alpha' => (int) ($ctrl['alpha'] ?? 0),
                'total' => $ctrlTotal, 'persen_hadir' => $ctrlTotal ? round(((int) ($ctrl['hadir'] ?? 0)) / $ctrlTotal * 100) : 0,
            ],
            'tren' => $tren,
            'tahfidz' => [
                'persen' => $stat['persen'] ?? 0, 'juz_selesai' => $stat['juz_selesai_total'] ?? 0,
                'total_ayat' => $stat['total_ayat'] ?? 0, 'tasmi_lulus' => $tasmiLulus,
            ],
            'tahsin' => [
                'level' => $level, 'label' => TahsinService::levelLabel($level),
                'materi_lulus' => $materiLulus, 'materi_total' => $materiTotal,
                'persen' => $materiTotal ? round($materiLulus / $materiTotal * 100) : 0, 'tasnif_lulus' => $tasnifLulus,
            ],
            'lainnya' => ['izin_total' => $izinTotal, 'izin_disetujui' => $izinDisetujui, 'kesehatan_aktif' => $kesehatanAktif],
        ]]);
    }

    /** GET /api/santri/absensi — rekap KBM per bulan. */
    public function absensi(Request $request): JsonResponse
    {
        $s = $request->user();
        [$dari, $sampai, $label] = $this->periode($request);

        $rows = AbsensiSantri::where('santri_id', $s->id)
            ->whereHas('absensiMengajar', fn($q) => $q->whereBetween('tanggal', [$dari, $sampai]))
            ->with(['absensiMengajar.jadwalMengajar.mataPelajaran', 'absensiMengajar.tenagaPendidik.user:id,name'])
            ->get()
            ->sortByDesc(fn($a) => optional($a->absensiMengajar)->tanggal)
            ->map(fn($a) => [
                'tanggal' => optional(optional($a->absensiMengajar)->tanggal)?->locale('id')->isoFormat('dd, D MMM'),
                'mapel'   => $a->absensiMengajar?->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                'guru'    => $a->absensiMengajar?->tenagaPendidik?->user?->name ?? '—',
                'status'  => $a->status,
            ])->values();

        $rekap = $rows->groupBy('status')->map->count();
        return response()->json(['success' => true, 'data' => [
            'periode' => $label,
            'rekap'   => ['hadir' => (int) ($rekap['hadir'] ?? 0), 'telat' => (int) ($rekap['telat'] ?? 0), 'alpha' => (int) ($rekap['alpha'] ?? 0), 'total' => $rows->count()],
            'rows'    => $rows,
        ]]);
    }

    /** GET /api/santri/tahfidz — progres + riwayat setoran + tasmi lulus. */
    public function tahfidz(Request $request): JsonResponse
    {
        $s = $request->user();
        $stat = (new TahfidzService())->statusSantri($s->id);

        $riwayat = SetoranTahfidz::where('santri_id', $s->id)->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'tanggal' => optional($r->tanggal)?->locale('id')->isoFormat('dd, D MMM YYYY'),
                'jenis' => $r->jenis, 'jumlah_ayat' => $r->jumlah_ayat, 'nilai' => $r->nilai, 'lulus' => $r->lulus, 'catatan' => $r->catatan,
            ]);

        $tasmi = TugasTasmi::where('santri_id', $s->id)->where('status', 'selesai')->where('lulus', true)
            ->with('penguji.user:id,name')->orderBy('juz')->get()
            ->map(fn($t) => [
                'id' => $t->id, 'juz' => $t->juz, 'nilai' => $t->nilai,
                'rubrik' => ['kelancaran' => $t->nilai_kelancaran, 'makhorijul_huruf' => $t->nilai_makhorijul_huruf, 'tajwid' => $t->nilai_tajwid, 'fashohah' => $t->nilai_fashohah],
                'rubrik_ada' => $t->nilai_kelancaran !== null,
                'penguji' => $t->penguji?->user?->name ?? '—',
                'tanggal' => optional($t->updated_at)->locale('id')->isoFormat('D MMM YYYY'),
            ]);

        return response()->json(['success' => true, 'data' => [
            'total_ayat' => $stat['total_ayat'] ?? 0, 'persen' => $stat['persen'] ?? 0,
            'juz_selesai' => $stat['juz_selesai_total'] ?? 0,
            'riwayat' => $riwayat, 'tasmi_lulus' => $tasmi,
        ]]);
    }

    /** GET /api/santri/tahsin — level + riwayat + tasnif lulus. */
    public function tahsin(Request $request): JsonResponse
    {
        $s = $request->user();
        $level = $s->tahsin_level ?? 1;

        $riwayat = TahsinPenilaianRiwayat::where('santri_id', $s->id)->with('materi:id,nama')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'tanggal' => optional($r->tanggal)?->locale('id')->isoFormat('dd, D MMM YYYY'),
                'level' => $r->level, 'materi' => $r->materi?->nama ?? '—', 'nilai' => $r->nilai, 'lulus' => $r->lulus, 'catatan' => $r->catatan,
            ]);

        $tasnif = TugasTasnif::where('santri_id', $s->id)->where('status', 'selesai')->where('lulus', true)
            ->with('penguji.user:id,name')->orderBy('level')->get()
            ->map(fn($t) => [
                'id' => $t->id, 'level' => $t->level, 'level_label' => TahsinService::levelLabel($t->level), 'nilai' => $t->nilai,
                'rubrik' => ['pemahaman_materi' => $t->nilai_pemahaman_materi, 'kelancaran' => $t->nilai_kelancaran, 'fashohah' => $t->nilai_fashohah, 'makhorijul_huruf' => $t->nilai_makhorijul_huruf],
                'penguji' => $t->penguji?->user?->name ?? '—',
                'tanggal' => optional($t->updated_at)->locale('id')->isoFormat('D MMM YYYY'),
            ]);

        return response()->json(['success' => true, 'data' => [
            'level' => $level, 'level_label' => TahsinService::levelLabel((int) $level),
            'materi' => collect((new TahsinService())->materiSantri($s->id, (int) $level))->values(),
            'riwayat' => $riwayat, 'tasnif_lulus' => $tasnif,
        ]]);
    }

    /** GET /api/santri/controlling — kehadiran kegiatan harian per bulan. */
    public function controlling(Request $request): JsonResponse
    {
        $s = $request->user();
        [$dari, $sampai, $label] = $this->periode($request);

        $rows = ControllingAbsensi::where('santri_id', $s->id)->whereBetween('tanggal', [$dari, $sampai])
            ->with('kegiatan:id,nama')->orderByDesc('tanggal')->get()
            ->map(fn($a) => [
                'tanggal' => Carbon::parse($a->tanggal)->locale('id')->isoFormat('dd, D MMM'),
                'kegiatan' => $a->kegiatan?->nama ?? '—', 'status' => $a->status, 'jam' => $a->jam_scan,
            ]);
        $rekap = $rows->groupBy('status')->map->count();

        return response()->json(['success' => true, 'data' => [
            'periode' => $label,
            'rekap' => ['hadir' => (int) ($rekap['hadir'] ?? 0), 'telat' => (int) ($rekap['telat'] ?? 0), 'alpha' => (int) ($rekap['alpha'] ?? 0), 'total' => $rows->count()],
            'rows' => $rows,
        ]]);
    }

    /** GET /api/santri/izin — daftar izin. */
    public function izin(Request $request): JsonResponse
    {
        $s = $request->user();
        $rows = IzinSantri::where('santri_id', $s->id)->orderByDesc('id')->limit(100)->get()
            ->map(fn($i) => [
                'jenis' => $i->jenis, 'jenis_label' => $i->jenis_label ?? $i->jenis, 'alasan' => $i->alasan,
                'tanggal_mulai' => optional($i->tanggal_mulai)?->toDateString(), 'tanggal_selesai' => optional($i->tanggal_selesai)?->toDateString(),
                'status' => $i->status, 'catatan' => $i->catatan_petugas,
            ]);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/santri/kesehatan — laporan + timeline pengecekan. */
    public function kesehatan(Request $request): JsonResponse
    {
        $s = $request->user();
        $rows = SmartHealthLaporan::where('santri_id', $s->id)->with('pengecekan')->orderByDesc('id')->limit(50)->get()
            ->map(fn($l) => [
                'penyakit' => $l->deskripsi_penyakit, 'status' => $l->status, 'kondisi_akhir' => $l->kondisi_akhir,
                'foto' => $l->foto ? url('storage/' . $l->foto) : null,
                'tanggal' => optional($l->created_at)->locale('id')->isoFormat('D MMM YYYY'),
                'riwayat' => ($l->pengecekan ?? collect())->sortBy('hari_ke')->map(fn($p) => [
                    'hari_ke' => $p->hari_ke, 'keputusan' => $p->keputusan, 'catatan' => $p->catatan,
                    'tanggal' => optional($p->tanggal)?->locale('id')->isoFormat('D MMM'),
                ])->values(),
            ]);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/santri/tahfidz/tasmi/{id}/sertifikat — data sertifikat (milik santri, lulus). */
    public function tasmiSertifikat(Request $request, $id): JsonResponse
    {
        $t = TugasTasmi::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name', 'pengampu.user:id,name'])->findOrFail($id);
        if ($t->santri_id !== $request->user()->id || $t->status !== 'selesai' || !$t->lulus) {
            return response()->json(['success' => false, 'message' => 'Sertifikat tidak tersedia.'], 422);
        }
        $nilai = (float) $t->nilai;
        return response()->json(['success' => true, 'data' => [
            'nomor' => sprintf('%03d/TASMI-%d/AN-NUR/%s', $t->id, $t->juz, optional($t->updated_at ?? now())->format('Y')),
            'santri' => ['nama' => $t->santri?->nama_lengkap, 'nip' => $t->santri?->nip],
            'juz' => $t->juz, 'nilai' => $nilai, 'predikat' => $this->predikat($nilai),
            'rubrik' => [['label' => 'Kelancaran', 'nilai' => $t->nilai_kelancaran], ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf], ['label' => 'Tajwid', 'nilai' => $t->nilai_tajwid], ['label' => 'Fashohah', 'nilai' => $t->nilai_fashohah]],
            'rubrik_ada' => $t->nilai_kelancaran !== null,
            'penguji' => $t->penguji?->user?->name ?? '—', 'pengampu' => $t->pengampu?->user?->name ?? '—', 'catatan' => $t->catatan,
            'tanggal' => optional($t->updated_at ?? now())->locale('id')->isoFormat('D MMMM YYYY'),
            'lembaga' => 'Pondok Pesantren An Nur Sidoarjo', 'program' => "Tahfizhul Qur'an",
            'alamat' => 'Jalan KH Ahmad Dahlan, Penatarsewu, Tanggulangin, Sidoarjo, Jawa Timur',
        ]]);
    }

    /** GET /api/santri/tahsin/tasnif/{id}/sertifikat */
    public function tasnifSertifikat(Request $request, $id): JsonResponse
    {
        $t = TugasTasnif::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name', 'pengampu.user:id,name'])->findOrFail($id);
        if ($t->santri_id !== $request->user()->id || $t->status !== 'selesai' || !$t->lulus) {
            return response()->json(['success' => false, 'message' => 'Sertifikat tidak tersedia.'], 422);
        }
        $nilai = (float) $t->nilai;
        return response()->json(['success' => true, 'data' => [
            'nomor' => sprintf('%03d/TASNIF-L%d/AN-NUR/%s', $t->id, $t->level, optional($t->updated_at ?? now())->format('Y')),
            'santri' => ['nama' => $t->santri?->nama_lengkap, 'nip' => $t->santri?->nip],
            'level' => $t->level, 'level_label' => TahsinService::levelLabel($t->level), 'nilai' => $nilai, 'predikat' => $this->predikat($nilai),
            'rubrik' => [['label' => 'Pemahaman Materi', 'nilai' => $t->nilai_pemahaman_materi], ['label' => 'Kelancaran', 'nilai' => $t->nilai_kelancaran], ['label' => 'Fashohah', 'nilai' => $t->nilai_fashohah], ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf]],
            'penguji' => $t->penguji?->user?->name ?? '—', 'pengampu' => $t->pengampu?->user?->name ?? '—', 'catatan' => $t->catatan,
            'tanggal' => optional($t->updated_at ?? now())->locale('id')->isoFormat('D MMMM YYYY'),
            'lembaga' => 'Pondok Pesantren An Nur Sidoarjo', 'program' => "Tahsinul Qur'an",
            'alamat' => 'Jalan KH Ahmad Dahlan, Penatarsewu, Tanggulangin, Sidoarjo, Jawa Timur',
        ]]);
    }

    /** GET /api/santri/pengumuman — pamflet/pengumuman aktif (satu, untuk pop-up). */
    public function pengumuman(Request $request): JsonResponse
    {
        $p = \App\Models\Pengumuman::aktif()->latest('updated_at')->first();
        return response()->json(['success' => true, 'data' => $p ? [
            'id' => $p->id, 'judul' => $p->judul,
            'gambar_url' => $p->gambar ? url('storage/' . $p->gambar) : null,
            'link_url' => $p->link_url, 'versi' => $p->updated_at?->timestamp,
        ] : null]);
    }

    private function predikat(float $n): string
    {
        return $n >= 9.5 ? 'Mumtaz (Istimewa)' : ($n >= 9 ? 'Jayyid Jiddan (Sangat Baik)' : ($n >= 8 ? 'Jayyid (Baik)' : 'Maqbul'));
    }
}
