<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Services\PengawasService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MONITORING PIMPINAN (PWA) — pengawas memantau guru yang menjadi tanggung
 * jawabnya tanpa akun admin kedua.
 *
 * KEAMANAN: setiap endpoint WAJIB (1) cek modul lewat PengawasService::boleh(),
 * (2) membatasi data hanya pada idGuruDiawasi(). ID guru dari klien tidak dipercaya.
 */
class MonitoringApiController extends Controller
{
    public function __construct(private PengawasService $pengawas) {}

    /** GET /monitoring/status — hak monitoring saya (untuk menampilkan menu). */
    public function status(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        return response()->json(['success' => true, 'data' => $this->pengawas->ringkasan($tp->id)]);
    }

    /** GET /monitoring/absen-harian?tanggal= — status absen guru yang diawasi. */
    public function absenHarian(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'absen_harian')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring absensi.'], 403);
        }

        $tanggal = $request->filled('tanggal')
            ? \Carbon\Carbon::parse($request->tanggal)->toDateString()
            : TimezoneHelper::today()->toDateString();

        $guru = $this->pengawas->guruDiawasi($tp->id);
        if ($guru->isEmpty()) {
            return response()->json(['success' => true, 'data' => [
                'tanggal' => $tanggal, 'ringkasan' => [], 'guru' => [],
            ]]);
        }

        $absen = AbsensiHarian::whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->get()->keyBy('tenaga_pendidik_id');

        $rows = $guru->map(function ($g) use ($absen) {
            $a = $absen->get($g->id);
            return [
                'tenaga_pendidik_id' => $g->id,
                'nama'            => $g->user?->name ?? '—',
                'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                'status'          => $a?->status ?? 'belum',
                'jam_masuk'       => $a?->jam_masuk ? substr((string) $a->jam_masuk, 0, 5) : null,
                'jam_pulang'      => $a?->jam_pulang ? substr((string) $a->jam_pulang, 0, 5) : null,
                'menit_terlambat' => (int) ($a?->menit_terlambat ?? 0),
                'keterangan'      => $a?->keterangan,
            ];
        })->values();

        $ringkas = [];
        foreach ($rows as $r) {
            $ringkas[$r['status']] = ($ringkas[$r['status']] ?? 0) + 1;
        }

        return response()->json(['success' => true, 'data' => [
            'tanggal'   => $tanggal,
            'total'     => $rows->count(),
            'ringkasan' => $ringkas,
            'guru'      => $rows,
        ]]);
    }

    /**
     * GET /monitoring/absen-mengajar?tanggal= — sesi mengajar guru yang diawasi.
     * READ-ONLY: sengaja TIDAK memakai jadwalMengajarHariIni (yang punya efek
     * samping auto-mark), agar monitoring tak pernah mengubah data guru lain.
     */
    public function absenMengajar(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'absen_mengajar')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring pembelajaran.'], 403);
        }

        $tanggal = $request->filled('tanggal')
            ? \Carbon\Carbon::parse($request->tanggal)
            : TimezoneHelper::today();
        $tglStr   = $tanggal->toDateString();
        $namaHari = TimezoneHelper::namaHariDB($tanggal);
        $sekarang = TimezoneHelper::now();

        $guru = $this->pengawas->guruDiawasi($tp->id);
        if ($guru->isEmpty()) {
            return response()->json(['success' => true, 'data' => ['tanggal' => $tglStr, 'ringkasan' => [], 'guru' => []]]);
        }
        $guruIds = $guru->pluck('id');

        $jadwal = \App\Models\JadwalMengajar::with('mataPelajaran:id,nama')
            ->whereIn('tenaga_pendidik_id', $guruIds)
            ->where('hari', $namaHari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderBy('jam_mulai')->get();

        $absensi = \App\Models\AbsensiMengajar::with('digantikanOleh.user:id,name')
            ->whereDate('tanggal', $tglStr)
            ->whereIn('jadwal_mengajar_id', $jadwal->pluck('id'))
            ->get()->keyBy('jadwal_mengajar_id');

        $ringkas = [];
        $rows = $guru->map(function ($g) use ($jadwal, $absensi, $tanggal, $sekarang, &$ringkas) {
            $sesi = $jadwal->where('tenaga_pendidik_id', $g->id)->map(function ($j) use ($absensi, $tanggal, $sekarang, &$ringkas) {
                $a = $absensi->get($j->id);
                $lewat = $sekarang->gt(\Carbon\Carbon::parse(
                    $tanggal->toDateString() . ' ' . $j->jam_selesai, TimezoneHelper::TZ
                ));
                // Belum ada catatan & jam sudah lewat → TERLEWAT (sinyal utama pimpinan).
                $status = $a?->status ?? ($lewat ? 'terlewat' : 'belum');
                $ringkas[$status] = ($ringkas[$status] ?? 0) + 1;

                return [
                    'jadwal_id'      => $j->id,
                    'mata_pelajaran' => $j->mataPelajaran?->nama ?? '—',
                    'kelas'          => $j->kelas,
                    'jam'            => substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5),
                    'jumlah_jp'      => $j->jumlah_jp,
                    'status'         => $status,
                    'jp_terlaksana'  => $a?->jp_terlaksana,
                    'pengganti'      => $a?->digantikanOleh?->user?->name,
                    'materi'         => $a?->materi,
                ];
            })->values();

            return [
                'tenaga_pendidik_id' => $g->id,
                'nama'      => $g->user?->name ?? '—',
                'jabatan'   => $g->jabatan?->nama_jabatan ?? '—',
                'total'     => $sesi->count(),
                'beres'     => $sesi->whereIn('status', ['terlaksana', 'hadir', 'pengganti', 'libur'])->count(),
                'bermasalah'=> $sesi->whereIn('status', ['terlewat', 'tidak_terlaksana'])->count(),
                'sesi'      => $sesi,
            ];
        })->filter(fn($r) => $r['total'] > 0)->values();

        return response()->json(['success' => true, 'data' => [
            'tanggal'   => $tglStr,
            'ringkasan' => $ringkas,
            'guru'      => $rows,
        ]]);
    }

    /** GET /monitoring/perizinan?status= — pengajuan izin guru yang diawasi (lihat). */
    public function perizinan(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'perizinan')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring perizinan.'], 403);
        }

        $ids = $this->pengawas->idGuruDiawasi($tp->id);
        if (empty($ids)) {
            return response()->json(['success' => true, 'data' => ['ringkasan' => [], 'izin' => []]]);
        }

        $status = $request->get('status'); // pending|disetujui|ditolak|null(semua)
        $q = \App\Models\PengajuanIzin::with(['tenagaPendidik.user:id,name', 'jenisPengajuan:id,nama,kategori'])
            ->whereIn('tenaga_pendidik_id', $ids);
        if (in_array($status, ['pending', 'disetujui', 'ditolak'], true)) {
            $q->where('status', $status);
        }

        $izin = $q->orderByRaw("FIELD(status,'pending','disetujui','ditolak')")
            ->orderByDesc('tanggal_mulai')->limit(100)->get()
            ->map(fn($i) => [
                'id'              => $i->id,
                'guru'            => $i->tenagaPendidik?->user?->name ?? '—',
                'jenis'           => $i->jenisPengajuan?->nama ?? 'Izin',
                'kategori'        => $i->jenisPengajuan?->kategori,
                'tanggal_mulai'   => $i->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $i->tanggal_selesai?->toDateString(),
                'jumlah_hari'     => $i->jumlah_hari,
                'alasan'          => $i->alasan,
                'status'          => $i->status,
                'catatan_admin'   => $i->catatan_admin,
                'sementara'       => (bool) $i->is_sementara,
                'datang_terlambat'=> (bool) $i->is_datang_terlambat,
            ])->values();

        $ringkas = [];
        foreach (\App\Models\PengajuanIzin::whereIn('tenaga_pendidik_id', $ids)
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->get() as $r) {
            $ringkas[$r->status] = (int) $r->c;
        }

        return response()->json(['success' => true, 'data' => [
            'ringkasan'          => $ringkas,
            'boleh_setujui_izin' => $this->pengawas->bolehSetujuiIzin($tp->id), // Fase 3
            'izin'               => $izin,
        ]]);
    }
}
