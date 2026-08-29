<?php

namespace App\Http\Controllers\Superadmin\SmartHabbit;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\ControllingPeriode;
use App\Models\ControllingKegiatan;
use App\Models\ControllingAbsensi;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Rekap Smart Controlling: per santri (hadir/telat/alpha + %) & per kegiatan (berjalan/tidak).
 */
class ControllingRekapController extends Controller
{
    public function index(Request $request)
    {
        $periodeAktif = ControllingPeriode::aktif();
        $periodeId    = $request->periode_id ? (int) $request->periode_id : $periodeAktif?->id;
        $dari   = $request->dari   ?: null;
        $sampai = $request->sampai ?: null;

        $base = fn() => ControllingAbsensi::where('periode_id', $periodeId)
            ->when($dari,   fn($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn($q) => $q->whereDate('tanggal', '<=', $sampai));

        // ── Rekap per santri ──────────────────────────────────────────────
        $perSantriRaw = $periodeId
            ? $base()->selectRaw('santri_id, status, COUNT(*) c')->groupBy('santri_id', 'status')->get()
            : collect();
        $byS = [];
        foreach ($perSantriRaw as $r) {
            $byS[$r->santri_id] ??= ['hadir' => 0, 'telat' => 0, 'alpha' => 0, 'izin' => 0];
            $byS[$r->santri_id][$r->status] = (int) $r->c;
        }
        $nama = Santri::whereIn('id', array_keys($byS))->pluck('nama_lengkap', 'id');
        $nip  = Santri::whereIn('id', array_keys($byS))->pluck('nip', 'id');
        $perSantri = collect($byS)->map(function ($v, $id) use ($nama, $nip) {
            // Izin dikecualikan dari penilaian kehadiran (bukan hadir, bukan alfa).
            $total = $v['hadir'] + $v['telat'] + $v['alpha'];
            return [
                'santri_id' => $id, 'nama' => $nama[$id] ?? '—', 'nip' => $nip[$id] ?? '',
                'hadir' => $v['hadir'], 'telat' => $v['telat'], 'alpha' => $v['alpha'],
                'izin' => $v['izin'], 'total' => $total,
                'pct_hadir' => $total > 0 ? round($v['hadir'] / $total * 100, 1) : 0,
            ];
        })->sortBy('nama')->values();

        // ── Rekap per kegiatan (berjalan = ada hadir/telat pada tanggal itu) ─
        $perKegRaw = $periodeId
            ? $base()->selectRaw('kegiatan_id, status, COUNT(*) c')->groupBy('kegiatan_id', 'status')->get()
            : collect();
        $hariBerjalan = $periodeId
            ? $base()->whereIn('status', ['hadir', 'telat'])
                ->selectRaw('kegiatan_id, COUNT(DISTINCT tanggal) h')->groupBy('kegiatan_id')->pluck('h', 'kegiatan_id')
            : collect();
        $hariTercatat = $periodeId
            ? $base()->selectRaw('kegiatan_id, COUNT(DISTINCT tanggal) h')->groupBy('kegiatan_id')->pluck('h', 'kegiatan_id')
            : collect();
        $byK = [];
        foreach ($perKegRaw as $r) {
            $byK[$r->kegiatan_id] ??= ['hadir' => 0, 'telat' => 0, 'alpha' => 0, 'izin' => 0];
            $byK[$r->kegiatan_id][$r->status] = (int) $r->c;
        }
        $kegNama = ControllingKegiatan::whereIn('id', array_keys($byK))->get(['id', 'nama', 'jenis'])->keyBy('id');
        $perKegiatan = collect($byK)->map(fn($v, $id) => [
            'kegiatan_id'   => $id,
            'nama'          => $kegNama[$id]->nama ?? '—',
            'jenis'         => $kegNama[$id]->jenis ?? '-',
            'hadir' => $v['hadir'], 'telat' => $v['telat'], 'alpha' => $v['alpha'], 'izin' => $v['izin'],
            'hari_berjalan' => (int) ($hariBerjalan[$id] ?? 0),
            'hari_tercatat' => (int) ($hariTercatat[$id] ?? 0),
        ])->sortBy('nama')->values();

        // ── Drill-down: riwayat harian satu santri (bila difilter) ──────────
        $santriId = $request->santri_id ? (int) $request->santri_id : null;
        $detailSantri = ($periodeId && $santriId)
            ? $base()->where('santri_id', $santriId)->with('kegiatan:id,nama,jenis')
                ->orderByDesc('tanggal')->get()
                ->map(fn($a) => [
                    'tanggal'  => $a->tanggal?->toDateString(),
                    'kegiatan' => $a->kegiatan?->nama ?? '—',
                    'jenis'    => $a->kegiatan?->jenis,
                    'status'   => $a->status,
                    'jam_scan' => $a->jam_scan ? substr($a->jam_scan, 0, 5) : null,
                    'terkirim' => (bool) $a->dikirim_outbox,
                ])->values()
            : collect();

        return Inertia::render('Admin/SmartHabbit/Controlling/Rekap', [
            'periodeList'  => ControllingPeriode::orderByDesc('tahun')->orderByDesc('bulan')->get(['id', 'nama', 'is_aktif']),
            'periodeId'    => $periodeId,
            'filter'       => ['periode_id' => $periodeId, 'dari' => $dari, 'sampai' => $sampai, 'santri_id' => $santriId],
            'perSantri'    => $perSantri,
            'perKegiatan'  => $perKegiatan,
            'detailSantri' => $detailSantri,
            'ringkasan'    => [
                'santri' => $perSantri->count(),
                'hadir'  => $perSantri->sum('hadir'),
                'telat'  => $perSantri->sum('telat'),
                'alpha'  => $perSantri->sum('alpha'),
                'izin'   => $perSantri->sum('izin'),
            ],
        ]);
    }

    /**
     * Detail harian per sesi: siapa saja Hadir / Telat / Alpha pada satu tanggal
     * (opsional difilter per kegiatan), lengkap dgn jam scan, petugas, & status kirim RamahAnak.
     */
    public function harian(Request $request)
    {
        $tanggal    = $request->tanggal ?: now()->toDateString();
        $kegiatanId = $request->kegiatan_id ? (int) $request->kegiatan_id : null;

        $rows = ControllingAbsensi::with(['santri:id,nip,nama_lengkap', 'kegiatan:id,nama,jenis'])
            ->whereDate('tanggal', $tanggal)
            ->when($kegiatanId, fn($q) => $q->where('kegiatan_id', $kegiatanId))
            ->get()
            ->map(fn($a) => [
                'santri_id' => $a->santri_id,
                'nama'      => $a->santri?->nama_lengkap ?? '—',
                'nip'       => $a->santri?->nip ?? '',
                'kegiatan'  => $a->kegiatan?->nama ?? '—',
                'status'    => $a->status,
                'jam_scan'  => $a->jam_scan ? substr($a->jam_scan, 0, 5) : null,
                'petugas'   => $a->petugas,
                'terkirim'  => (bool) $a->dikirim_outbox,
            ])
            ->sortBy(fn($r) => [['hadir' => 0, 'telat' => 1, 'alpha' => 2, 'izin' => 3][$r['status']] ?? 9, $r['nama']])
            ->values();

        return Inertia::render('Admin/SmartHabbit/Controlling/Harian', [
            'tanggal'      => $tanggal,
            'kegiatanId'   => $kegiatanId,
            'kegiatanOpsi' => ControllingKegiatan::where('is_aktif', true)->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'rows'         => $rows,
            'ringkasan'    => [
                'hadir' => $rows->where('status', 'hadir')->count(),
                'telat' => $rows->where('status', 'telat')->count(),
                'alpha' => $rows->where('status', 'alpha')->count(),
                'izin'  => $rows->where('status', 'izin')->count(),
                'total' => $rows->count(),
            ],
        ]);
    }
}
