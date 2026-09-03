<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\Santri;
use App\Models\SettingTahsinMateri;
use App\Models\TahsinPenilaian;
use App\Models\TenagaPendidik;
use App\Models\TugasTasnif;
use App\Services\TahsinService;
use App\Services\TasnifService;
use App\Services\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TahsinApiController extends Controller
{
    /** GET /education/tahsin/jadwal-hari-ini */
    public function jadwalHariIni(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $today    = TimezoneHelper::today();
        $namaHari = TimezoneHelper::namaHariDB($today);

        // SEMUA kelas tahsin guru (lintas hari) — penilaian bisa dibuka kapan saja.
        $jadwal = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])
            ->where('tenaga_pendidik_id', $tp->id)->where('is_aktif', true)
            ->whereHas('mataPelajaran', fn($q) => $q->where('tipe', 'tahsin'))
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->orderBy('jam_mulai')->get();

        $absensi = AbsensiMengajar::whereDate('tanggal', $today)
            ->where('tenaga_pendidik_id', $tp->id)->get()->keyBy('jadwal_mengajar_id');

        $now = TimezoneHelper::now();
        $data = $jadwal->map(function ($j) use ($absensi, $namaHari, $today, $now) {
            $isToday = strtolower($j->hari) === $namaHari;
            $am      = $isToday ? $absensi->get($j->id) : null;
            $jumlahSantri = $j->kelas_id
                ? Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $j->kelas_id))->count() : 0;

            // Window sedang berjalan (untuk pemilihan jadwal aktif: pagi vs sore).
            $dalamJam = false;
            if ($isToday) {
                $jamMulai   = \Carbon\Carbon::parse($today->toDateString() . ' ' . $j->jam_mulai, TimezoneHelper::TZ);
                $jamSelesai = \Carbon\Carbon::parse($today->toDateString() . ' ' . $j->jam_selesai, TimezoneHelper::TZ);
                $dalamJam   = $now->betweenIncluded($jamMulai, $jamSelesai);
            }
            return [
                'jadwal_id'           => $j->id,
                'mata_pelajaran'      => $j->mataPelajaran?->nama ?? 'Tahsin',
                'kelas'               => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                'kelas_id'            => $j->kelas_id,
                'level'               => $j->kelasRel?->level_tahsin,
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
                'dalam_jam'           => $dalamJam,
                // Wajib absen HANYA saat dalam jam sesi & belum absen (bukan sepanjang hari).
                'wajib_absen'         => $dalamJam && $am === null,
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'tanggal' => $today->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'jadwal'  => $data->values(),
        ]]);
    }

    /** POST /education/tahsin/absen — buat sesi (JP) + absensi_santri, terkunci. */
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
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $jadwal = JadwalMengajar::with('mataPelajaran')->findOrFail($request->jadwal_id);
        if ($jadwal->tenaga_pendidik_id !== $tp->id) return response()->json(['success' => false, 'message' => 'Bukan milik Anda.'], 403);
        if (($jadwal->mataPelajaran?->tipe) !== 'tahsin') return response()->json(['success' => false, 'message' => 'Bukan kelas tahsin.'], 422);

        $today = TimezoneHelper::today();
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($today)) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini tidak hari ini.', 'code' => 'WRONG_DAY'], 422);
        }

        $exist = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->whereDate('tanggal', $today)->first();
        if ($exist) {
            return response()->json(['success' => false, 'message' => 'Absen kelas ini sudah terkunci.',
                'code' => 'SUDAH_ABSEN', 'data' => ['absensi_mengajar_id' => $exist->id]], 422);
        }

        // Absen kehadiran WAJIB dalam jam mengajar. Di luar jam (belum absen) → tidak boleh:
        // kehadiran dilimpahkan ke guru piket & guru asli tidak dapat vakasi. Jurnal/nilai tetap kapan saja.
        $jamMulaiC   = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_mulai, TimezoneHelper::TZ);
        $jamSelesaiC = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_selesai, TimezoneHelper::TZ);
        if (!TimezoneHelper::now()->betweenIncluded($jamMulaiC, $jamSelesaiC)) {
            return response()->json([
                'success' => false,
                'message' => 'Absen hanya bisa dalam jam mengajar (' . substr($jadwal->jam_mulai, 0, 5)
                    . '–' . substr($jadwal->jam_selesai, 0, 5) . '). Di luar jam, kehadiran dilimpahkan ke guru piket. '
                    . 'Jurnal/penilaian tetap bisa dilakukan kapan saja.',
                'code'    => 'DILUAR_JAM',
            ], 422);
        }
        $status = 'terlaksana';
        $jp     = $jadwal->jumlah_jp;

        $am = DB::transaction(function () use ($jadwal, $tp, $today, $request, $status, $jp) {
            $am = AbsensiMengajar::create([
                'jadwal_mengajar_id' => $jadwal->id, 'tenaga_pendidik_id' => $tp->id,
                'tanggal' => $today->toDateString(), 'jam_mulai_aktual' => TimezoneHelper::now()->format('H:i:s'),
                'jp_terlaksana' => $jp, 'status' => $status,
                'materi' => $request->deskripsi, 'keterangan' => $request->catatan, 'sudah_buka_jurnal' => true,
            ]);
            foreach ($request->absensi as $row) {
                AbsensiSantri::create(['absensi_mengajar_id' => $am->id, 'santri_id' => $row['santri_id'], 'status' => $row['status']]);
            }
            return $am;
        });

        // Fase A — kirim santri "telat" ke RamahAnak (batch, idempotent). Aman bila gagal.
        app(\App\Services\EducationTelatSync::class)->pushSesi(
            $am,
            $jadwal->mataPelajaran?->nama ?? 'Tahsin',
            "{$request->user()->name} (NIP {$tp->nip})",
        );

        // Notifikasi WA wali per santri (hadir/telat/alfa).
        $pembelajaran = $jadwal->mataPelajaran?->nama ?? 'Tahsin';
        foreach (AbsensiSantri::where('absensi_mengajar_id', $am->id)->get() as $as) {
            app(\App\Services\WaService::class)->absenMengajar(
                $as->santri_id, $as->status, $pembelajaran, $today->toDateString(), $as->id);
        }

        return response()->json(['success' => true,
            'message' => 'Absen kelas tahsin tersimpan & dikunci.',
            'data' => ['absensi_mengajar_id' => $am->id, 'status' => $status]]);
    }

    /** GET /education/tahsin/sesi/{absensiMengajarId}/santri — roster + ringkasan materi. */
    public function rosterSesi(Request $request, $absensiMengajarId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $am = AbsensiMengajar::with(['jadwalMengajar.kelasRel', 'jadwalMengajar.mataPelajaran'])->findOrFail($absensiMengajarId);
        if ($am->tenaga_pendidik_id !== $tp->id) return response()->json(['success' => false, 'message' => 'Bukan milik Anda.'], 403);
        $kelasId = $am->jadwalMengajar?->kelas_id;
        if (!$kelasId) return response()->json(['success' => false, 'message' => 'Kelas belum tersinkron.'], 422);

        $santri = Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelasId))
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'tahsin_level']);

        // total materi aktif per level (cache)
        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');
        $lulus = TahsinPenilaian::whereIn('santri_id', $santri->pluck('id'))->where('lulus', true)
            ->selectRaw('santri_id, level, COUNT(*) as jml')->groupBy('santri_id', 'level')->get();

        $data = $santri->map(function ($s) use ($materiTotal, $lulus) {
            $lv = $s->tahsin_level ?? 1;
            $total = (int) ($materiTotal[$lv] ?? 0);
            $sudah = (int) ($lulus->where('santri_id', $s->id)->where('level', $lv)->first()->jml ?? 0);
            return [
                'santri_id'    => $s->id,
                'nip'          => $s->nip,
                'nama'         => $s->nama_lengkap,
                'level'        => $lv,
                'materi_total' => $total,
                'materi_lulus' => $sudah,
                'level_selesai'=> $total > 0 && $sudah >= $total,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => [
            'absensi_mengajar_id' => $am->id,
            'kelas' => $am->jadwalMengajar?->kelasRel?->nama ?? '—',
            'level' => $am->jadwalMengajar?->kelasRel?->level_tahsin,
            'santri' => $data,
        ]]);
    }

    /**
     * GET /education/tahsin/jadwal/{jadwalId}/roster
     * Roster untuk PENILAIAN kapan saja (jurnal nilai selalu terbuka).
     * Hari terjadwal (Senin–Jumat) → wajib_absen=true (absen dulu); di luar → tanpa sesi.
     */
    public function rosterJadwal(Request $request, $jadwalId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $jadwal = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])->findOrFail($jadwalId);
        if ($jadwal->tenaga_pendidik_id !== $tp->id) return response()->json(['success' => false, 'message' => 'Bukan milik Anda.'], 403);
        if (($jadwal->mataPelajaran?->tipe) !== 'tahsin') return response()->json(['success' => false, 'message' => 'Bukan kelas tahsin.'], 422);
        if (!$jadwal->kelas_id) return response()->json(['success' => false, 'message' => 'Kelas belum tersinkron.', 'code' => 'KELAS_BELUM_SINKRON'], 422);

        $today   = TimezoneHelper::today();
        $now     = TimezoneHelper::now();
        $isToday = strtolower($jadwal->hari) === TimezoneHelper::namaHariDB($today);
        $am = $isToday
            ? AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->whereDate('tanggal', $today)->first()
            : null;

        $jamMulai   = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_mulai, TimezoneHelper::TZ);
        $jamSelesai = \Carbon\Carbon::parse($today->toDateString() . ' ' . $jadwal->jam_selesai, TimezoneHelper::TZ);
        $dalamJam   = $isToday && $now->betweenIncluded($jamMulai, $jamSelesai);

        return response()->json(['success' => true, 'data' => array_merge([
            'absensi_mengajar_id' => $am?->id,
            'jadwal_id'           => $jadwal->id,
            'kelas'               => $jadwal->kelasRel?->nama ?? $jadwal->kelas ?? '—',
            'level'               => $jadwal->kelasRel?->level_tahsin,
            'hari'                => $jadwal->hari,
            'is_today'            => $isToday,
            'jam_mulai'           => $jadwal->jam_mulai,
            'jam_selesai'         => $jadwal->jam_selesai,
            'jumlah_jp'           => $jadwal->jumlah_jp,
            'sudah_absen'         => $am !== null,
            'dalam_jam'           => $dalamJam,
            // Wajib absen HANYA saat dalam jam sesi & belum absen.
            'wajib_absen'         => $dalamJam && $am === null,
        ], $this->rosterPayload($jadwal->kelas_id))]);
    }

    /** Bangun payload roster tahsin (total_santri + santri dgn ringkasan materi). */
    private function rosterPayload(int $kelasId): array
    {
        $santri = Santri::aktif()->whereHas('kelas', fn($q) => $q->where('kelas.id', $kelasId))
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'tahsin_level']);

        $materiTotal = SettingTahsinMateri::where('is_aktif', true)
            ->selectRaw('level, COUNT(*) as jml')->groupBy('level')->pluck('jml', 'level');
        $lulus = TahsinPenilaian::whereIn('santri_id', $santri->pluck('id'))->where('lulus', true)
            ->selectRaw('santri_id, level, COUNT(*) as jml')->groupBy('santri_id', 'level')->get();

        $data = $santri->map(function ($s) use ($materiTotal, $lulus) {
            $lv = $s->tahsin_level ?? 1;
            $total = (int) ($materiTotal[$lv] ?? 0);
            $sudah = (int) ($lulus->where('santri_id', $s->id)->where('level', $lv)->first()->jml ?? 0);
            return [
                'santri_id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap, 'level' => $lv,
                'materi_total' => $total, 'materi_lulus' => $sudah,
                'level_selesai' => $total > 0 && $sudah >= $total,
            ];
        })->values();

        return ['total_santri' => $data->count(), 'santri' => $data];
    }

    /** GET /education/tahsin/santri/{santriId}/materi — materi level santri + status nilai. */
    public function materiSantri(Request $request, $santriId): JsonResponse
    {
        $santri = Santri::find($santriId);
        if (!$santri) return response()->json(['success' => false, 'message' => 'Santri tidak ditemukan.'], 404);
        $level = $santri->tahsin_level ?? 1;

        $svc = new TahsinService();
        return response()->json(['success' => true, 'data' => [
            'santri' => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'level' => $level],
            'materi' => $svc->materiSantri((int) $santriId, $level),
            'materi_tambahan' => $svc->materiTambahanSantri((int) $santriId),
            'level_selesai' => $svc->levelSelesai((int) $santriId, $level),
        ]]);
    }

    /** POST /education/tahsin/materi-tambahan — catat materi pelengkap (tak untuk naik level). */
    public function materiTambahan(Request $request): JsonResponse
    {
        $request->validate([
            'absensi_mengajar_id' => 'nullable|exists:absensi_mengajar,id',
            'santri_id'           => 'required|exists:santri,id',
            'nama_materi'         => 'required|string|min:2|max:150',
            'nilai'               => 'nullable|numeric|min:1|max:10',
            'catatan'             => 'nullable|string|max:300',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        (new TahsinService())->catatMateriTambahan([
            'absensi_mengajar_id' => $request->absensi_mengajar_id,
            'santri_id'           => $request->santri_id,
            'tenaga_pendidik_id'  => $tp->id,
            'nama_materi'         => $request->nama_materi,
            'nilai'               => $request->nilai,
            'catatan'             => $request->catatan,
        ]);

        return response()->json(['success' => true, 'message' => 'Materi tambahan tersimpan.']);
    }

    /** POST /education/tahsin/nilai — nilai satu materi (wajib). */
    public function nilai(Request $request): JsonResponse
    {
        $request->validate([
            'absensi_mengajar_id' => 'nullable|exists:absensi_mengajar,id',
            'santri_id'           => 'required|exists:santri,id',
            'materi_id'           => 'required|exists:setting_tahsin_materi,id',
            'nilai'               => 'required|numeric|min:1|max:10',
            // Catatan WAJIB tiap penilaian → tracking pembelajaran untuk evaluasi.
            'catatan'             => 'required|string|min:3|max:300',
        ], ['catatan.required' => 'Catatan wajib diisi untuk tracking pembelajaran.']);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $p = (new TahsinService())->nilaiMateri([
            'absensi_mengajar_id' => $request->absensi_mengajar_id,
            'santri_id'           => $request->santri_id,
            'tenaga_pendidik_id'  => $tp->id,
            'materi_id'           => $request->materi_id,
            'nilai'               => $request->nilai,
            'catatan'             => $request->catatan,
        ]);

        return response()->json(['success' => true, 'message' => 'Nilai materi tersimpan.',
            'data' => ['nilai' => $p->nilai, 'lulus' => $p->lulus]]);
    }

    /** POST /education/tahsin/santri/{santriId}/naik-level — naik level (override opsional). */
    public function naikLevel(Request $request, $santriId): JsonResponse
    {
        $request->validate(['override' => 'nullable|boolean']);
        if (!Santri::find($santriId)) return response()->json(['success' => false, 'message' => 'Santri tidak ditemukan.'], 404);

        try {
            $hasil = (new TahsinService())->naikLevel((int) $santriId, (bool) $request->boolean('override'));
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'BELUM_LULUS'], 422);
        }

        $msg = ($hasil['tahsin_selesai'] ?? false)
            ? 'Santri lulus Persiapan Tahfidz → pindah ke program Tahfidz.'
            : 'Santri naik ke ' . TahsinService::levelLabel((int) $hasil['level']) . '.';
        return response()->json(['success' => true, 'message' => $msg, 'data' => $hasil]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TASNIF — ujian kenaikan level (analog Tasmi'). Fitur terpisah.
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /education/tahsin/penguji-opsi — daftar guru penguji (semua guru aktif). */
    public function pengujiOpsi(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        // Tasnif boleh diuji guru mengampu sendiri → sertakan semua guru aktif.
        $guru = TenagaPendidik::aktif()->with('user:id,name')->get()
            ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—', 'saya' => $g->id === $tp->id])->values();
        return response()->json(['success' => true, 'data' => $guru]);
    }

    /** POST /education/tahsin/tunjuk-tasnif — pengampu menunjuk penguji ujian level. */
    public function tunjukTasnif(Request $request): JsonResponse
    {
        $request->validate([
            'santri_id'  => 'required|exists:santri,id',
            'level'      => 'required|integer|min:1|max:6',
            'penguji_id' => 'required|exists:tenaga_pendidik,id',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        try {
            (new TasnifService())->tunjukPenguji(
                (int) $request->santri_id, (int) $request->level, $tp->id,
                (int) $request->penguji_id, $request->user()->id
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'TASNIF'], 422);
        }

        // Reminder ke guru penguji yang ditunjuk.
        $penguji = \App\Models\TenagaPendidik::find((int) $request->penguji_id);
        $santri  = \App\Models\Santri::find((int) $request->santri_id);
        \App\Services\NotifikasiService::kirim(
            $penguji?->user_id,
            'Ditunjuk Penguji Tasnif',
            'Anda ditunjuk menguji tasnif ' . TahsinService::levelLabel((int) $request->level)
                . ($santri ? " a.n. {$santri->nama_lengkap}" : '') . '.',
            'tugas_baru',
            ['type' => 'tasnif'],
        );

        return response()->json(['success' => true, 'message' => 'Penguji tasnif ' . TahsinService::levelLabel((int) $request->level) . ' ditunjuk.']);
    }

    /** GET /education/tahsin/tasnif-saya — tasnif yang ditugaskan ke saya (penguji). */
    public function tasnifSaya(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $data = (new TasnifService())->tasnifSaya($tp->id)->map(fn($t) => [
            'id'          => $t->id,
            'level'       => $t->level,
            'level_label' => TahsinService::levelLabel($t->level),
            'santri'      => $t->santri?->nama_lengkap ?? '—',
            'nip'         => $t->santri?->nip,
            'pengampu'    => $t->pengampu?->user?->name ?? '—',
        ])->values();
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /education/tahsin/tasnif/{tugasTasnif}/nilai — penilaian 4 rubrik. */
    public function nilaiTasnif(Request $request, $tugasTasnif): JsonResponse
    {
        $request->validate([
            'nilai_pemahaman_materi' => 'required|numeric|min:1|max:10',
            'nilai_kelancaran'       => 'required|numeric|min:1|max:10',
            'nilai_fashohah'         => 'required|numeric|min:1|max:10',
            'nilai_makhorijul_huruf' => 'required|numeric|min:1|max:10',
            'catatan'                => 'required|string|min:3|max:300',
        ], ['catatan.required' => 'Catatan wajib diisi untuk tracking tasnif.']);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        try {
            $hasil = (new TasnifService())->nilaiTasnif((int) $tugasTasnif, $tp->id, [
                'pemahaman_materi' => (float) $request->nilai_pemahaman_materi,
                'kelancaran'       => (float) $request->nilai_kelancaran,
                'fashohah'         => (float) $request->nilai_fashohah,
                'makhorijul_huruf' => (float) $request->nilai_makhorijul_huruf,
            ], $request->catatan);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'TASNIF'], 422);
        }

        $msg = $hasil['lulus']
            ? "Tasnif LULUS (rata-rata {$hasil['nilai']}). Santri naik level."
            : "Tasnif belum lulus (rata-rata {$hasil['nilai']}, minimal 8).";
        return response()->json(['success' => true, 'message' => $msg, 'data' => $hasil]);
    }

    /** GET /education/tahsin/tasnif/{tugasTasnif}/sertifikat — data sertifikat (hanya LULUS). */
    public function sertifikatTasnif(Request $request, $tugasTasnif): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $t = TugasTasnif::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name', 'pengampu.user:id,name'])
            ->findOrFail($tugasTasnif);
        if ($t->status !== 'selesai' || !$t->lulus) {
            return response()->json(['success' => false, 'message' => 'Sertifikat hanya untuk tasnif yang sudah dinilai & LULUS.', 'code' => 'BELUM_LULUS'], 422);
        }

        $nilai = (float) $t->nilai;
        $predikat = $nilai >= 9.5 ? 'Mumtaz (Istimewa)'
            : ($nilai >= 9 ? 'Jayyid Jiddan (Sangat Baik)'
            : ($nilai >= 8 ? 'Jayyid (Baik)' : 'Maqbul'));
        $tgl = $t->updated_at ?? now();

        return response()->json(['success' => true, 'data' => [
            'nomor'    => sprintf('%03d/TASNIF-L%d/AN-NUR/%s', $t->id, $t->level, $tgl->format('Y')),
            'santri'   => ['nama' => $t->santri?->nama_lengkap ?? '—', 'nip' => $t->santri?->nip],
            'level'       => $t->level,
            'level_label' => TahsinService::levelLabel($t->level),
            'nilai'    => $nilai,
            'predikat' => $predikat,
            'rubrik'   => [
                ['label' => 'Pemahaman Materi', 'nilai' => $t->nilai_pemahaman_materi],
                ['label' => 'Kelancaran',       'nilai' => $t->nilai_kelancaran],
                ['label' => 'Fashohah',         'nilai' => $t->nilai_fashohah],
                ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf],
            ],
            'penguji'  => $t->penguji?->user?->name ?? '—',
            'pengampu' => $t->pengampu?->user?->name ?? '—',
            'catatan'  => $t->catatan,
            'tanggal'  => $tgl->locale('id')->isoFormat('D MMMM YYYY'),
            'lembaga'  => 'Pondok Pesantren An Nur Sidoarjo',
            'program'  => 'Tahsinul Qur\'an',
            'alamat'   => 'Jalan KH Ahmad Dahlan, Penatarsewu, Tanggulangin, Sidoarjo, Jawa Timur',
        ]]);
    }
}
