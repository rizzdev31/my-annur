<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiKegiatan;
use App\Models\AbsensiKegiatanPeserta;
use App\Models\PenugasanTambahan;
use App\Models\RealisasiTugasJabatan;
use App\Models\SettingJamKerja;
use App\Models\TugasJabatan;
use App\Models\TenagaPendidik;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TugasApiController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // TUGAS TAMBAHAN (mandiri)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /tugas — semua penugasan aktif guru ini
     */
    public function index(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $list = PenugasanTambahan::with(['tugasTambahan.settingVakasi'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereHas('tugasTambahan', fn($q) =>
                $q->where('status', 'aktif')
                  ->where('tanggal_mulai', '<=', now()->toDateString())
                  ->where(fn($q2) =>
                      $q2->whereNull('tanggal_selesai')
                         ->orWhere('tanggal_selesai', '>=', now()->toDateString())
                  )
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $list->map(fn($p) => $this->formatPenugasan($p))->values(),
        ]);
    }

    /**
     * GET /tugas/aktif — penugasan yang sedang/belum dikerjakan
     */
    public function aktif(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        // Absen_kegiatan bertipe RENTANG (mis. Maulid 22-25) boleh punya banyak kegiatan.
        // Menyelesaikan 1 kegiatan menandai penugasan 'selesai' (agar vakasi pengabsen mengalir),
        // tapi tugas HARUS tetap tampil selama masih dalam rentang tanggal supaya guru bisa
        // menambah kegiatan berikutnya. Maka: tampilkan bila belum/sedang, ATAU absen_kegiatan
        // yang tanggal_selesai-nya belum lewat (independen status_pengerjaan).
        $list = PenugasanTambahan::with(['tugasTambahan.settingVakasi'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereHas('tugasTambahan', fn($q) => $q->where('status', 'aktif'))
            ->where(function ($q) {
                $q->whereIn('status_pengerjaan', ['belum', 'sedang'])
                  ->orWhereHas('tugasTambahan', fn($t) => $t
                      ->where('tipe_pengerjaan', 'absen_kegiatan')
                      ->whereDate('tanggal_selesai', '>=', TimezoneHelper::today()));
            })
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $list->map(fn($p) => $this->formatPenugasan($p))->values(),
            'total'   => $list->count(),
        ]);
    }

    /**
     * GET /tugas/{penugasan} — detail penugasan
     */
    public function show(Request $request, $penugasanId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $p = PenugasanTambahan::with(['tugasTambahan.settingVakasi', 'kegiatanList'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->findOrFail($penugasanId);

        $data = $this->formatPenugasan($p);

        // Jika absen_kegiatan, sertakan SEMUA kegiatan (tugas rentang boleh banyak kegiatan).
        if ($p->tugasTambahan?->tipe_pengerjaan === 'absen_kegiatan') {
            $data['kegiatan_list'] = $p->kegiatanList->map(fn($k) => $this->formatKegiatan($k))->values();
            // backward-compat: kegiatan terbaru (single)
            $data['kegiatan'] = $data['kegiatan_list']->first();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /tugas/{penugasan}/mulai — tandai sedang dikerjakan
     */
    public function mulai(Request $request, $penugasanId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $p = PenugasanTambahan::where('tenaga_pendidik_id', $tp->id)->findOrFail($penugasanId);

        if ($p->status_pengerjaan === 'selesai') {
            return response()->json(['success' => false, 'message' => 'Tugas sudah selesai.'], 422);
        }

        $p->update([
            'status_pengerjaan' => 'sedang',
            'dikerjakan_pada'   => TimezoneHelper::now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Tugas dimulai.', 'data' => $this->formatPenugasan($p)]);
    }

    /**
     * POST /tugas/{penugasan}/laporan — submit bukti (mandiri)
     * Body: bukti_tipe (teks|foto|link), teks_bukti, link_bukti, foto (file)
     */
    public function kirimLaporan(Request $request, $penugasanId): JsonResponse
    {
        $request->validate([
            'bukti_tipe'  => 'required|in:teks,foto,link',
            'teks_bukti'  => 'required_if:bukti_tipe,teks|nullable|string|max:1000',
            'link_bukti'  => 'required_if:bukti_tipe,link|nullable|url|max:500',
            'foto'        => 'required_if:bukti_tipe,foto|nullable|image|mimes:jpeg,jpg,png|max:3072',
            'laporan'     => 'nullable|string|max:500',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $p = PenugasanTambahan::with('tugasTambahan')
            ->where('tenaga_pendidik_id', $tp->id)
            ->findOrFail($penugasanId);

        if ($p->status_pengerjaan === 'selesai') {
            return response()->json(['success' => false, 'message' => 'Tugas sudah selesai.'], 422);
        }

        // Upload foto jika ada
        $filePath = null;
        if ($request->bukti_tipe === 'foto' && $request->hasFile('foto')) {
            $filePath = $request->file('foto')->store("bukti-tugas/{$tp->id}", 'public');
        }

        $p->update([
            'status_pengerjaan' => 'selesai',
            'bukti_tipe'        => $request->bukti_tipe,
            'teks_bukti'        => $request->teks_bukti,
            'link_bukti'        => $request->link_bukti,
            'file_laporan'      => $filePath,
            'laporan'           => $request->laporan,
            'dilaporkan_pada'   => TimezoneHelper::now(),
            'dikerjakan_pada'   => $p->dikerjakan_pada ?? TimezoneHelper::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dikirim. Menunggu verifikasi admin.',
            'data'    => $this->formatPenugasan($p->fresh()),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TUGAS JABATAN
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /tugas/jabatan/list — tugas jabatan aktif milik guru ini
     */
    public function tugasJabatan(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        // Ambil jabatan aktif guru
        $jabatanIds = $tp->jabatan_aktif->pluck('id')->toArray();
        if ($tp->jabatan_id && !in_array($tp->jabatan_id, $jabatanIds)) {
            $jabatanIds[] = $tp->jabatan_id;
        }

        // settingVakasi tidak lagi di TugasJabatan (kolom di-DROP) — load tanpa relasi itu
        $tugas = TugasJabatan::whereIn('jabatan_id', $jabatanIds)
            ->aktif()
            ->get();

        // Cek realisasi pada WINDOW sesuai frekuensi (harian/mingguan/bulanan/insidental)
        $now   = TimezoneHelper::now();
        $bulan = $now->month;
        $tahun = $now->year;

        $data = $tugas->map(function ($t) use ($tp, $now, $bulan, $tahun) {
            // Status "sudah dikerjakan" dihitung per periode frekuensi berjalan,
            // BUKAN selalu per bulan — agar tugas harian muncul lagi tiap hari.
            $q = RealisasiTugasJabatan::where('tugas_jabatan_id', $t->id)
                ->where('tenaga_pendidik_id', $tp->id);

            $periodeLabel = 'bulan ini';
            switch ($t->frekuensi) {
                case 'harian':
                    $q->whereDate('tanggal', $now->toDateString());
                    $periodeLabel = 'hari ini';
                    break;
                case 'mingguan':
                    $q->whereBetween('tanggal', [
                        $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                        $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    ]);
                    $periodeLabel = 'minggu ini';
                    break;
                default: // bulanan & insidental → window bulan berjalan
                    $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
                    $periodeLabel = 'bulan ini';
            }
            $realisasi = $q->latest()->first();

            // Untuk absen_kegiatan: cek kegiatan bulan ini
            $kegiatanBulanIni = null;
            if ($t->tipe_pengerjaan === 'absen_kegiatan') {
                $kegiatanBulanIni = AbsensiKegiatan::where('sumber_tipe', 'tugas_jabatan')
                    ->where('sumber_id', $t->id)
                    ->where('pengabsen_id', $tp->id)
                    ->whereMonth('tanggal_kegiatan', $bulan)
                    ->whereYear('tanggal_kegiatan', $tahun)
                    ->get()
                    ->map(fn($k) => $this->formatKegiatan($k))
                    ->values();
            }

            return [
                'id'               => $t->id,
                'nama_tugas'       => $t->nama_tugas,
                'deskripsi'        => $t->deskripsi,
                'frekuensi'        => $t->frekuensi,
                'frekuensi_label'  => $t->frekuensi_label,
                'periode_label'    => $periodeLabel, // konteks window: hari ini/minggu ini/bulan ini
                'tipe_pengerjaan'  => $t->tipe_pengerjaan ?? 'mandiri',
                'perlu_verifikasi' => (bool) ($t->perlu_verifikasi ?? true),
                // nominal_vakasi tugas jabatan kini via SettingVakasi lingkup jabatan di payroll
                'nominal_vakasi'   => 0,
                // wajib_laporan di-DROP — semua tugas jabatan dianggap wajib
                'wajib_laporan'    => true,
                // Status realisasi PADA window frekuensi berjalan
                'sudah_realisasi'  => $realisasi !== null,
                'realisasi_id'     => $realisasi?->id,
                'realisasi_status' => $realisasi?->disetujui === true
                    ? 'disetujui' : ($realisasi ? 'menunggu' : null),
                'bukti_ada'        => $realisasi?->hasBukti() ?? false,
                // Kegiatan (jika absen_kegiatan)
                'kegiatan_bulan_ini'=> $kegiatanBulanIni,
            ];
        });

        return response()->json(['success' => true, 'data' => $data->values()]);
    }

    /**
     * POST /tugas/jabatan/{tugasJabatan}/realisasi — submit realisasi + bukti
     * Body: bukti_tipe, teks_bukti, link_bukti, foto, keterangan
     */
    public function realisasiJabatan(Request $request, $tugasJabatanId): JsonResponse
    {
        $request->validate([
            'bukti_tipe'  => 'required|in:teks,foto,link',
            'teks_bukti'  => 'required_if:bukti_tipe,teks|nullable|string|max:1000',
            'link_bukti'  => 'required_if:bukti_tipe,link|nullable|url|max:500',
            'foto'        => 'required_if:bukti_tipe,foto|nullable|image|mimes:jpeg,jpg,png|max:3072',
            'keterangan'  => 'nullable|string|max:500',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $tugasJabatan = TugasJabatan::findOrFail($tugasJabatanId);

        // Cek duplikasi hari ini
        $existing = RealisasiTugasJabatan::where('tugas_jabatan_id', $tugasJabatan->id)
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada realisasi untuk tugas ini hari ini.',
                'code'    => 'ALREADY_SUBMITTED',
            ], 422);
        }

        // Upload foto jika ada
        $filePath = null;
        if ($request->bukti_tipe === 'foto' && $request->hasFile('foto')) {
            $filePath = $request->file('foto')->store("bukti-tugas-jabatan/{$tp->id}", 'public');
        }

        // Auto-sah jika tugas tidak butuh verifikasi; selain itu menunggu approve admin.
        $autoSah   = !($tugasJabatan->perlu_verifikasi ?? true);
        $disetujui = $autoSah ? true : null;

        // Disiplin: tandai terlambat bila dikirim setelah jam masuk (deadline harian).
        $terlambat = false;
        $now       = TimezoneHelper::now();
        $jadwal    = SettingJamKerja::getDefault()?->getJamUntukHari(TimezoneHelper::namaHariDB($now));
        if ($jadwal && !empty($jadwal['jam_masuk'])) {
            $batas     = TimezoneHelper::parse($now->toDateString().' '.$jadwal['jam_masuk']);
            $terlambat = $now->gt($batas);
        }

        $realisasi = RealisasiTugasJabatan::create([
            'tugas_jabatan_id'  => $tugasJabatan->id,
            'tenaga_pendidik_id'=> $tp->id,
            'tanggal'           => TimezoneHelper::today()->toDateString(),
            'keterangan'        => $request->keterangan,
            'file_bukti'        => $filePath,
            'bukti_tipe'        => $request->bukti_tipe,
            'link_bukti'        => $request->link_bukti,
            'teks_bukti'        => $request->teks_bukti,
            'disetujui'         => $disetujui,
            'terlambat'         => $terlambat,
        ]);

        return response()->json([
            'success' => true,
            'message' => $autoSah
                ? ($terlambat ? 'Realisasi tersimpan & sah (tercatat terlambat).' : 'Realisasi tersimpan & sah.')
                : 'Realisasi tugas berhasil dikirim. Menunggu verifikasi.',
            'data'    => [
                'id'          => $realisasi->id,
                'tanggal'     => $realisasi->tanggal->format('Y-m-d'),
                'bukti_tipe'  => $realisasi->bukti_tipe,
                'disetujui'   => $disetujui,
                'terlambat'   => $terlambat,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABSENSI KEGIATAN (tipe_pengerjaan = absen_kegiatan)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /kegiatan — kegiatan yang jadi tanggung jawab guru ini
     */
    public function kegiatanSaya(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $list = AbsensiKegiatan::with(['peserta.tenagaPendidik.user'])
            ->where('pengabsen_id', $tp->id)
            ->orderByDesc('tanggal_kegiatan')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $list->map(fn($k) => $this->formatKegiatan($k, true))->values(),
        ]);
    }

    /**
     * GET /kegiatan/{id} — detail kegiatan + daftar peserta
     */
    public function kegiatanDetail(Request $request, $kegiatanId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $kegiatan = AbsensiKegiatan::with([
                'peserta.tenagaPendidik.user',
                'peserta.tenagaPendidik.jabatan',
            ])
            ->where('pengabsen_id', $tp->id)
            ->findOrFail($kegiatanId);

        return response()->json([
            'success' => true,
            'data'    => [
                ...$this->formatKegiatan($kegiatan),
                'peserta' => $kegiatan->peserta->map(fn($p) => [
                    'id'               => $p->id,
                    'tenaga_pendidik_id'=> $p->tenaga_pendidik_id,
                    'nama'             => $p->tenagaPendidik->user->name,
                    'jabatan'          => $p->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                    'foto'             => $p->tenagaPendidik->user->foto
                        ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
                    'status_kehadiran' => $p->status_kehadiran,
                    'jam_hadir'        => $p->jam_hadir,
                    'keterangan'       => $p->keterangan,
                    'vakasi_diberikan' => $p->vakasi_diberikan,
                    'nominal_vakasi'   => $p->nominal_vakasi,
                    'status_label'     => $p->statusLabel(),
                ])->values(),
                // semua_guru: daftar guru yang BISA ditambah sebagai peserta.
                // Exclude: pengabsen (bukan peserta) + peserta yang sudah ada.
                'semua_guru'   => TenagaPendidik::aktif()
                    ->with(['user', 'jabatan'])
                    ->whereNotIn('id', array_merge(
                        [$kegiatan->pengabsen_id],
                        $kegiatan->peserta->pluck('tenaga_pendidik_id')->toArray()
                    ))
                    ->get()
                    ->map(fn($g) => [
                        'id'      => $g->id,
                        'nama'    => $g->user->name,
                        'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                        'foto'    => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                    ])->values(),
            ],
        ]);
    }

    /**
     * POST /kegiatan — buat kegiatan baru
     */
    public function buatKegiatan(Request $request): JsonResponse
    {
        $request->validate([
            'sumber_tipe'       => 'required|in:tugas_jabatan,tugas_tambahan',
            'sumber_id'         => 'required|integer',
            'nama_kegiatan'     => 'required|string|max:200',
            'tanggal_kegiatan'  => 'required|date',
            'jam_mulai'         => 'nullable|date_format:H:i',
            'jam_selesai'       => 'nullable|date_format:H:i',
            'deskripsi'         => 'nullable|string|max:500',
            'lokasi'            => 'nullable|string|max:200',
            'peserta_ids'       => 'nullable|array',
            'peserta_ids.*'     => 'exists:tenaga_pendidik,id',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        // Auto-ambil vakasi dari tugas.
        // Catatan: tugas_jabatan.setting_vakasi_id sudah di-DROP — untuk tugas jabatan,
        // nominal vakasi harus diinput manual saat membuat kegiatan (field vakasi_per_peserta).
        $vakasiPeserta = null;
        if ($request->sumber_tipe === 'tugas_jabatan') {
            // Tidak ada nominal di TugasJabatan — admin/guru input manual saat buat kegiatan
            $vakasiPeserta = null;
        } else {
            $t = \App\Models\TugasTambahan::with('settingVakasi')->find($request->sumber_id);
            $vakasiPeserta = $t?->vakasi_override ?? $t?->settingVakasi?->nominal;
        }

        // BUG FIX: Resolve penugasan_id agar selesaikanKegiatan() dapat auto-approve.
        // Jika sumber adalah tugas_tambahan, cari PenugasanTambahan guru ini untuk tugas tersebut.
        // Tanpa ini, $kegiatan->penugasan_id selalu null dan blok auto-approve tidak pernah berjalan,
        // sehingga vakasi tugas tambahan bertipe absen_kegiatan tidak pernah terhitung saat generate gaji.
        $penugasanId = null;
        if ($request->sumber_tipe === 'tugas_tambahan') {
            $penugasanId = PenugasanTambahan::where('tenaga_pendidik_id', $tp->id)
                ->where('tugas_tambahan_id', $request->sumber_id)
                ->value('id');
        }

        $kegiatan = DB::transaction(function () use ($request, $tp, $vakasiPeserta, $penugasanId) {
            $k = AbsensiKegiatan::create([
                'sumber_tipe'        => $request->sumber_tipe,
                'sumber_id'          => $request->sumber_id,
                'penugasan_id'       => $penugasanId,  // BUG FIX: set relasi ke PenugasanTambahan
                'pengabsen_id'       => $tp->id,
                'nama_kegiatan'      => $request->nama_kegiatan,
                'tanggal_kegiatan'   => $request->tanggal_kegiatan,
                'jam_mulai'          => $request->jam_mulai,
                'jam_selesai'        => $request->jam_selesai,
                'deskripsi'          => $request->deskripsi,
                'lokasi'             => $request->lokasi,
                'vakasi_per_peserta' => $vakasiPeserta,
                'status'             => 'berlangsung',
                'dibuat_oleh'        => $tp->user_id ?? null,
            ]);

            // Status 'belum' = belum diabsen oleh guru — bukan 'hadir' langsung.
            // Guru yang akan menandai kehadiran via Flutter saat kegiatan berlangsung.
            // Exclude pengabsen ($tp->id) dari daftar peserta — pengabsen adalah
            // penanggung jawab kegiatan, bukan peserta. Vakasi pengabsen via PenugasanTambahan.
            // firstOrCreate: hindari duplicate entry dari UNIQUE KEY unique_peserta_kegiatan.
            $pesertaIds = array_filter(
                $request->peserta_ids ?? [],
                fn($id) => $id != $tp->id
            );
            foreach ($pesertaIds as $gId) {
                AbsensiKegiatanPeserta::firstOrCreate(
                    [
                        'absensi_kegiatan_id' => $k->id,
                        'tenaga_pendidik_id'  => $gId,
                    ],
                    [
                        'status_kehadiran' => 'belum',
                    ]
                );
            }

            return $k;
        });

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil dibuat.',
            'data'    => $this->formatKegiatan($kegiatan),
        ]);
    }

    /**
     * POST /kegiatan/{id}/peserta — tambah peserta
     */
    public function tambahPeserta(Request $request, $kegiatanId): JsonResponse
    {
        $request->validate([
            'tenaga_pendidik_ids'   => 'required|array',
            'tenaga_pendidik_ids.*' => 'exists:tenaga_pendidik,id',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $kegiatan = AbsensiKegiatan::where('pengabsen_id', $tp->id)->findOrFail($kegiatanId);

        // Exclude pengabsen dari peserta — mencegah duplikat entry dan double vakasi
        $ids = array_filter(
            $request->tenaga_pendidik_ids,
            fn($id) => $id != $kegiatan->pengabsen_id
        );

        $added = 0;
        foreach ($ids as $id) {
            // firstOrCreate: aman dari race condition dan UNIQUE KEY duplicate entry.
            // wasRecentlyCreated = true jika baru dibuat, false jika sudah ada.
            $peserta = AbsensiKegiatanPeserta::firstOrCreate(
                [
                    'absensi_kegiatan_id' => $kegiatanId,
                    'tenaga_pendidik_id'  => $id,
                ],
                [
                    'status_kehadiran' => 'belum',
                ]
            );
            if ($peserta->wasRecentlyCreated) $added++;
        }

        return response()->json(['success' => true, 'message' => "{$added} peserta ditambahkan."]);
    }

    /**
     * PATCH /kegiatan/{id}/absensi-bulk — simpan absensi peserta
     *
     * Jika kegiatan sudah berstatus 'selesai' (mis. di-selesaikan dari web admin
     * sebelum absensi diisi), endpoint ini tetap menerima update dan otomatis
     * mendistribusikan ulang vakasi berdasarkan status terbaru.
     */
    public function updateAbensiBulk(Request $request, $kegiatanId): JsonResponse
    {
        $request->validate([
            'absensi'                    => 'required|array|min:1',
            'absensi.*.id'               => 'required|integer|exists:absensi_kegiatan_peserta,id',
            'absensi.*.status_kehadiran' => 'required|in:hadir,terlambat,izin,alfa',
            'absensi.*.jam_hadir'        => 'nullable|date_format:H:i',
            'absensi.*.keterangan'       => 'nullable|string|max:300',
        ]);

        // ── 1. Guard: pastikan user punya relasi tenagaPendidik ──────────────
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            \Log::warning('[KEGIATAN_API] updateAbensiBulk: user tidak memiliki data tenaga pendidik', [
                'user_id' => $request->user()->id,
            ]);
            return $this->notFound();
        }

        // ── 2. Ambil kegiatan — hanya bisa diakses oleh pengabsen ────────────
        $kegiatan = AbsensiKegiatan::where('pengabsen_id', $tp->id)
            ->findOrFail($kegiatanId);

        \Log::info('[KEGIATAN_API] updateAbensiBulk', [
            'kegiatan_id'  => $kegiatanId,
            'pengabsen_id' => $tp->id,
            'status'       => $kegiatan->status,
            'jumlah_item'  => count($request->absensi),
            'payload'      => $request->absensi,
        ]);

        // ── 3. Kumpulkan ID peserta yang boleh diupdate (harus milik kegiatan ini) ──
        // Verifikasi kepemilikan: hanya update peserta yang benar-benar ada di
        // kegiatan ini. Mencegah update diam-diam ke peserta kegiatan lain.
        $validPesertaIds = AbsensiKegiatanPeserta::where('absensi_kegiatan_id', $kegiatan->id)
            ->pluck('id')
            ->toArray();

        $updatedCount = 0;

        DB::transaction(function () use ($request, $kegiatan, $validPesertaIds, &$updatedCount) {
            // ── 3a. Update status kehadiran tiap peserta ──────────────────────
            foreach ($request->absensi as $item) {
                // Skip jika ID tidak termasuk peserta kegiatan ini
                if (!in_array($item['id'], $validPesertaIds)) {
                    \Log::warning('[KEGIATAN_API] Peserta ID '.$item['id'].' bukan milik kegiatan '.$kegiatan->id.', dilewati.');
                    continue;
                }

                $affected = AbsensiKegiatanPeserta::where('id', $item['id'])
                    ->where('absensi_kegiatan_id', $kegiatan->id)
                    ->update([
                        'status_kehadiran' => $item['status_kehadiran'],
                        'jam_hadir'        => $item['jam_hadir'] ?? null,
                        'keterangan'       => $item['keterangan'] ?? null,
                    ]);

                $updatedCount += $affected;
            }

            \Log::info('[KEGIATAN_API] updateAbensiBulk: '.$updatedCount.' baris diperbarui');

            // ── 3b. Re-distribusi vakasi jika kegiatan sudah selesai ──────────
            // Kasus: kegiatan di-selesaikan dari web admin sebelum absensi diisi,
            // sehingga semua peserta masih 'belum'. Guru mengisi dari Flutter →
            // vakasi harus didistribusikan ulang sesuai status terbaru.
            if ($kegiatan->status === 'selesai' && ($kegiatan->vakasi_per_peserta ?? 0) > 0) {
                // Reset semua vakasi dulu
                AbsensiKegiatanPeserta::where('absensi_kegiatan_id', $kegiatan->id)
                    ->update([
                        'vakasi_diberikan' => false,
                        'nominal_vakasi'   => null,
                    ]);

                // Distribusikan ulang ke peserta yang hadir/terlambat (kecuali pengabsen)
                $vakasiCount = AbsensiKegiatanPeserta::where('absensi_kegiatan_id', $kegiatan->id)
                    ->whereIn('status_kehadiran', ['hadir', 'terlambat'])
                    ->where('tenaga_pendidik_id', '!=', $kegiatan->pengabsen_id)
                    ->update([
                        'vakasi_diberikan' => true,
                        'nominal_vakasi'   => $kegiatan->vakasi_per_peserta,
                    ]);

                \Log::info('[KEGIATAN_API] Re-distribusi vakasi ke '.$vakasiCount.' peserta');
            }
        });

        $message = $kegiatan->status === 'selesai'
            ? 'Absensi diperbarui. Vakasi telah didistribusikan ulang.'
            : 'Absensi berhasil disimpan.';

        return response()->json([
            'success'      => true,
            'message'      => $message,
            'updated_rows' => $updatedCount,
        ]);
    }

    /**
     * POST /kegiatan/{id}/selesaikan — selesaikan + distribusi vakasi otomatis
     */
    public function selesaikanKegiatan(Request $request, $kegiatanId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $kegiatan = AbsensiKegiatan::with('peserta')
            ->where('pengabsen_id', $tp->id)
            ->findOrFail($kegiatanId);

        if ($kegiatan->status !== 'berlangsung') {
            return response()->json(['success' => false, 'message' => 'Kegiatan sudah ' . $kegiatan->status . '.'], 422);
        }

        DB::transaction(function () use ($kegiatan) {
            $kegiatan->update(['status' => 'selesai']);

            $nominal = $kegiatan->vakasi_per_peserta ?? 0;

            // Distribusi vakasi ke peserta yang hadir.
            // Safety net: exclude pengabsen walau secara teori sudah tidak ada di tabel peserta.
            // Vakasi pengabsen disalurkan via PenugasanTambahan di bawah.
            if ($nominal > 0) {
                $kegiatan->peserta()
                    ->whereIn('status_kehadiran', ['hadir', 'terlambat'])
                    ->where('tenaga_pendidik_id', '!=', $kegiatan->pengabsen_id)
                    ->update([
                        'vakasi_diberikan' => true,
                        'nominal_vakasi'   => $nominal,
                    ]);
            }

            // Auto-approve penugasan pengabsen.
            // Guru menyelesaikan kegiatan via mobile = bukti fisik → langsung selesai+disetujui.
            // PENTING: set dikerjakan_pada agar tidak ada constraint "belum dimulai"
            // pada PenugasanTambahan yang status-nya masih 'belum'.
            if ($kegiatan->penugasan_id) {
                $penugasan = \App\Models\PenugasanTambahan::find($kegiatan->penugasan_id);
                if ($penugasan && $penugasan->status_pengerjaan !== 'selesai') {
                    $penugasan->update([
                        'status_pengerjaan' => 'selesai',
                        'dikerjakan_pada'   => $penugasan->dikerjakan_pada ?? TimezoneHelper::now(),
                        'dilaporkan_pada'   => TimezoneHelper::now(),
                        'disetujui'         => true,
                        'catatan_verifikasi'=> 'Otomatis disetujui saat kegiatan selesai.',
                    ]);
                }
            }
            if ($kegiatan->realisasi_id) {
                \App\Models\RealisasiTugasJabatan::find($kegiatan->realisasi_id)
                    ?->update(['disetujui' => true]);
            }
        });

        $hadir = $kegiatan->fresh()->peserta->whereIn('status_kehadiran', ['hadir','terlambat'])->count();

        // Reminder ke admin: ada kegiatan yang baru diselesaikan guru.
        \App\Services\NotifikasiService::keSuperadmin(
            'Kegiatan Selesai',
            ($tp->user->name ?? 'Guru') . " menyelesaikan kegiatan \"{$kegiatan->nama_kegiatan}\" ({$hadir} peserta hadir).",
            'tugas_update',
            ['type' => 'kegiatan', 'kegiatan_id' => $kegiatan->id],
        );

        return response()->json([
            'success' => true,
            'message' => "Kegiatan selesai! Vakasi didistribusikan ke {$hadir} peserta.",
            'data'    => $this->formatKegiatan($kegiatan->fresh()),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FORMAT HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function formatPenugasan(PenugasanTambahan $p): array
    {
        $tugas = $p->tugasTambahan;
        // Absen_kegiatan bertipe rentang yang tanggal_selesai-nya belum lewat → masih bisa
        // ditambah kegiatan (walau status_pengerjaan sudah 'selesai' dari kegiatan pertama).
        $rentangAktif = $tugas?->tipe_pengerjaan === 'absen_kegiatan'
            && $tugas?->tanggal_selesai
            && TimezoneHelper::today()->lte(\Carbon\Carbon::parse($tugas->tanggal_selesai, TimezoneHelper::TZ));
        return [
            'id'               => $p->id,
            'tugas_tambahan_id'=> $tugas?->id,   // master id: dipakai sbg sumber_id saat buat kegiatan
            'rentang_aktif'    => (bool) $rentangAktif,
            'judul'            => $tugas?->judul ?? '—',
            'deskripsi'        => $tugas?->deskripsi,
            'tipe_pengerjaan'  => $tugas?->tipe_pengerjaan ?? 'mandiri',
            'tanggal_mulai'    => $tugas?->tanggal_mulai?->toDateString(),
            'tanggal_selesai'  => $tugas?->tanggal_selesai?->toDateString(),
            'status_pengerjaan'=> $p->status_pengerjaan,
            'status_label'     => match($p->status_pengerjaan) {
                'belum'         => 'Belum Dikerjakan',
                'sedang'        => 'Sedang Dikerjakan',
                'selesai'       => 'Selesai',
                'tidak_selesai' => 'Tidak Selesai',
                default         => $p->status_pengerjaan,
            },
            'wajib_laporan'    => $tugas?->wajib_laporan ?? false,
            'nominal_vakasi'   => $p->getNominalVakasi(),
            'bukti_tipe'       => $p->bukti_tipe,
            'link_bukti'       => $p->link_bukti,
            'teks_bukti'       => $p->teks_bukti,
            'file_laporan_url' => $p->file_laporan_url,
            'disetujui'        => $p->disetujui,
            'catatan_verifikasi'=> $p->catatan_verifikasi,
            'dilaporkan_pada'  => $p->dilaporkan_pada?->format('d M Y H:i'),
            // Absen kegiatan
            'kegiatan_id'      => $p->kegiatanAbsensi?->id,
        ];
    }

    private function formatKegiatan(AbsensiKegiatan $k, bool $withStats = false): array
    {
        $data = [
            'id'               => $k->id,
            'sumber_tipe'      => $k->sumber_tipe,
            'sumber_id'        => $k->sumber_id,
            'nama_kegiatan'    => $k->nama_kegiatan,
            'tanggal_kegiatan' => $k->tanggal_kegiatan?->toDateString(),
            'jam_mulai'        => $k->jam_mulai,
            'jam_selesai'      => $k->jam_selesai,
            'lokasi'           => $k->lokasi,
            'deskripsi'        => $k->deskripsi,
            'status'           => $k->status,
            'vakasi_per_peserta'=> $k->vakasi_per_peserta,
        ];

        if ($withStats) {
            $peserta = $k->relationLoaded('peserta') ? $k->peserta : $k->peserta()->get();
            $data['total_peserta'] = $peserta->count();
            $data['hadir']         = $peserta->whereIn('status_kehadiran', ['hadir','terlambat'])->count();
            $data['alfa']          = $peserta->where('status_kehadiran', 'alfa')->count();
        }

        return $data;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }
}