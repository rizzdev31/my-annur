<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\Santri;
use App\Models\Surah;
use App\Models\HafalanSantri;
use App\Models\HafalanJuz;
use App\Models\SetoranTahfidz;
use App\Models\TenagaPendidik;
use App\Models\TugasTasmi;
use App\Services\TahfidzService;
use App\Services\TasmiService;
use App\Services\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TahfidzApiController extends Controller
{
    /** GET /education/surah — referensi surah untuk dropdown setoran. */
    public function surah(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Surah::orderBy('nomor')->get(['nomor', 'nama', 'jumlah_ayat']),
        ]);
    }

    /**
     * GET /education/tahfidz/jadwal-hari-ini
     * Jadwal kelas tahfidz milik guru hari ini + status absen.
     */
    public function jadwalHariIni(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $today    = TimezoneHelper::today();
        $namaHari = TimezoneHelper::namaHariDB($today);

        // SEMUA kelas tahfidz guru (lintas hari) — jurnal/setoran bisa dibuka kapan saja.
        // Absen tetap hanya untuk jadwal yang berlangsung hari ini (dalam jam).
        $jadwal = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('is_aktif', true)
            ->whereHas('mataPelajaran', fn($q) => $q->where('tipe', 'tahfidz'))
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderBy('jam_mulai')->get();

        $absensi = AbsensiMengajar::whereDate('tanggal', $today)
            ->where('tenaga_pendidik_id', $tp->id)->get()->keyBy('jadwal_mengajar_id');

        $now = TimezoneHelper::now();
        $data = $jadwal->map(function ($j) use ($absensi, $today, $now, $namaHari) {
            $isToday = strtolower($j->hari) === $namaHari;
            $am      = $isToday ? $absensi->get($j->id) : null;
            $jumlahSantri = $j->kelas_id
                ? Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $j->kelas_id))->count() : 0;

            // Dalam jam mengajar (hanya bila jadwal hari ini) → absen WAJIB sebelum isi jurnal.
            $dalamJam = false;
            if ($isToday) {
                $jamMulai   = Carbon::parse($today->toDateString().' '.$j->jam_mulai, TimezoneHelper::TZ);
                $jamSelesai = Carbon::parse($today->toDateString().' '.$j->jam_selesai, TimezoneHelper::TZ);
                $dalamJam   = $now->betweenIncluded($jamMulai, $jamSelesai);
            }

            return [
                'jadwal_id'           => $j->id,
                'mata_pelajaran'      => $j->mataPelajaran?->nama ?? 'Tahfidz',
                'kelas'               => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                'kelas_id'            => $j->kelas_id,
                'hari'                => $j->hari,
                'is_today'            => $isToday,
                'jam_mulai'           => $j->jam_mulai,
                'jam_selesai'         => $j->jam_selesai,
                'jumlah_jp'           => $j->jumlah_jp,
                'jumlah_santri'       => $jumlahSantri,
                'sudah_absen'         => $am !== null,
                'absensi_mengajar_id' => $am?->id,
                'materi'              => $am?->materi,
                'catatan'             => $am?->keterangan,
                // Kontrol alur jurnal/absen
                'dalam_jam'           => $dalamJam,
                'wajib_absen'         => $dalamJam && $am === null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'tanggal' => $today->locale('id')->isoFormat('dddd, D MMMM YYYY'),
                'jadwal'  => $data->values(),
            ],
        ]);
    }

    /**
     * POST /education/tahfidz/absen
     * Absen santri kelas tahfidz → buat sesi (AbsensiMengajar, JP, tanpa foto) +
     * absensi_santri. Terkunci (bukti mengajar/kredensial). Lalu lanjut setoran.
     */
    public function absen(Request $request): JsonResponse
    {
        $request->validate([
            'jadwal_id'           => 'required|exists:jadwal_mengajar,id',
            'absensi'             => 'required|array|min:1',
            'absensi.*.santri_id' => 'required|integer|exists:santri,id',
            'absensi.*.status'    => 'required|in:hadir,telat,alpha',
            'deskripsi'           => 'nullable|string|max:500',
            'catatan'             => 'nullable|string|max:300',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $jadwal = JadwalMengajar::with('mataPelajaran')->findOrFail($request->jadwal_id);
        if ($jadwal->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan milik Anda.'], 403);
        }
        if (($jadwal->mataPelajaran?->tipe) !== 'tahfidz') {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan kelas tahfidz.'], 422);
        }

        $today = TimezoneHelper::today();
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($today)) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini tidak berlangsung hari ini.', 'code' => 'WRONG_DAY'], 422);
        }

        // Sudah absen hari ini → terkunci; kembalikan id agar lanjut setoran.
        $exist = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->whereDate('tanggal', $today)->first();
        if ($exist) {
            return response()->json([
                'success' => false,
                'message' => 'Absen kelas tahfidz hari ini sudah terkunci.',
                'code'    => 'SUDAH_ABSEN',
                'data'    => ['absensi_mengajar_id' => $exist->id],
            ], 422);
        }

        // Absen kehadiran WAJIB dalam jam mengajar. Di luar jam (belum absen) → tidak boleh:
        // kehadiran dilimpahkan ke guru piket & guru asli tidak dapat vakasi. Jurnal/setoran tetap kapan saja.
        $jamMulaiC   = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_mulai, TimezoneHelper::TZ);
        $jamSelesaiC = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_selesai, TimezoneHelper::TZ);
        if (!TimezoneHelper::now()->betweenIncluded($jamMulaiC, $jamSelesaiC)) {
            return response()->json([
                'success' => false,
                'message' => 'Absen hanya bisa dalam jam mengajar (' . substr($jadwal->jam_mulai, 0, 5)
                    . '–' . substr($jadwal->jam_selesai, 0, 5) . '). Di luar jam, kehadiran dilimpahkan ke guru piket. '
                    . 'Jurnal/setoran tetap bisa dilakukan kapan saja.',
                'code'    => 'DILUAR_JAM',
            ], 422);
        }
        $status = 'terlaksana';
        $jp     = $jadwal->jumlah_jp;

        $am = DB::transaction(function () use ($jadwal, $tp, $today, $request, $status, $jp) {
            $am = AbsensiMengajar::create([
                'jadwal_mengajar_id' => $jadwal->id,
                'tenaga_pendidik_id' => $tp->id,
                'tanggal'            => $today->toDateString(),
                'jam_mulai_aktual'   => TimezoneHelper::now()->format('H:i:s'),
                'jp_terlaksana'      => $jp,
                'status'             => $status,
                'materi'             => $request->deskripsi,
                'keterangan'         => $request->catatan,
                'sudah_buka_jurnal'  => true,
            ]);
            foreach ($request->absensi as $row) {
                AbsensiSantri::create([
                    'absensi_mengajar_id' => $am->id,
                    'santri_id'           => $row['santri_id'],
                    'status'              => $row['status'],
                ]);
            }
            return $am;
        });

        // Fase A — kirim santri "telat" ke RamahAnak (batch, idempotent). Aman bila gagal.
        app(\App\Services\EducationTelatSync::class)->pushSesi(
            $am,
            $jadwal->mataPelajaran?->nama ?? 'Tahfidz',
            "{$request->user()->name} (NIP {$tp->nip})",
        );

        // Notifikasi WA wali per santri (hadir/telat/alfa) — idempotent per baris.
        $pembelajaran = $jadwal->mataPelajaran?->nama ?? 'Tahfidz';
        foreach (AbsensiSantri::where('absensi_mengajar_id', $am->id)->get() as $as) {
            app(\App\Services\WaService::class)->absenMengajar(
                $as->santri_id, $as->status, $pembelajaran, $today->toDateString(), $as->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Absen kelas tahfidz tersimpan & dikunci. Lanjutkan setoran santri.',
            'data'    => ['absensi_mengajar_id' => $am->id, 'jp' => $jp, 'status' => $status],
        ]);
    }

    /**
     * GET /education/tahfidz/sesi/{absensiMengajarId}/santri
     * Roster santri kelas tahfidz untuk satu sesi + ringkasan hafalan.
     */
    public function rosterSesi(Request $request, $absensiMengajarId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $am = AbsensiMengajar::with(['jadwalMengajar.kelasRel', 'jadwalMengajar.mataPelajaran'])
            ->findOrFail($absensiMengajarId);
        if ($am->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Sesi ini bukan milik Anda.'], 403);
        }
        $kelasId = $am->jadwalMengajar?->kelas_id;
        if (!$kelasId) {
            return response()->json(['success' => false, 'message' => 'Kelas sesi belum tersinkron.', 'code' => 'KELAS_BELUM_SINKRON'], 422);
        }

        $totalQuran = (int) Surah::sum('jumlah_ayat');
        $santri = Santri::aktif()
            ->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelasId))
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);

        $ids      = $santri->pluck('id');
        $hafalan  = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
        $tasmiJuz = HafalanJuz::whereIn('santri_id', $ids)->where('status', 'selesai')
            ->get()->groupBy('santri_id');
        $quran = new \App\Services\QuranReferenceService();

        $data = $santri->map(function ($s) use ($hafalan, $tasmiJuz, $totalQuran, $quran) {
            $h = $hafalan->get($s->id);
            $total = $h?->total_ayat ?? 0;
            // Saran "lanjut dari" = ayat setelah hafalan terakhir (untuk hafalan baru).
            $lanjutSurah = null; $lanjutAyat = null;
            if ($h && $h->last_surah) {
                $batasPos = $quran->posisiAbsolut((int) ($h->last_surah_selesai ?? $h->last_surah), (int) ($h->last_ayat_selesai ?? 0));
                if ($batasPos < $quran->totalAyatQuran()) {
                    [$lanjutSurah, $lanjutAyat] = $quran->posisiKeSurahAyat($batasPos + 1);
                }
            }
            return [
                'santri_id'       => $s->id,
                'nip'             => $s->nip,
                'nama'            => $s->nama_lengkap,
                'total_ayat'      => $total,
                'persen'          => $totalQuran > 0 ? round($total / $totalQuran * 100, 2) : 0,
                'perlu_murojaah'  => (bool) ($h?->perlu_murojaah ?? false),
                'juz_perlu_tasmi' => $tasmiJuz->get($s->id)?->pluck('juz')->values() ?? [],
                'last_surah'         => $h?->last_surah,
                'last_surah_selesai' => $h?->last_surah_selesai,
                'last_ayat_mulai'    => $h?->last_ayat_mulai,
                'last_ayat_selesai'  => $h?->last_ayat_selesai,
                'lanjut_surah'       => $lanjutSurah,
                'lanjut_ayat'        => $lanjutAyat,
                // "Selesai semua" = total ayat hafalan ≥ total Quran (independen urutan juz).
                // Mencegah salah "30 juz selesai" untuk hafidz juz-amma yang baru juz 30.
                'selesai_semua'      => $total >= $totalQuran,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'absensi_mengajar_id' => $am->id,
                'kelas'               => $am->jadwalMengajar?->kelasRel?->nama ?? $am->jadwalMengajar?->kelas ?? '—',
                'mapel'               => $am->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                'total_santri'        => $data->count(),
                'santri'              => $data,
            ],
        ]);
    }

    /**
     * GET /education/tahfidz/jadwal/{jadwalId}/roster
     * Roster santri kelas tahfidz untuk SETORAN kapan saja (jurnal selalu terbuka).
     * Di dalam jam mengajar → wajib_absen=true (absen dulu); di luar jam → setoran tanpa sesi.
     */
    public function rosterJadwal(Request $request, $jadwalId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $jadwal = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])->findOrFail($jadwalId);
        if ($jadwal->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan milik Anda.'], 403);
        }
        if (($jadwal->mataPelajaran?->tipe) !== 'tahfidz') {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan kelas tahfidz.'], 422);
        }
        if (!$jadwal->kelas_id) {
            return response()->json(['success' => false, 'message' => 'Kelas jadwal belum tersinkron.', 'code' => 'KELAS_BELUM_SINKRON'], 422);
        }

        $today   = TimezoneHelper::today();
        $now     = TimezoneHelper::now();
        $isToday = strtolower($jadwal->hari) === TimezoneHelper::namaHariDB($today);
        // Absen sesi hanya relevan bila jadwal berlangsung hari ini (Senin–Jumat).
        $am = $isToday
            ? AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->whereDate('tanggal', $today)->first()
            : null;

        $jamMulai   = Carbon::parse($today->toDateString().' '.$jadwal->jam_mulai, TimezoneHelper::TZ);
        $jamSelesai = Carbon::parse($today->toDateString().' '.$jadwal->jam_selesai, TimezoneHelper::TZ);
        $dalamJam   = $isToday && $now->betweenIncluded($jamMulai, $jamSelesai);

        return response()->json([
            'success' => true,
            'data'    => array_merge([
                'absensi_mengajar_id' => $am?->id,            // null bila belum absen / hari non-jadwal → setoran tanpa sesi
                'jadwal_id'           => $jadwal->id,
                'kelas'               => $jadwal->kelasRel?->nama ?? $jadwal->kelas ?? '—',
                'mapel'               => $jadwal->mataPelajaran?->nama ?? '—',
                'hari'                => $jadwal->hari,
                'is_today'            => $isToday,
                'jam_mulai'           => $jadwal->jam_mulai,
                'jam_selesai'         => $jadwal->jam_selesai,
                'jumlah_jp'           => $jadwal->jumlah_jp,
                'sudah_absen'         => $am !== null,
                'dalam_jam'           => $dalamJam,
                // GERBANG absen: WAJIB hanya saat dalam jam mengajar sesi ini & belum absen.
                // Di luar jam (mis. sesi sore saat masih pagi) → tidak dipaksa absen,
                // setoran/jurnal tetap bisa kapan saja. Konsisten dgn jadwalHariIni.
                'wajib_absen'         => $dalamJam && $am === null,
            ], $this->rosterPayload($jadwal->kelas_id)),
        ]);
    }

    /** Bangun payload roster (total_santri + santri) untuk satu kelas. */
    private function rosterPayload(int $kelasId): array
    {
        $totalQuran = (int) Surah::sum('jumlah_ayat');
        $santri = Santri::aktif()
            ->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelasId))
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);

        $ids      = $santri->pluck('id');
        $hafalan  = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
        $tasmiJuz = HafalanJuz::whereIn('santri_id', $ids)->where('status', 'selesai')
            ->get()->groupBy('santri_id');
        $quran = new \App\Services\QuranReferenceService();

        $data = $santri->map(function ($s) use ($hafalan, $tasmiJuz, $totalQuran, $quran) {
            $h = $hafalan->get($s->id);
            $total = $h?->total_ayat ?? 0;
            // Saran "lanjut dari" = ayat setelah hafalan terakhir (untuk hafalan baru).
            $lanjutSurah = null; $lanjutAyat = null;
            if ($h && $h->last_surah) {
                $batasPos = $quran->posisiAbsolut((int) ($h->last_surah_selesai ?? $h->last_surah), (int) ($h->last_ayat_selesai ?? 0));
                if ($batasPos < $quran->totalAyatQuran()) {
                    [$lanjutSurah, $lanjutAyat] = $quran->posisiKeSurahAyat($batasPos + 1);
                }
            }
            return [
                'santri_id'       => $s->id,
                'nip'             => $s->nip,
                'nama'            => $s->nama_lengkap,
                'total_ayat'      => $total,
                'persen'          => $totalQuran > 0 ? round($total / $totalQuran * 100, 2) : 0,
                'perlu_murojaah'  => (bool) ($h?->perlu_murojaah ?? false),
                'juz_perlu_tasmi' => $tasmiJuz->get($s->id)?->pluck('juz')->values() ?? [],
                'last_surah'         => $h?->last_surah,
                'last_surah_selesai' => $h?->last_surah_selesai,
                'last_ayat_mulai'    => $h?->last_ayat_mulai,
                'last_ayat_selesai'  => $h?->last_ayat_selesai,
                'lanjut_surah'       => $lanjutSurah,
                'lanjut_ayat'        => $lanjutAyat,
                // "Selesai semua" = total ayat hafalan ≥ total Quran (independen urutan juz).
                // Mencegah salah "30 juz selesai" untuk hafidz juz-amma yang baru juz 30.
                'selesai_semua'      => $total >= $totalQuran,
            ];
        })->values();

        return ['total_santri' => $data->count(), 'santri' => $data];
    }

    /** POST /education/tahfidz/setoran — catat setoran (ziyadah/murojaah/tasmi). */
    public function setoran(Request $request): JsonResponse
    {
        $request->validate([
            'absensi_mengajar_id' => 'nullable|exists:absensi_mengajar,id',
            'santri_id'           => 'required|exists:santri,id',
            'jenis'               => 'required|in:ziyadah,murojaah_wajib,murojaah_tambahan,tasmi',
            // Surah/ayat hanya wajib untuk ziyadah & murojaah tambahan (manual);
            // murojaah wajib & tasmi diturunkan otomatis oleh service.
            'surah_mulai'         => 'nullable|integer|min:1|max:114',
            'ayat_mulai'          => 'nullable|integer|min:1',
            'surah_selesai'       => 'nullable|integer|min:1|max:114',
            'ayat_selesai'        => 'nullable|integer|min:1',
            'juz'                 => 'nullable|integer|min:1|max:30', // untuk tasmi'
            // Nilai WAJIB untuk hafalan baru (penentu lulus → batas hafalan maju).
            'nilai'               => 'required_if:jenis,ziyadah|nullable|numeric|min:1|max:10',
            // Catatan WAJIB tiap setoran → tracking pembelajaran untuk evaluasi.
            'catatan'             => 'required|string|min:3|max:300',
        ], [
            'catatan.required'  => 'Catatan wajib diisi untuk tracking pembelajaran.',
            'nilai.required_if' => 'Nilai wajib diisi untuk hafalan baru.',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        // Validasi sesi milik guru (jika dikirim)
        if ($request->absensi_mengajar_id) {
            $am = AbsensiMengajar::find($request->absensi_mengajar_id);
            if ($am && $am->tenaga_pendidik_id !== $tp->id) {
                return response()->json(['success' => false, 'message' => 'Sesi ini bukan milik Anda.'], 403);
            }
        }

        try {
            $setoran = (new TahfidzService())->catatSetoran([
                'absensi_mengajar_id' => $request->absensi_mengajar_id,
                'santri_id'           => $request->santri_id,
                'tenaga_pendidik_id'  => $tp->id,
                'jenis'               => $request->jenis,
                'surah_mulai'         => $request->surah_mulai,
                'ayat_mulai'          => $request->ayat_mulai,
                'surah_selesai'       => $request->surah_selesai,
                'ayat_selesai'        => $request->ayat_selesai,
                'juz'                 => $request->juz,
                'nilai'               => $request->nilai,
                'catatan'             => $request->catatan,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'GERBANG'], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'INVALID'], 422);
        }

        // Pesan reflektif khusus hafalan baru: lulus (batas maju) vs belum lulus (boleh diulang).
        $msg = 'Setoran tahfidz tersimpan.';
        if ($setoran->jenis === 'ziyadah') {
            $msg = $setoran->lulus
                ? 'Hafalan baru LULUS — batas hafalan maju. Lanjutkan murojaah lalu hafalan berikutnya.'
                : 'Hafalan baru BELUM lulus (nilai < ambang) — batas hafalan belum maju, silakan ulangi ayat ini sampai lulus.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => [
                'setoran' => [
                    'id'          => $setoran->id,
                    'jenis'       => $setoran->jenis,
                    'jumlah_ayat' => $setoran->jumlah_ayat,
                    'juz_mulai'   => $setoran->juz_mulai,
                    'juz_selesai' => $setoran->juz_selesai,
                    'lulus'       => $setoran->lulus,
                ],
                'status' => (new TahfidzService())->statusSantri($request->santri_id),
            ],
        ]);
    }

    /** GET /education/tahfidz/penguji-opsi — daftar guru lain untuk penunjukan tasmi'. */
    public function pengujiOpsi(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $guru = TenagaPendidik::aktif()->where('id', '!=', $tp->id)->with('user:id,name')->get()
            ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values();
        return response()->json(['success' => true, 'data' => $guru]);
    }

    /** POST /education/tahfidz/tunjuk-tasmi — pengampu menunjuk penguji. */
    public function tunjukTasmi(Request $request): JsonResponse
    {
        $request->validate([
            'santri_id'  => 'required|exists:santri,id',
            'juz'        => 'required|integer|min:1|max:30',
            'penguji_id' => 'required|exists:tenaga_pendidik,id',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        try {
            (new TasmiService())->tunjukPenguji(
                (int) $request->santri_id, (int) $request->juz, $tp->id,
                (int) $request->penguji_id, $request->user()->id
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'TASMI'], 422);
        }

        // Reminder ke guru penguji yang ditunjuk.
        $penguji = \App\Models\TenagaPendidik::find((int) $request->penguji_id);
        $santri  = \App\Models\Santri::find((int) $request->santri_id);
        \App\Services\NotifikasiService::kirim(
            $penguji?->user_id,
            "Ditunjuk Penguji Tasmi'",
            "Anda ditunjuk menguji tasmi' juz {$request->juz}" . ($santri ? " a.n. {$santri->nama_lengkap}" : '') . '.',
            'tugas_baru',
            ['type' => 'tasmi'],
        );

        return response()->json(['success' => true, 'message' => "Penguji tasmi' juz {$request->juz} ditunjuk."]);
    }

    /** GET /education/tahfidz/tasmi-saya — tugas tasmi' untuk penguji (yang login). */
    public function tasmiSaya(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $data = (new TasmiService())->tasmiSaya($tp->id)->map(fn($t) => [
            'id'       => $t->id,
            'juz'      => $t->juz,
            'santri'   => $t->santri?->nama_lengkap ?? '—',
            'nip'      => $t->santri?->nip,
            'pengampu' => $t->pengampu?->user?->name ?? '—',
        ])->values();
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /education/tahfidz/tasmi/{tugasTasmi}/nilai — penguji menilai tasmi'. */
    public function nilaiTasmi(Request $request, $tugasTasmi): JsonResponse
    {
        // Penilaian tasmi' via 4 rubrik (1-10): Kelancaran, Makhorijul Huruf, Tajwid, Fashohah.
        // Nilai akhir = rata-rata; lulus bila >= 8. Khusus tasmi'.
        $request->validate([
            'nilai_kelancaran'       => 'required|numeric|min:1|max:10',
            'nilai_makhorijul_huruf' => 'required|numeric|min:1|max:10',
            'nilai_tajwid'           => 'required|numeric|min:1|max:10',
            'nilai_fashohah'         => 'required|numeric|min:1|max:10',
            'catatan'                => 'required|string|min:3|max:300',
        ], ['catatan.required' => 'Catatan wajib diisi untuk tracking tasmi.']);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        try {
            $hasil = (new TasmiService())->nilaiTasmi((int) $tugasTasmi, $tp->id, [
                'kelancaran'       => (float) $request->nilai_kelancaran,
                'makhorijul_huruf' => (float) $request->nilai_makhorijul_huruf,
                'tajwid'           => (float) $request->nilai_tajwid,
                'fashohah'         => (float) $request->nilai_fashohah,
            ], $request->catatan);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'TASMI'], 422);
        }
        $msg = $hasil['lulus'] ? "Tasmi' LULUS (rata-rata {$hasil['nilai']}). Juz tuntas." : "Tasmi' belum lulus (rata-rata {$hasil['nilai']}, minimal 8).";
        return response()->json(['success' => true, 'message' => $msg, 'data' => $hasil]);
    }

    /**
     * GET /education/tahfidz/tasmi/{tugasTasmi}/sertifikat
     * Data sertifikat tasmi' (hanya juz yang LULUS) + breakdown 4 rubrik.
     */
    public function sertifikatTasmi(Request $request, $tugasTasmi): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $t = \App\Models\TugasTasmi::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name', 'pengampu.user:id,name'])
            ->findOrFail($tugasTasmi);

        if ($t->status !== 'selesai' || !$t->lulus) {
            return response()->json(['success' => false, 'message' => "Sertifikat hanya untuk tasmi' yang sudah dinilai & LULUS.", 'code' => 'BELUM_LULUS'], 422);
        }

        $nilai = (float) $t->nilai;
        $predikat = $nilai >= 9.5 ? 'Mumtaz (Istimewa)'
            : ($nilai >= 9 ? 'Jayyid Jiddan (Sangat Baik)'
            : ($nilai >= 8 ? 'Jayyid (Baik)' : 'Maqbul'));

        $tgl = $t->updated_at ?? now();
        return response()->json(['success' => true, 'data' => [
            'nomor'    => sprintf('%03d/TASMI-%d/AN-NUR/%s', $t->id, $t->juz, $tgl->format('Y')),
            'santri'   => ['nama' => $t->santri?->nama_lengkap ?? '—', 'nip' => $t->santri?->nip],
            'juz'      => $t->juz,
            'nilai'    => $nilai,
            'predikat' => $predikat,
            'rubrik'   => [
                ['label' => 'Kelancaran',       'nilai' => $t->nilai_kelancaran],
                ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf],
                ['label' => 'Tajwid',           'nilai' => $t->nilai_tajwid],
                ['label' => 'Fashohah',         'nilai' => $t->nilai_fashohah],
            ],
            'rubrik_ada' => $t->nilai_kelancaran !== null,
            'penguji'    => $t->penguji?->user?->name ?? '—',
            'pengampu'   => $t->pengampu?->user?->name ?? '—',
            'catatan'    => $t->catatan,
            'tanggal'    => $tgl->locale('id')->isoFormat('D MMMM YYYY'),
            'lembaga'    => 'Pondok Pesantren An Nur Sidoarjo',
            'program'    => "Tahfizhul Qur'an",
            'alamat'     => 'Jalan KH Ahmad Dahlan, Penatarsewu, Tanggulangin, Sidoarjo, Jawa Timur',
        ]]);
    }

    /** GET /education/tahfidz/santri/{santriId}/status — tracking + riwayat. */
    public function statusSantri(Request $request, $santriId): JsonResponse
    {
        $santri = Santri::find($santriId);
        if (!$santri) {
            return response()->json(['success' => false, 'message' => 'Santri tidak ditemukan.'], 404);
        }

        $riwayat = SetoranTahfidz::where('santri_id', $santriId)
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(20)->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'tanggal'     => $s->tanggal?->toDateString(),
                'jenis'       => $s->jenis,
                'surah_mulai' => $s->surah_mulai,
                'ayat_mulai'  => $s->ayat_mulai,
                'surah_selesai' => $s->surah_selesai,
                'ayat_selesai'  => $s->ayat_selesai,
                'jumlah_ayat' => $s->jumlah_ayat,
                'juz_mulai'   => $s->juz_mulai,
                'nilai'       => $s->nilai,
                'lulus'       => $s->lulus,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'santri'  => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'nip' => $santri->nip],
                'status'  => (new TahfidzService())->statusSantri((int) $santriId),
                'riwayat' => $riwayat,
            ],
        ]);
    }
}
