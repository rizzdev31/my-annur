<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\Santri;
use App\Models\Surah;
use App\Models\HafalanJuz;
use App\Models\HafalanSantri;
use App\Models\SetoranTahfidz;
use App\Models\SettingTahsinMateri;
use App\Models\TahsinPenilaian;
use App\Services\TahfidzService;
use App\Services\TahsinService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LaporanApiController extends Controller
{
    private const ALAMAT = 'Jalan KH Ahmad Dahlan, Sangangewu, Penatarsewu, '
        . 'Kec. Tanggulangin, Kabupaten Sidoarjo, Jawa Timur 61272';

    /**
     * GET /education/laporan/pembelajaran
     * Laporan jurnal pembelajaran per kelas (kelas yang diajar guru ybs).
     */
    public function pembelajaran(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        // Kelas yang diajar guru ini (punya jadwal dengan kelas_id)
        $kelasIds = JadwalMengajar::where('tenaga_pendidik_id', $tp->id)
            ->whereNotNull('kelas_id')->distinct()->pluck('kelas_id');
        $kelasOpsi = Kelas::whereIn('id', $kelasIds)->orderBy('nama')->get(['id', 'nama']);

        $dari    = $request->filled('dari')   ? Carbon::parse($request->dari)   : Carbon::today()->startOfMonth();
        $sampai  = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::today();
        $kelasId = $request->kelas_id ? (int) $request->kelas_id : null;

        // Pastikan kelas yang diminta memang diajar guru ini
        $kelas = ($kelasId && $kelasIds->contains($kelasId)) ? Kelas::find($kelasId) : null;
        $rows  = collect();

        if ($kelas) {
            $rows = AbsensiMengajar::query()
                ->whereHas('jadwalMengajar', fn($j) => $j->where('kelas_id', $kelas->id))
                ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
                ->with([
                    'jadwalMengajar.mataPelajaran',
                    'tenagaPendidik.user',
                    'absensiSantri.santri:id,nama_lengkap',
                ])
                ->orderBy('tanggal')->orderBy('jam_mulai_aktual')
                ->get()->values()
                ->map(function ($a, $i) {
                    $byStatus = $a->absensiSantri->groupBy('status');
                    $namaBy = fn($st) => $byStatus->get($st)?->map(fn($x) => $x->santri?->nama_lengkap)
                        ->filter()->values()->all() ?? [];
                    return [
                        'no'        => $i + 1,
                        'tanggal'   => Carbon::parse($a->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                        'guru'      => $a->tenagaPendidik?->user?->name ?? '—',
                        'nip'       => $a->tenagaPendidik?->nip ?? '—',
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
                            // Jurnal absensi per santri (otomatis dari AbsensiSantri) — siapa masuk & tidak.
                            'santri'     => $a->absensiSantri
                                ->sortBy(fn($x) => $x->santri?->nama_lengkap)
                                ->map(fn($x) => [
                                    'nama'   => $x->santri?->nama_lengkap ?? '—',
                                    'status' => $x->status,
                                ])->values()->all(),
                        ],
                    ];
                });
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'kelas_opsi'    => $kelasOpsi,
                'kelas'         => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
                'periode_label' => $dari->locale('id')->isoFormat('D MMM YYYY')
                    . ' – ' . $sampai->locale('id')->isoFormat('D MMM YYYY'),
                'tanggal_cetak' => Carbon::today()->locale('id')->isoFormat('D MMMM YYYY'),
                'rows'          => $rows->values(),
                'kop_opsi'      => [
                    ['key' => 'smp', 'nama' => 'SMP Muhammadiyah 9 Boarding School Tanggulangin', 'alamat' => self::ALAMAT],
                    ['key' => 'sma', 'nama' => 'SMA Entrepreneur Muhammadiyah An Nur Sidoarjo', 'alamat' => self::ALAMAT],
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LAPORAN PENCAPAIAN — TAHFIDZ (guru pengampu, per kelas / per anak)
    // ══════════════════════════════════════════════════════════════════════
    public function tahfidz(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);

        $kelasOpsi = $this->kelasGuru($tp->id, 'tahfidz');
        $kelasId   = $request->kelas_id ? (int) $request->kelas_id : null;
        $santriId  = $request->santri_id ? (int) $request->santri_id : null;

        // Hanya kelas yang diajar guru ini
        $kelas  = ($kelasId && $kelasOpsi->pluck('id')->contains($kelasId)) ? Kelas::find($kelasId) : null;
        $santri = $santriId ? Santri::find($santriId) : null;
        $totalQ = (int) Surah::sum('jumlah_ayat');

        $mode = ($santri && $kelas) ? 'anak' : ($kelas ? 'kelas' : null);
        $rows = collect(); $detail = null; $santriOpsi = collect();

        if ($kelas) {
            $list = Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelas->id))
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);
            $santriOpsi = $list->map(fn($s) => ['id' => $s->id, 'nama' => $s->nama_lengkap, 'nip' => $s->nip]);

            if ($mode === 'kelas') {
                $ids = $list->pluck('id');
                $hafalan = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
                $juz = HafalanJuz::whereIn('santri_id', $ids)->get()->groupBy('santri_id');
                $rataNilai = SetoranTahfidz::whereIn('santri_id', $ids)->whereNotNull('nilai')
                    ->selectRaw('santri_id, AVG(nilai) as r')->groupBy('santri_id')->pluck('r', 'santri_id');
                $catatanAkhir = SetoranTahfidz::whereIn('santri_id', $ids)->whereNotNull('catatan')
                    ->orderByDesc('tanggal')->orderByDesc('id')->get(['santri_id', 'catatan'])
                    ->groupBy('santri_id')->map(fn($g) => $g->first()->catatan);
                $rows = $list->values()->map(function ($s, $i) use ($hafalan, $juz, $totalQ, $rataNilai, $catatanAkhir) {
                    $h = $hafalan->get($s->id); $jz = $juz->get($s->id) ?? collect();
                    $total = $h?->total_ayat ?? 0;
                    return [
                        'no' => $i + 1, 'santri_id' => $s->id, 'nama' => $s->nama_lengkap, 'nip' => $s->nip,
                        'total_ayat' => $total, 'persen' => $totalQ > 0 ? round($total / $totalQ * 100, 1) : 0,
                        'juz_selesai' => $jz->whereIn('status', ['selesai', 'tasmi_lulus'])->count(),
                        'rata_nilai' => isset($rataNilai[$s->id]) ? round((float) $rataNilai[$s->id], 1) : null,
                        'catatan_terakhir' => $catatanAkhir[$s->id] ?? null,
                    ];
                });
            }
        }

        if ($mode === 'anak') {
            $status = (new TahfidzService())->statusSantri($santri->id);
            $namaSurah = Surah::pluck('nama', 'nomor');
            $juzMap = HafalanJuz::where('santri_id', $santri->id)->get()->keyBy('juz');
            $juzGrid = collect(range(1, 30))->map(fn($n) => ['juz' => $n, 'status' => $juzMap->get($n)?->status ?? 'belum']);
            $setoran = SetoranTahfidz::where('santri_id', $santri->id)->with('tenagaPendidik.user:id,name')
                ->orderByDesc('tanggal')->orderByDesc('id')->get();

            $recap = collect(['ziyadah', 'murojaah_wajib', 'murojaah_tambahan', 'tasmi'])->map(function ($j) use ($setoran) {
                $grp = $setoran->where('jenis', $j); $bernilai = $grp->whereNotNull('nilai');
                return ['jenis' => $j, 'label' => $this->jenisLabel($j), 'count' => $grp->count(),
                    'ayat' => (int) $grp->sum('jumlah_ayat'), 'rata' => $bernilai->count() ? round($bernilai->avg('nilai'), 1) : null];
            })->values();

            $riwayat = $setoran->take(100)->map(fn($r) => [
                'tanggal' => Carbon::parse($r->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'jenis' => $r->jenis, 'label' => $this->jenisLabel($r->jenis),
                'rentang' => ($namaSurah[$r->surah_mulai] ?? '?') . ' ' . $r->ayat_mulai
                    . ' – ' . ($namaSurah[$r->surah_selesai] ?? '?') . ' ' . $r->ayat_selesai,
                'jumlah_ayat' => $r->jumlah_ayat, 'nilai' => $r->nilai, 'lulus' => $r->lulus,
                'catatan' => $r->catatan,
                'guru' => $r->tenagaPendidik?->user?->name ?? '—',
                'penguji' => $r->jenis === 'tasmi' ? ($r->tenagaPendidik?->user?->name ?? '—') : null,
            ])->values();

            // Tasmi' LULUS per juz + breakdown 4 rubrik (untuk laporan khusus tasmi' & sertifikat).
            $tasmiLulus = \App\Models\TugasTasmi::where('santri_id', $santri->id)
                ->where('status', 'selesai')->where('lulus', true)
                ->with(['penguji.user:id,name', 'pengampu.user:id,name'])
                ->orderBy('juz')->get()->map(fn($t) => [
                    'id'       => $t->id,
                    'juz'      => $t->juz,
                    'nilai'    => $t->nilai,
                    'rubrik'   => [
                        'kelancaran'       => $t->nilai_kelancaran,
                        'makhorijul_huruf' => $t->nilai_makhorijul_huruf,
                        'tajwid'           => $t->nilai_tajwid,
                        'fashohah'         => $t->nilai_fashohah,
                    ],
                    'rubrik_ada' => $t->nilai_kelancaran !== null,
                    'penguji'    => $t->penguji?->user?->name ?? '—',
                    'pengampu'   => $t->pengampu?->user?->name ?? '—',
                    'tanggal'    => optional($t->updated_at)->locale('id')->isoFormat('D MMM YYYY'),
                    'catatan'    => $t->catatan,
                ])->values();

            $detail = [
                'santri' => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'nip' => $santri->nip],
                'total_ayat' => $status['total_ayat'] ?? 0, 'persen' => $status['persen'] ?? 0,
                'juz_selesai' => $status['juz_selesai_total'] ?? 0,
                'ayat_baru' => (int) $setoran->where('jenis', 'ziyadah')->sum('jumlah_ayat'),
                'juz_grid' => $juzGrid, 'recap' => $recap, 'riwayat' => $riwayat,
                'tasmi_lulus' => $tasmiLulus,
            ];
        }

        return response()->json(['success' => true, 'data' => [
            'mode' => $mode,
            'kelas' => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
            'kelas_opsi' => $kelasOpsi, 'santri_opsi' => $santriOpsi->values(),
            'rows' => $rows->values(), 'detail' => $detail,
        ]]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LAPORAN PENCAPAIAN — TAHSIN (guru pengampu, per kelas / per anak)
    // ══════════════════════════════════════════════════════════════════════
    public function tahsin(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);

        $kelasOpsi = $this->kelasGuru($tp->id, 'tahsin');
        $kelasId   = $request->kelas_id ? (int) $request->kelas_id : null;
        $santriId  = $request->santri_id ? (int) $request->santri_id : null;

        $kelas  = ($kelasId && $kelasOpsi->pluck('id')->contains($kelasId)) ? Kelas::find($kelasId) : null;
        $santri = $santriId ? Santri::find($santriId) : null;

        $mode = ($santri && $kelas) ? 'anak' : ($kelas ? 'kelas' : null);
        $rows = collect(); $detail = null; $santriOpsi = collect();

        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');

        if ($kelas) {
            $list = Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelas->id))
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'tahsin_level']);
            $santriOpsi = $list->map(fn($s) => ['id' => $s->id, 'nama' => $s->nama_lengkap, 'nip' => $s->nip]);

            if ($mode === 'kelas') {
                $ids = $list->pluck('id');
                $lulus = TahsinPenilaian::whereIn('santri_id', $ids)->where('lulus', true)
                    ->selectRaw('santri_id, level, COUNT(*) as jml')->groupBy('santri_id', 'level')->get();
                $rataNilai = \App\Models\TahsinPenilaianRiwayat::whereIn('santri_id', $ids)->whereNotNull('nilai')
                    ->selectRaw('santri_id, AVG(nilai) as r')->groupBy('santri_id')->pluck('r', 'santri_id');
                $catatanAkhir = \App\Models\TahsinPenilaianRiwayat::whereIn('santri_id', $ids)->whereNotNull('catatan')
                    ->orderByDesc('tanggal')->orderByDesc('id')->get(['santri_id', 'catatan'])
                    ->groupBy('santri_id')->map(fn($g) => $g->first()->catatan);
                $rows = $list->values()->map(function ($s, $i) use ($materiTotal, $lulus, $rataNilai, $catatanAkhir) {
                    $lv = $s->tahsin_level ?? 1; $total = (int) ($materiTotal[$lv] ?? 0);
                    $sudah = (int) ($lulus->where('santri_id', $s->id)->where('level', $lv)->first()->jml ?? 0);
                    return ['no' => $i + 1, 'santri_id' => $s->id, 'nama' => $s->nama_lengkap, 'nip' => $s->nip,
                        'level' => $lv, 'materi_total' => $total, 'materi_lulus' => $sudah,
                        'level_selesai' => $total > 0 && $sudah >= $total,
                        'rata_nilai' => isset($rataNilai[$s->id]) ? round((float) $rataNilai[$s->id], 1) : null,
                        'catatan_terakhir' => $catatanAkhir[$s->id] ?? null];
                });
            }
        }

        if ($mode === 'anak') {
            $level = $santri->tahsin_level ?? 1;
            $lulusLv = TahsinPenilaian::where('santri_id', $santri->id)->where('lulus', true)
                ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');
            $statLv = \App\Models\TahsinPenilaianRiwayat::where('santri_id', $santri->id)
                ->selectRaw('level, COUNT(*) as c, AVG(nilai) as r')->groupBy('level')->get()->keyBy('level');
            $levelGrid = collect(range(1, \App\Services\TahsinService::LEVEL_MAX))->map(fn($lv) => [
                'level' => $lv, 'label' => \App\Services\TahsinService::levelLabel($lv),
                'total' => (int) ($materiTotal[$lv] ?? 0), 'lulus' => (int) ($lulusLv[$lv] ?? 0),
                'penilaian' => (int) ($statLv[$lv]->c ?? 0),
                'rata' => isset($statLv[$lv]->r) ? round((float) $statLv[$lv]->r, 1) : null,
                'status' => $lv < $level ? 'lewat' : ($lv === $level ? 'berjalan' : 'belum'),
            ]);
            // Riwayat = LOG penuh (setiap perubahan nilai tersimpan).
            $riwayat = \App\Models\TahsinPenilaianRiwayat::where('santri_id', $santri->id)
                ->with(['materi:id,nama', 'tenagaPendidik.user:id,name'])
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(120)->get()
                ->map(fn($p) => [
                    'tanggal' => Carbon::parse($p->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                    'level' => $p->level, 'materi' => $p->materi?->nama ?? '—', 'nilai' => $p->nilai, 'lulus' => $p->lulus,
                    'catatan' => $p->catatan,
                    'guru' => $p->tenagaPendidik?->user?->name ?? '—',
                ])->values();
            $nilaiAda = $riwayat->whereNotNull('nilai');
            // Tasnif LULUS per level + breakdown 4 rubrik (untuk laporan & sertifikat kenaikan).
            $tasnifLulus = \App\Models\TugasTasnif::where('santri_id', $santri->id)
                ->where('status', 'selesai')->where('lulus', true)
                ->with(['penguji.user:id,name'])
                ->orderBy('level')->get()->map(fn($t) => [
                    'id'          => $t->id,
                    'level'       => $t->level,
                    'level_label' => \App\Services\TahsinService::levelLabel($t->level),
                    'nilai'       => $t->nilai,
                    'rubrik'      => [
                        'pemahaman_materi' => $t->nilai_pemahaman_materi,
                        'kelancaran'       => $t->nilai_kelancaran,
                        'fashohah'         => $t->nilai_fashohah,
                        'makhorijul_huruf' => $t->nilai_makhorijul_huruf,
                    ],
                    'penguji'  => $t->penguji?->user?->name ?? '—',
                    'tanggal'  => optional($t->updated_at)->locale('id')->isoFormat('D MMM YYYY'),
                ])->values();
            $detail = [
                'santri' => ['nama' => $santri->nama_lengkap, 'nip' => $santri->nip, 'level' => $level],
                'materi' => collect((new TahsinService())->materiSantri($santri->id, $level))->values(),
                'level_grid' => $levelGrid, 'riwayat' => $riwayat,
                'rekap' => ['penilaian' => $riwayat->count(), 'lulus' => $riwayat->where('lulus', true)->count(),
                    'rata' => $nilaiAda->count() ? round($nilaiAda->avg('nilai'), 1) : null],
                'tasnif_lulus' => $tasnifLulus,
            ];
        }

        return response()->json(['success' => true, 'data' => [
            'mode' => $mode,
            'kelas' => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama, 'level' => $kelas->level_tahsin] : null,
            'kelas_opsi' => $kelasOpsi, 'santri_opsi' => $santriOpsi->values(),
            'rows' => $rows->values(), 'detail' => $detail,
        ]]);
    }

    /** Kelas (tahfidz|tahsin) yang diajar guru ini. */
    private function kelasGuru(int $tpId, string $tipe)
    {
        $kelasIds = JadwalMengajar::where('tenaga_pendidik_id', $tpId)
            ->whereNotNull('kelas_id')
            ->whereHas('mataPelajaran', fn($q) => $q->where('tipe', $tipe))
            ->distinct()->pluck('kelas_id');
        return Kelas::whereIn('id', $kelasIds)->orderBy('nama')->get(['id', 'nama'])
            ->map(fn($k) => ['id' => $k->id, 'nama' => $k->nama])->values();
    }

    private function jenisLabel(string $j): string
    {
        return [
            'ziyadah' => 'Hafalan Baru', 'murojaah_wajib' => 'Murojaah Wajib',
            'murojaah_tambahan' => 'Murojaah Tambahan', 'tasmi' => "Tasmi'",
        ][$j] ?? ucfirst(str_replace('_', ' ', $j));
    }
}
