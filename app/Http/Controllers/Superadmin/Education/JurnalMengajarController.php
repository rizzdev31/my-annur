<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\Kelas;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class JurnalMengajarController extends Controller
{
    /**
     * Monitoring jurnal mengajar sekolah + rekap absensi santri.
     * Tahun ajaran diturunkan dari rantai jadwal → kelas (tidak disimpan ganda).
     */
    public function index(Request $request)
    {
        $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)   : Carbon::today();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::today();

        $sesi = AbsensiMengajar::query()
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->whereHas('jadwalMengajar', fn($j) => $j->whereNotNull('kelas_id'))
            ->when($request->kelas_id, fn($q) => $q->whereHas('jadwalMengajar',
                fn($j) => $j->where('kelas_id', $request->kelas_id)))
            ->when($request->guru_id, fn($q) => $q->where('tenaga_pendidik_id', $request->guru_id))
            ->with([
                'jadwalMengajar.mataPelajaran',
                'jadwalMengajar.kelasRel',
                'tenagaPendidik.user',
                'absensiSantri.santri:id,nip,nama_lengkap',
            ])
            ->orderByDesc('tanggal')->orderBy('jam_mulai_aktual')
            ->get()
            ->map(function ($a) {
                $byStatus = $a->absensiSantri->groupBy('status');
                return [
                    'id'                 => $a->id,
                    'tanggal'            => $a->tanggal?->toDateString(),
                    'guru'               => $a->tenagaPendidik?->user?->name ?? '—',
                    'mapel'              => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                    'kelas'              => $a->jadwalMengajar?->kelasRel?->nama
                                            ?? $a->jadwalMengajar?->kelas ?? '—',
                    'materi'             => $a->materi,
                    'jp'                 => $a->jp_terlaksana,
                    'status_sesi'        => $a->status,
                    'foto_url'           => $a->foto_mengajar ? asset('storage/'.$a->foto_mengajar) : null,
                    'hadir'              => $byStatus->get('hadir')?->count() ?? 0,
                    'telat'              => $byStatus->get('telat')?->count() ?? 0,
                    'alpha'              => $byStatus->get('alpha')?->count() ?? 0,
                    'total_santri'       => $a->absensiSantri->count(),
                    'sudah_absen_santri' => $a->absensiSantri->isNotEmpty(),
                    'santri'             => $a->absensiSantri->map(fn($s) => [
                        'nama'   => $s->santri?->nama_lengkap ?? '—',
                        'nip'    => $s->santri?->nip,
                        'status' => $s->status,
                    ])->values(),
                ];
            });

        return Inertia::render('Admin/SmartEducation/JurnalMengajar/Index', [
            'sesi'   => $sesi,
            'filter' => [
                'dari'     => $dari->toDateString(),
                'sampai'   => $sampai->toDateString(),
                'kelas_id' => $request->kelas_id ? (int) $request->kelas_id : null,
                'guru_id'  => $request->guru_id ? (int) $request->guru_id : null,
            ],
            'kelasOpsi' => Kelas::aktif()->sekolah()->orderBy('nama')->get(['id', 'nama']),
            'guruOpsi'  => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'summary' => [
                'total_sesi'  => $sesi->count(),
                'sudah_isi'   => $sesi->where('sudah_absen_santri', true)->count(),
                'total_hadir' => $sesi->sum('hadir'),
                'total_telat' => $sesi->sum('telat'),
                'total_alpha' => $sesi->sum('alpha'),
            ],
        ]);
    }
}
