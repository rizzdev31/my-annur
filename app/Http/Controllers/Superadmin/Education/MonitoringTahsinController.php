<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Kelas;
use App\Models\SettingTahsinMateri;
use App\Models\TahsinPenilaian;
use App\Services\TahsinService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class MonitoringTahsinController extends Controller
{
    /** Daftar santri tahsin per kelas + ringkasan progres level/materi. */
    public function index(Request $request)
    {
        $kelasId = $request->kelas_id ? (int) $request->kelas_id : null;
        $kelas   = $kelasId ? Kelas::find($kelasId) : null;
        $rows    = collect();

        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');

        if ($kelas) {
            $santri = Santri::aktif()
                ->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelas->id))
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'tahsin_level']);

            $lulus = TahsinPenilaian::whereIn('santri_id', $santri->pluck('id'))->where('lulus', true)
                ->selectRaw('santri_id, level, COUNT(*) as jml')->groupBy('santri_id', 'level')->get();

            $rows = $santri->map(function ($s) use ($materiTotal, $lulus) {
                $lv    = $s->tahsin_level ?? 1;
                $total = (int) ($materiTotal[$lv] ?? 0);
                $sudah = (int) ($lulus->where('santri_id', $s->id)->where('level', $lv)->first()->jml ?? 0);
                return [
                    'id'            => $s->id,
                    'nip'           => $s->nip,
                    'nama'          => $s->nama_lengkap,
                    'level'         => $lv,
                    'materi_total'  => $total,
                    'materi_lulus'  => $sudah,
                    'level_selesai' => $total > 0 && $sudah >= $total,
                ];
            })->values();
        }

        return Inertia::render('Admin/SmartEducation/MonitoringTahsin/Index', [
            'kelas'     => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama, 'level' => $kelas->level_tahsin] : null,
            'kelasOpsi' => Kelas::aktif()->tahsin()->orderBy('nama')->get(['id', 'nama', 'level_tahsin']),
            'santri'    => $rows,
            'summary'   => [
                'jumlah_santri' => $rows->count(),
                'level_selesai' => $rows->where('level_selesai', true)->count(),
            ],
        ]);
    }

    /** Detail 1 santri: progres antar level + materi level kini + riwayat nilai. */
    public function detail(Request $request, Santri $santri)
    {
        $level = $santri->tahsin_level ?? 1;
        $svc   = new TahsinService();

        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');
        $lulus = TahsinPenilaian::where('santri_id', $santri->id)->where('lulus', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');

        $levelGrid = collect(range(1, TahsinService::LEVEL_MAX))->map(function ($lv) use ($materiTotal, $lulus, $level) {
            $total = (int) ($materiTotal[$lv] ?? 0);
            $sudah = (int) ($lulus[$lv] ?? 0);
            return [
                'level'      => $lv,
                'label'      => TahsinService::levelLabel($lv),
                'total'      => $total,
                'lulus'      => $sudah,
                'is_current' => $lv === $level,
                'status'     => $lv < $level ? 'lewat' : ($lv === $level
                    ? ($total > 0 && $sudah >= $total ? 'siap_naik' : 'berjalan') : 'belum'),
            ];
        });

        $riwayat = TahsinPenilaian::where('santri_id', $santri->id)
            ->with('materi:id,nama', 'tenagaPendidik.user')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($p) => [
                'tanggal' => Carbon::parse($p->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'level'   => $p->level,
                'materi'  => $p->materi?->nama ?? '—',
                'nilai'   => $p->nilai,
                'lulus'   => $p->lulus,
                'guru'    => $p->tenagaPendidik?->user?->name ?? '—',
                'catatan' => $p->catatan,
            ]);

        return Inertia::render('Admin/SmartEducation/MonitoringTahsin/Detail', [
            'santri'    => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'nip' => $santri->nip, 'level' => $level],
            'materiKini'=> $svc->materiSantri($santri->id, $level),
            'levelGrid' => $levelGrid,
            'riwayat'   => $riwayat,
        ]);
    }
}
