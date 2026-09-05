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

    /**
     * GET /monitoring/dashboard?tanggal= — RINGKASAN SATU LAYAR untuk pimpinan.
     * Mengambil semua modul yang diizinkan dalam satu panggilan agar pimpinan
     * langsung melihat kondisi hari ini tanpa berpindah-pindah tab.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        $p = $this->pengawas->untuk($tp->id);
        if (!$p) return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring.'], 403);

        $modul   = array_values((array) ($p->modul ?? []));
        $tanggal = $request->filled('tanggal') ? \Carbon\Carbon::parse($request->tanggal) : TimezoneHelper::today();
        $tglStr  = $tanggal->toDateString();
        $namaHari = TimezoneHelper::namaHariDB($tanggal);
        $sekarang = TimezoneHelper::now();

        $guru    = $this->pengawas->guruDiawasi($tp->id);
        $guruIds = $guru->pluck('id');

        $out = [
            'tanggal'    => $tglStr,
            'hari'       => ucfirst($tanggal->locale('id')->isoFormat('dddd, D MMMM YYYY')),
            'total_guru' => $guru->count(),
            'modul'      => $modul,
            'perhatian'  => [],   // ringkasan anomali paling penting
        ];
        if ($guru->isEmpty()) return response()->json(['success' => true, 'data' => $out]);

        // ── Kehadiran hari ini ────────────────────────────────────────────
        if (in_array('absen_harian', $modul, true)) {
            $absen = AbsensiHarian::whereDate('tanggal', $tglStr)
                ->whereIn('tenaga_pendidik_id', $guruIds)->get()->keyBy('tenaga_pendidik_id');

            $rows = $guru->map(function ($g) use ($absen) {
                $a = $absen->get($g->id);
                return [
                    'nama'            => $g->user?->name ?? '—',
                    'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                    'status'          => $a?->status ?? 'belum',
                    'jam_masuk'       => $a?->jam_masuk ? substr((string) $a->jam_masuk, 0, 5) : null,
                    'menit_terlambat' => (int) ($a?->menit_terlambat ?? 0),
                ];
            })->values();

            $hitung = [];
            foreach ($rows as $r) $hitung[$r['status']] = ($hitung[$r['status']] ?? 0) + 1;

            $out['absen'] = ['ringkasan' => $hitung, 'guru' => $rows];

            $belum = $rows->where('status', 'belum')->pluck('nama')->all();
            if ($belum) $out['perhatian'][] = ['tipe' => 'absen', 'jumlah' => count($belum),
                'teks' => count($belum) . ' guru belum absen', 'nama' => array_slice($belum, 0, 6)];
        }

        // ── Jadwal & absensi mengajar hari ini ────────────────────────────
        if (in_array('absen_mengajar', $modul, true)) {
            $jadwal = \App\Models\JadwalMengajar::with(['mataPelajaran:id,nama', 'tenagaPendidik.user:id,name'])
                ->whereIn('tenaga_pendidik_id', $guruIds)
                ->where('hari', $namaHari)->where('is_aktif', true)
                ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
                ->orderBy('jam_mulai')->get();

            $am = \App\Models\AbsensiMengajar::with('digantikanOleh.user:id,name')
                ->whereDate('tanggal', $tglStr)->whereIn('jadwal_mengajar_id', $jadwal->pluck('id'))
                ->get()->keyBy('jadwal_mengajar_id');

            $sesi = $jadwal->map(function ($j) use ($am, $tglStr, $sekarang) {
                $a = $am->get($j->id);
                $lewat = $sekarang->gt(\Carbon\Carbon::parse($tglStr . ' ' . $j->jam_selesai, TimezoneHelper::TZ));
                return [
                    'guru'           => $j->tenagaPendidik?->user?->name ?? '—',
                    'mata_pelajaran' => $j->mataPelajaran?->nama ?? '—',
                    'kelas'          => $j->kelas,
                    'jam'            => substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5),
                    'status'         => $a?->status ?? ($lewat ? 'terlewat' : 'belum'),
                    'pengganti'      => $a?->digantikanOleh?->user?->name,
                ];
            })->values();

            $bermasalah = $sesi->whereIn('status', ['terlewat', 'tidak_terlaksana']);
            $out['mengajar'] = [
                'total'      => $sesi->count(),
                'beres'      => $sesi->whereIn('status', ['terlaksana', 'hadir', 'pengganti', 'libur'])->count(),
                'bermasalah' => $bermasalah->count(),
                'sesi'       => $sesi,
            ];
            if ($bermasalah->count()) $out['perhatian'][] = ['tipe' => 'mengajar', 'jumlah' => $bermasalah->count(),
                'teks' => $bermasalah->count() . ' sesi mengajar belum tercatat',
                'nama' => $bermasalah->take(6)->map(fn($s) => $s['guru'] . ' · ' . $s['mata_pelajaran'])->values()->all()];
        }

        // ── Perizinan: yang sedang izin hari ini + menunggu keputusan ─────
        if (in_array('perizinan', $modul, true)) {
            $izinAktif = \App\Models\PengajuanIzin::with(['tenagaPendidik.user:id,name', 'jenisPengajuan:id,nama'])
                ->whereIn('tenaga_pendidik_id', $guruIds)->where('status', 'disetujui')
                ->where('tanggal_mulai', '<=', $tglStr)->where('tanggal_selesai', '>=', $tglStr)
                ->get()->map(fn($i) => [
                    'guru'  => $i->tenagaPendidik?->user?->name ?? '—',
                    'jenis' => $i->jenisPengajuan?->nama ?? 'Izin',
                    'sampai'=> $i->tanggal_selesai?->toDateString(),
                ])->values();

            $menunggu = \App\Models\PengajuanIzin::with(['tenagaPendidik.user:id,name', 'jenisPengajuan:id,nama'])
                ->whereIn('tenaga_pendidik_id', $guruIds)->where('status', 'pending')
                ->orderBy('tanggal_mulai')->get()->map(fn($i) => [
                    'id'    => $i->id,
                    'guru'  => $i->tenagaPendidik?->user?->name ?? '—',
                    'jenis' => $i->jenisPengajuan?->nama ?? 'Izin',
                    'mulai' => $i->tanggal_mulai?->toDateString(),
                    'selesai' => $i->tanggal_selesai?->toDateString(),
                    'alasan'  => $i->alasan,
                    'datang_terlambat' => (bool) $i->is_datang_terlambat,
                ])->values();

            $out['izin'] = [
                'aktif_hari_ini'     => $izinAktif,
                'menunggu'           => $menunggu,
                'boleh_setujui_izin' => $this->pengawas->bolehSetujuiIzin($tp->id),
            ];
            if ($menunggu->count()) $out['perhatian'][] = ['tipe' => 'izin', 'jumlah' => $menunggu->count(),
                'teks' => $menunggu->count() . ' izin menunggu keputusan',
                'nama' => $menunggu->take(6)->pluck('guru')->all()];
        }

        // ── Tugas tambahan yang masih berjalan ───────────────────────────
        if (in_array('tugas_tambahan', $modul, true)) {
            $qTugas = \App\Models\PenugasanTambahan::whereIn('tenaga_pendidik_id', $guruIds)
                ->whereIn('status_pengerjaan', ['belum', 'sedang']);

            // Hitung penuh dulu; daftar dibatasi agar payload dashboard tetap ringan.
            $jumlahTugas = (clone $qTugas)->count();

            $tugas = $qTugas->with(['tenagaPendidik.user:id,name', 'tugasTambahan:id,judul,tanggal_selesai'])
                ->latest('id')->limit(40)->get()
                ->map(fn($t) => [
                    'guru'    => $t->tenagaPendidik?->user?->name ?? '—',
                    'judul'   => $t->tugasTambahan?->judul ?? '—',
                    'selesai' => optional($t->tugasTambahan?->tanggal_selesai)->toDateString(),
                    'status'  => $t->status_pengerjaan,
                ])->values();

            $out['tugas'] = ['berjalan' => $jumlahTugas, 'daftar' => $tugas];
        }

        // ── Kinerja bulan berjalan: yang terendah lebih dulu ─────────────
        if (in_array('kinerja', $modul, true)) {
            $rekap = \App\Models\RekapKinerjaBulanan::with('tenagaPendidik.user:id,name')
                ->whereIn('tenaga_pendidik_id', $guruIds)
                ->where('bulan', (int) $tanggal->month)->where('tahun', (int) $tanggal->year)
                ->whereNotNull('skor_total')->orderBy('skor_total')->limit(10)->get()
                ->map(fn($r) => [
                    'guru' => $r->tenagaPendidik?->user?->name ?? '—',
                    'skor' => round((float) $r->skor_total, 1),
                ])->values();
            $out['kinerja'] = ['terendah' => $rekap];
        }

        return response()->json(['success' => true, 'data' => $out]);
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
            'boleh_setujui_izin' => $this->pengawas->bolehSetujuiIzin($tp->id),
            'izin'               => $izin,
        ]]);
    }

    /**
     * Penjagaan bersama untuk aksi persetujuan izin oleh pimpinan.
     * Mengembalikan pesan error (string) bila TIDAK boleh, atau null bila boleh.
     */
    private function tolakAksiIzin($tp, \App\Models\PengajuanIzin $izin): ?string
    {
        if (!$this->pengawas->bolehSetujuiIzin($tp->id)) {
            return 'Anda tidak diberi wewenang menyetujui izin.';
        }
        // bolehLihatGuru() sudah otomatis mengecualikan DIRI SENDIRI → cegah
        // konflik kepentingan (menyetujui izin sendiri) sekaligus lintas cakupan.
        if (!$this->pengawas->bolehLihatGuru($tp->id, (int) $izin->tenaga_pendidik_id)) {
            return 'Guru ini di luar cakupan pengawasan Anda.';
        }
        if ($izin->status !== 'pending') {
            return "Pengajuan ini sudah {$izin->status}.";
        }
        return null;
    }

    /** POST /monitoring/perizinan/{izin}/setujui — keputusan FINAL (tercatat). */
    public function setujuiIzin(Request $request, \App\Models\PengajuanIzin $izin): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        $request->validate([
            'catatan'   => 'nullable|string|max:500',
            'jam_mulai' => 'nullable|date_format:H:i,H:i:s', // khusus izin datang terlambat
        ]);

        if ($pesan = $this->tolakAksiIzin($tp, $izin)) {
            return response()->json(['success' => false, 'message' => $pesan], 403);
        }

        // Izin DATANG TERLAMBAT tak lewat alur absensi harian (sama seperti admin).
        if ($izin->is_datang_terlambat) {
            $upd = [
                'status'            => 'disetujui',
                'catatan_admin'     => $request->catatan,
                'diproses_oleh'     => $request->user()->id,
                'tanggal_keputusan' => now(),
            ];
            if ($request->filled('jam_mulai')) {
                $upd['jam_mulai'] = strlen($request->jam_mulai) === 5 ? $request->jam_mulai . ':00' : $request->jam_mulai;
            }
            $izin->update($upd);

            if ($izin->tenagaPendidik?->user) {
                \App\Services\NotifikasiService::kirim(
                    $izin->tenagaPendidik->user->id, 'Izin Datang Terlambat Disetujui',
                    'Kamu boleh datang s/d ' . substr((string) $izin->jam_mulai, 0, 5)
                        . ' pada ' . $izin->tanggal_mulai?->format('d/m/Y') . '. Dalam batas itu tetap dihitung hadir.',
                    'izin', ['type' => 'izin', 'route' => '/izin']
                );
            }
            return response()->json(['success' => true, 'message' => 'Izin datang terlambat disetujui.']);
        }

        try {
            // Reuse service admin → absensi & status kepegawaian ikut diproses,
            // audit (diproses_oleh + tanggal_keputusan) tercatat otomatis.
            app(\App\Services\PengajuanIzinService::class)->setujui($izin, $request->catatan);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Izin disetujui. Absensi guru diperbarui otomatis.']);
    }

    /** POST /monitoring/perizinan/{izin}/tolak — alasan WAJIB (tercatat). */
    public function tolakIzin(Request $request, \App\Models\PengajuanIzin $izin): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        $request->validate(['catatan' => 'required|string|min:3|max:500'],
            ['catatan.required' => 'Alasan penolakan wajib diisi.']);

        if ($pesan = $this->tolakAksiIzin($tp, $izin)) {
            return response()->json(['success' => false, 'message' => $pesan], 403);
        }

        try {
            app(\App\Services\PengajuanIzinService::class)->tolak($izin, $request->catatan);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Izin ditolak.']);
    }

    /** GET /monitoring/tugas-tambahan?status= — penugasan guru yang diawasi. */
    public function tugasTambahan(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'tugas_tambahan')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring tugas tambahan.'], 403);
        }

        $ids = $this->pengawas->idGuruDiawasi($tp->id);
        if (empty($ids)) return response()->json(['success' => true, 'data' => ['ringkasan' => [], 'tugas' => []]]);

        $status = $request->get('status'); // belum|sedang|selesai|null
        $q = \App\Models\PenugasanTambahan::with(['tenagaPendidik.user:id,name', 'tugasTambahan:id,judul,tanggal_mulai,tanggal_selesai,tipe'])
            ->whereIn('tenaga_pendidik_id', $ids);
        if (in_array($status, ['belum', 'sedang', 'selesai'], true)) {
            $q->where('status_pengerjaan', $status);
        }

        $rows = $q->latest('id')->limit(120)->get()->map(fn($p) => [
            'id'          => $p->id,
            'guru'        => $p->tenagaPendidik?->user?->name ?? '—',
            'judul'       => $p->tugasTambahan?->judul ?? '—',
            'tipe'        => $p->tugasTambahan?->tipe,
            'mulai'       => optional($p->tugasTambahan?->tanggal_mulai)->toDateString(),
            'selesai'     => optional($p->tugasTambahan?->tanggal_selesai)->toDateString(),
            'status'      => $p->status_pengerjaan,
            'disetujui'   => (bool) $p->disetujui,
            'dilaporkan'  => $p->dilaporkan_pada ? \Carbon\Carbon::parse($p->dilaporkan_pada)->toDateString() : null,
            'laporan'     => $p->laporan ?: $p->teks_bukti,
        ])->values();

        $ringkas = [];
        foreach (\App\Models\PenugasanTambahan::whereIn('tenaga_pendidik_id', $ids)
            ->selectRaw('status_pengerjaan s, COUNT(*) c')->groupBy('status_pengerjaan')->get() as $r) {
            $ringkas[$r->s] = (int) $r->c;
        }

        return response()->json(['success' => true, 'data' => ['ringkasan' => $ringkas, 'tugas' => $rows]]);
    }

    /**
     * GET /monitoring/kinerja?bulan=&tahun= — skor kinerja guru yang diawasi.
     * Kebijakan: RINGKAS + komponen pembentuk skor, TANPA angka rupiah/potongan.
     */
    public function kinerja(Request $request): JsonResponse
    {
        $tp = $request->user()?->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Bukan tenaga pendidik.'], 403);

        if (!$this->pengawas->boleh($tp->id, 'kinerja')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diberi akses monitoring kinerja.'], 403);
        }

        $bulan = (int) ($request->bulan ?: TimezoneHelper::now()->month);
        $tahun = (int) ($request->tahun ?: TimezoneHelper::now()->year);

        $guru = $this->pengawas->guruDiawasi($tp->id);
        if ($guru->isEmpty()) {
            return response()->json(['success' => true, 'data' => ['bulan' => $bulan, 'tahun' => $tahun, 'guru' => []]]);
        }

        $rekap = \App\Models\RekapKinerjaBulanan::whereIn('tenaga_pendidik_id', $guru->pluck('id'))
            ->where('bulan', $bulan)->where('tahun', $tahun)->get()->keyBy('tenaga_pendidik_id');

        $grade = function ($skor) {
            if ($skor === null) return null;
            if ($skor >= 90) return 'A';
            if ($skor >= 80) return 'B';
            if ($skor >= 70) return 'C';
            return 'D';
        };

        $rows = $guru->map(function ($g) use ($rekap, $grade) {
            $r = $rekap->get($g->id);
            $skor = $r?->skor_total !== null ? round((float) $r->skor_total, 1) : null;
            return [
                'tenaga_pendidik_id' => $g->id,
                'nama'    => $g->user?->name ?? '—',
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                'skor'    => $skor,
                'grade'   => $grade($skor),
                // Komponen pembentuk skor (tanpa nominal rupiah — sesuai kebijakan).
                'komponen' => $r ? [
                    'absensi'      => $r->skor_absensi !== null ? round((float) $r->skor_absensi, 1) : null,
                    'tugas'        => $r->skor_tugas !== null ? round((float) $r->skor_tugas, 1) : null,
                    'administrasi' => $r->skor_administrasi !== null ? round((float) $r->skor_administrasi, 1) : null,
                    'piket'        => $r->skor_piket !== null ? round((float) $r->skor_piket, 1) : null,
                ] : null,
                'absensi' => $r ? [
                    'hadir' => (int) $r->total_hadir, 'terlambat' => (int) $r->total_terlambat,
                    'izin' => (int) $r->total_izin, 'sakit' => (int) $r->total_sakit,
                    'alfa' => (int) $r->total_alfa, 'hari_kerja' => (int) $r->total_hari_kerja,
                ] : null,
                'mengajar' => $r ? [
                    'sesi_terlaksana' => (int) $r->total_sesi_terlaksana,
                    'sesi_jadwal'     => (int) $r->total_sesi_jadwal,
                ] : null,
            ];
        })->sortBy(fn($r) => $r['skor'] ?? 999)->values();  // skor terendah dulu = perlu perhatian

        return response()->json(['success' => true, 'data' => [
            'bulan' => $bulan, 'tahun' => $tahun, 'guru' => $rows,
        ]]);
    }
}
