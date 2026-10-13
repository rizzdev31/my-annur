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
                ->anggotaKelas($kelas->id)
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
            // Penunjukan penguji tasnif — sebelumnya tidak ada tempat untuk
            // melihat, apalagi membatalkan. Terukur 16 tugas menggantung.
            'tugasTasnif' => \App\Models\TugasTasnif::where('santri_id', $santri->id)
                ->with(['penguji.user:id,name', 'pengampu.user:id,name'])
                ->orderByDesc('id')->get()
                ->map(fn ($t) => [
                    'id'           => $t->id,
                    'level'        => $t->level,
                    'status'       => $t->status,
                    'penguji'      => $t->penguji?->user?->name ?? '—',
                    'pengampu'     => $t->pengampu?->user?->name ?? '—',
                    'nilai'        => $t->nilai,
                    'lulus'        => $t->lulus,
                    'alasan_batal' => $t->alasan_batal,
                    'dibuat'       => $t->created_at?->format('d M Y'),
                ]),
            'jejak' => \App\Models\KoreksiPencapaian::where('santri_id', $santri->id)
                ->where('tipe', 'tahsin')->with('dikoreksiOleh:id,name')
                ->orderByDesc('id')->limit(20)->get()
                ->map(fn ($k) => [
                    'level'  => $k->level,
                    'field'  => $k->field,
                    'lama'   => $k->nilai_lama,
                    'baru'   => $k->nilai_baru,
                    'alasan' => $k->alasan,
                    'oleh'   => $k->dikoreksiOleh?->name ?? '—',
                    'waktu'  => $k->created_at?->format('d M Y H:i'),
                ]),
        ]);
    }

    /**
     * Batalkan penunjukan penguji tasnif (termasuk tugas tambahan & vakasinya).
     *
     * Catatan: level santri TIDAK diturunkan otomatis — lihat alasannya di
     * TasnifService::batalkanTugas().
     */
    public function batalkanTasnif(Request $request, int $tugasTasnif)
    {
        $d = $request->validate(
            ['alasan' => 'required|string|max:300'],
            ['alasan.required' => 'Alasan pembatalan wajib diisi.']
        );

        try {
            app(\App\Services\TasnifService::class)
                ->batalkanTugas($tugasTasnif, \Illuminate\Support\Facades\Auth::id(), $d['alasan']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penunjukan tasnif dibatalkan. Vakasi pengujinya tidak akan terbayar.');
    }
}
