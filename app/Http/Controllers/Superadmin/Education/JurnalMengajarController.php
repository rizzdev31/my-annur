<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\Kelas;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class JurnalMengajarController extends Controller
{
    /**
     * Monitoring jurnal mengajar sekolah + rekap absensi santri.
     * Tahun ajaran diturunkan dari rantai jadwal → kelas (tidak disimpan ganda).
     */

    /**
     * GET sesi/{absensi}/roster — seluruh anggota kelas + status tersimpannya,
     * untuk panel koreksi admin. Santri yang belum punya baris tetap tampil
     * supaya roster yang kosong bisa diisi dari sini.
     */
    public function rosterSesi(AbsensiMengajar $absensi): \Illuminate\Http\JsonResponse
    {
        $kelasId = $absensi->jadwalMengajar?->kelas_id;
        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Sesi ini belum punya kelas.'], 422);
        }

        $tersimpan = \App\Models\AbsensiSantri::where('absensi_mengajar_id', $absensi->id)
            ->with('dikoreksiOleh:id,name')->get()->keyBy('santri_id');

        $kh = app(\App\Services\KehadiranSantriService::class);
        $ids = \App\Models\Santri::aktif()->anggotaKelas((int) $kelasId)->pluck('id');
        $konteks = $kh->konteks($ids, $absensi->tanggal->toDateString());

        $santri = \App\Models\Santri::aktif()->anggotaKelas((int) $kelasId)
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap'])
            ->map(function ($s) use ($tersimpan, $kh, $konteks) {
                $row = $tersimpan->get($s->id);
                $baris = $kh->baris($s->id, $konteks, $row?->status);
                return [
                    'santri_id'      => $s->id,
                    'nip'            => $s->nip,
                    'nama'           => $s->nama_lengkap,
                    'status'         => $baris['status'],
                    'tersimpan'      => $row !== null,
                    'izin_disetujui' => $baris['izin_disetujui'],
                    'sakit_health'   => $baris['sakit_health'],
                    'dikoreksi_oleh' => $row?->dikoreksiOleh?->name,
                ];
            })->values();

        return response()->json(['success' => true, 'data' => [
            'absensi_id' => $absensi->id,
            'tanggal'    => $absensi->tanggal->toDateString(),
            'kelas'      => $absensi->jadwalMengajar?->kelasRel?->nama ?? '—',
            'mapel'      => $absensi->jadwalMengajar?->mataPelajaran?->nama ?? '—',
            'guru'       => $absensi->tenagaPendidik?->user?->name ?? '—',
            'status_sesi'=> $absensi->status,
            'santri'     => $santri,
        ]]);
    }

    /** POST sesi/{absensi}/koreksi — atur terlaksananya sesi (status/JP/jam/materi). */
    public function koreksiSesi(Request $request, AbsensiMengajar $absensi)
    {
        $d = $request->validate([
            'status'             => 'required|in:terlaksana,tidak_terlaksana,pengganti,libur,izin',
            'jp_terlaksana'      => 'nullable|integer|min:0|max:20',
            'jam_mulai_aktual'   => 'nullable|date_format:H:i,H:i:s',
            'jam_selesai_aktual' => 'nullable|date_format:H:i,H:i:s',
            'materi'             => 'nullable|string|max:500',
            'keterangan'         => 'nullable|string|max:500',
            'digantikan_oleh'    => 'nullable|exists:tenaga_pendidik,id',
            'alasan_koreksi'     => 'required|string|max:500',
        ]);

        try {
            app(\App\Services\KoreksiPembelajaranService::class)
                ->koreksiSesi($absensi, $d, Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sesi dikoreksi menjadi "' . $d['status'] . '". '
            . 'Kinerja guru ikut dihitung ulang pada penghitungan berikutnya.');
    }

    /** POST sesi/{absensi}/koreksi-roster — koreksi absensi santri oleh admin. */
    public function koreksiRoster(Request $request, AbsensiMengajar $absensi)
    {
        $d = $request->validate([
            'absensi'             => 'required|array|min:1',
            'absensi.*.santri_id' => 'required|integer|exists:santri,id',
            'absensi.*.status'    => 'required|in:hadir,telat,izin,sakit,alpha',
            'alasan'              => 'nullable|string|max:500',
            'kabari_wali'         => 'boolean',
        ]);

        $svc = app(\App\Services\KoreksiPembelajaranService::class);
        try {
            $h = $svc->tulisRoster($absensi, $d['absensi'], Auth::id(), true, $d['alasan'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (($d['kabari_wali'] ?? false) && $h['berubah']->isNotEmpty()) {
            $svc->kabariWali($absensi, $h['berubah']);
        }

        $n = $h['diubah'] + $h['ditambah'];

        return back()->with($n ? 'success' : 'info', $n
            ? "Absensi santri dikoreksi: {$h['diubah']} diubah, {$h['ditambah']} ditambahkan."
                . (($d['kabari_wali'] ?? false) ? ' Wali santri yang berubah dikabari.' : '')
            : 'Tidak ada perubahan absensi santri.');
    }

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
                'jadwalMengajar.ujianSesi.ujian:id,nama',
                'tenagaPendidik.user',
                'digantikanOleh.user:id,name',
                'absensiSantri.santri:id,nip,nama_lengkap',
            ])
            ->orderByDesc('tanggal')->orderBy('jam_mulai_aktual')
            ->get()
            ->map(function ($a) {
                $byStatus = $a->absensiSantri->groupBy('status');
                return [
                    'id'                 => $a->id,
                    'jadwal_id'          => $a->jadwal_mengajar_id,
                    'kelas_id'           => $a->jadwalMengajar?->kelas_id,
                    'tipe'               => $a->jadwalMengajar?->mataPelajaran?->tipe ?? 'reguler',
                    'jp_jadwal'          => (int) ($a->jadwalMengajar?->jumlah_jp ?? 0),
                    'jam'                => substr((string) $a->jadwalMengajar?->jam_mulai, 0, 5)
                                            . '–' . substr((string) $a->jadwalMengajar?->jam_selesai, 0, 5),
                    'pengganti_nama'     => $a->digantikanOleh?->user?->name,
                    'is_koreksi'         => (bool) $a->is_koreksi,
                    'tanggal'            => $a->tanggal?->toDateString(),
                    'guru'               => $a->tenagaPendidik?->user?->name ?? '—',
                    'mapel'              => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                    'kelas'              => $a->jadwalMengajar?->kelasRel?->nama
                                            ?? $a->jadwalMengajar?->kelas ?? '—',
                    'materi'             => $a->materi,
                    // Sesi ujian tercatat di jurnal kelas masing-masing, dengan
                    // penanda agar tidak terbaca sebagai pembelajaran biasa.
                    'is_ujian'           => $a->jadwalMengajar?->ujian_sesi_id !== null,
                    'ujian'              => $a->jadwalMengajar?->ujianSesi?->ujian?->nama,
                    'ruangan_ujian'      => $a->jadwalMengajar?->ujianSesi?->ruangan,
                    'jp'                 => $a->jp_terlaksana,
                    'status_sesi'        => $a->status,
                    'foto_url'           => $a->foto_mengajar ? asset('storage/'.$a->foto_mengajar) : null,
                    'hadir'              => $byStatus->get('hadir')?->count() ?? 0,
                    'telat'              => $byStatus->get('telat')?->count() ?? 0,
                    'izin'               => $byStatus->get('izin')?->count() ?? 0,
                    'sakit'              => $byStatus->get('sakit')?->count() ?? 0,
                    'alpha'              => $byStatus->get('alpha')?->count() ?? 0,
                    'total_santri'       => $a->absensiSantri->count(),
                    'sudah_absen_santri' => $a->absensiSantri->isNotEmpty(),
                    'santri'             => $a->absensiSantri->map(fn($s) => [
                        'santri_id' => $s->santri_id,
                        'nama'      => $s->santri?->nama_lengkap ?? '—',
                        'nip'       => $s->santri?->nip,
                        'status'    => $s->status,
                        'dikoreksi' => $s->dikoreksi_oleh !== null,
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
            // Sekolah + pesantren: keduanya dijurnal dengan cara yang sama.
            'kelasOpsi' => Kelas::aktif()->reguler()->orderBy('nama')->get(['id', 'nama']),
            'guruOpsi'  => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'summary' => [
                'total_sesi'  => $sesi->count(),
                'sudah_isi'   => $sesi->where('sudah_absen_santri', true)->count(),
                'total_hadir' => $sesi->sum('hadir'),
                'total_telat' => $sesi->sum('telat'),
                'total_izin'  => $sesi->sum('izin'),
                'total_sakit' => $sesi->sum('sakit'),
                'total_alpha' => $sesi->sum('alpha'),
            ],
        ]);
    }
}
