<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Kelas;
use App\Models\Surah;
use App\Models\HafalanSantri;
use App\Models\HafalanJuz;
use App\Models\SetoranTahfidz;
use App\Services\TahfidzService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class MonitoringTahfidzController extends Controller
{
    /** Daftar santri tahfidz per kelas + ringkasan progres. */
    public function index(Request $request)
    {
        $kelasId   = $request->kelas_id ? (int) $request->kelas_id : null;
        $kelas     = $kelasId ? Kelas::find($kelasId) : null;
        $totalQ    = (int) Surah::sum('jumlah_ayat');
        $santriRow = collect();

        if ($kelas) {
            $santri = Santri::aktif()
                ->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelas->id))
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);

            $ids     = $santri->pluck('id');
            $hafalan = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
            $juz     = HafalanJuz::whereIn('santri_id', $ids)->get()->groupBy('santri_id');
            $last    = SetoranTahfidz::whereIn('santri_id', $ids)
                ->selectRaw('santri_id, MAX(tanggal) as last_tgl')->groupBy('santri_id')
                ->pluck('last_tgl', 'santri_id');

            $santriRow = $santri->map(function ($s) use ($hafalan, $juz, $last, $totalQ) {
                $h  = $hafalan->get($s->id);
                $jz = $juz->get($s->id) ?? collect();
                $total = $h?->total_ayat ?? 0;
                return [
                    'id'             => $s->id,
                    'nip'            => $s->nip,
                    'nama'           => $s->nama_lengkap,
                    'total_ayat'     => $total,
                    'persen'         => $totalQ > 0 ? round($total / $totalQ * 100, 2) : 0,
                    'juz_selesai'    => $jz->whereIn('status', ['selesai', 'tasmi_lulus'])->count(),
                    'perlu_tasmi'    => $jz->where('status', 'selesai')->pluck('juz')->values(),
                    'perlu_murojaah' => (bool) ($h?->perlu_murojaah ?? false),
                    'last_setoran'   => $last->get($s->id)
                        ? Carbon::parse($last->get($s->id))->locale('id')->isoFormat('D MMM YYYY') : null,
                ];
            })->values();
        }

        return Inertia::render('Admin/SmartEducation/MonitoringTahfidz/Index', [
            'kelas'     => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
            'kelasOpsi' => Kelas::aktif()->tahfidz()->orderBy('nama')->get(['id', 'nama']),
            'santri'    => $santriRow,
            'summary'   => [
                'jumlah_santri' => $santriRow->count(),
                'total_juz_selesai' => $santriRow->sum('juz_selesai'),
                'perlu_tasmi'   => $santriRow->filter(fn($s) => count($s['perlu_tasmi']) > 0)->count(),
            ],
        ]);
    }

    /** Detail tracking 1 santri: ringkasan + grid 30 juz + riwayat setoran. */
    public function detail(Request $request, Santri $santri)
    {
        $status   = (new TahfidzService())->statusSantri($santri->id);
        $namaSurah = Surah::pluck('nama', 'nomor');

        // Grid 30 juz: status dari hafalan_juz, sisanya 'belum'
        $juzMap = HafalanJuz::where('santri_id', $santri->id)->get()->keyBy('juz');
        $juzGrid = collect(range(1, 30))->map(function ($n) use ($juzMap) {
            $hj = $juzMap->get($n);
            return [
                'juz'    => $n,
                'status' => $hj?->status ?? 'belum',
                'ayat'   => $hj?->ayat_terkumpul ?? 0,
                'total'  => $hj?->jumlah_ayat_juz ?? 0,
            ];
        });

        $riwayat = SetoranTahfidz::where('santri_id', $santri->id)
            ->with('tenagaPendidik.user')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'id'        => $r->id,
                'tanggal'   => Carbon::parse($r->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'jenis'     => $r->jenis,
                'rentang'   => ($namaSurah[$r->surah_mulai] ?? '?') . ' ' . $r->ayat_mulai
                    . ' – ' . ($namaSurah[$r->surah_selesai] ?? '?') . ' ' . $r->ayat_selesai,
                'jumlah_ayat' => $r->jumlah_ayat,
                'juz'       => $r->juz_mulai == $r->juz_selesai ? $r->juz_mulai : "{$r->juz_mulai}–{$r->juz_selesai}",
                'nilai'     => $r->nilai,
                'lulus'     => $r->lulus,
                'guru'      => $r->tenagaPendidik?->user?->name ?? '—',
                'catatan'   => $r->catatan,
            ]);

        return Inertia::render('Admin/SmartEducation/MonitoringTahfidz/Detail', [
            'santri'  => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'nip' => $santri->nip,
                          'tahsin_level' => $santri->tahsin_level],
            'status'  => $status,
            'juzGrid' => $juzGrid,
            'riwayat' => $riwayat,
        ]);
    }
}
