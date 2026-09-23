<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\TenagaPendidik;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guru pengganti (inval) saat izin — SATU sumber aturan untuk reguler, tahfidz,
 * dan tahsin.
 *
 * Alur:
 *  1. Guru izin (PengajuanIzin disetujui yg mencakup tanggal) menunjuk pengganti
 *     → baris absensi_mengajar (satu per jadwal+tanggal) di-set status='pengganti',
 *     tenaga_pendidik_id tetap guru asli (jejak), digantikan_oleh=pengganti,
 *     jp_terlaksana=0 (belum diajar). Langsung sah.
 *  2. Pengganti mengisi absen + jurnal + absensi santri HANYA di jam mengajar
 *     (jam_mulai s/d jam_selesai) → jp_terlaksana penuh, dibayar ke pengganti.
 *  3. Tidak diisi sampai batas → mengajar:tandai-tidak-terlaksana mengubahnya ke
 *     'tidak_terlaksana' (digantikan_oleh tetap) dan DIHITUNG ke kinerja pengganti.
 *  4. Izin TANPA pengganti → status 'izin', JP hangus, netral di kinerja.
 *
 * Kewenangan inval (keputusan 17 Sep 2026) — inval hampir selalu bukan pengampu,
 * karena seluruh kelas tahfidz dan tahsin berjalan serentak:
 *  - reguler : absen, jurnal, absensi santri
 *  - tahfidz : absen, jurnal, absensi santri, setoran MUROJAAH saja
 *              (hafalan baru memajukan batas hafalan; tasmi' tetap pengampu)
 *  - tahsin  : absen, jurnal, absensi santri, materi tambahan (catatan)
 *              (penilaian kelulusan materi, tasnif, naik level tetap pengampu)
 */
class PenggantiMengajarService
{
    /** Jenis setoran tahfidz yang boleh dicatat guru inval. */
    public const JENIS_SETORAN_INVAL = ['murojaah_wajib', 'murojaah_tambahan'];

    /** Guru izin menunjuk pengganti untuk satu jadwal pada tanggal tertentu. */
    public function tunjukPengganti(
        int $jadwalId, int $guruTpId, int $penggantiTpId, ?Carbon $tanggal, ?string $keterangan
    ): AbsensiMengajar {
        $tanggal ??= TimezoneHelper::today();
        $jadwal = JadwalMengajar::findOrFail($jadwalId);

        if ($jadwal->tenaga_pendidik_id !== $guruTpId) {
            throw new \DomainException('Jadwal ini bukan milik Anda.');
        }
        if ($penggantiTpId === $guruTpId) {
            throw new \DomainException('Pengganti harus guru lain (bukan diri sendiri).');
        }

        // Hari jadwal harus cocok dengan tanggal target.
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($tanggal)) {
            throw new \DomainException('Jadwal ini tidak berlangsung pada tanggal tersebut.');
        }

        // Inval hanya bisa mengisi selama jam mengajar. Menunjuk pengganti untuk sesi
        // yang jamnya sudah berakhir menghasilkan penugasan yang mustahil dikerjakan.
        $hariIni = TimezoneHelper::today();
        if ($tanggal->lt($hariIni->copy()->startOfDay())) {
            throw new \DomainException('Tidak bisa menunjuk pengganti untuk tanggal yang sudah lewat.');
        }
        if ($tanggal->isSameDay($hariIni)
            && TimezoneHelper::now()->gt($this->jamSesi($tanggal->toDateString(), (string) $jadwal->jam_selesai))) {
            throw new \DomainException(
                'Sesi ini sudah berakhir pukul ' . substr((string) $jadwal->jam_selesai, 0, 5)
                . '. Pengganti hanya bisa mengisi selama jam mengajar, jadi tidak bisa ditunjuk lagi.'
            );
        }

        // Guru harus punya izin disetujui yang mencakup tanggal target.
        $izin = PengajuanIzin::where('tenaga_pendidik_id', $guruTpId)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal->toDateString())
            ->where('tanggal_selesai', '>=', $tanggal->toDateString())
            ->with('jenisPengajuan')->first();
        if (!$izin) {
            throw new \DomainException('Anda belum punya izin yang disetujui pada tanggal tersebut.');
        }

        $pengganti = TenagaPendidik::where('id', $penggantiTpId)->where('is_aktif', true)->first();
        if (!$pengganti) {
            throw new \DomainException('Guru pengganti tidak ditemukan / tidak aktif.');
        }

        // Pengganti harus benar-benar kosong di jam itu — aturan yang sama dengan
        // daftar calon, agar pilihan yang tampil tidak pernah ditolak di sini.
        $kelayakan = $this->kelayakanInval($penggantiTpId, $jadwal, $tanggal);
        if ($kelayakan['alasan']) {
            throw new \DomainException($kelayakan['alasan']);
        }

        // Tidak boleh menimpa sesi yang sudah benar-benar terlaksana / libur.
        $existing = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal)->first();
        if ($existing && in_array($existing->status, ['terlaksana', 'hadir', 'libur', 'tidak_terlaksana'])) {
            throw new \DomainException('Sesi ini sudah tercatat "' . $existing->status . '", tidak bisa ditunjuk pengganti.');
        }
        if ($existing && $existing->status === 'pengganti' && !is_null($existing->jam_selesai_aktual)) {
            throw new \DomainException('Pengganti sudah mengajar sesi ini.');
        }

        $absen = AbsensiMengajar::updateOrCreate(
            ['jadwal_mengajar_id' => $jadwal->id, 'tanggal' => $tanggal->toDateString()],
            [
                'tenaga_pendidik_id' => $guruTpId,        // jejak guru asli
                'digantikan_oleh'    => $penggantiTpId,   // pengganti yang dibayar
                'status'             => 'pengganti',
                'jp_terlaksana'      => 0,                // belum diajar → belum dibayar
                'materi'             => null,
                'foto_mengajar'      => null,
                'sudah_buka_jurnal'  => false,
                'keterangan'         => 'Pengganti izin (' . ($izin->jenisPengajuan?->nama ?? 'Izin') . ')'
                    . ($kelayakan['gabung'] ? ' — digabung dengan ' . $kelayakan['gabung'] : '')
                    . ($keterangan ? ' — ' . $keterangan : ''),
            ]
        );

        // Notifikasi ke guru pengganti (menghormati toggle 'pengganti.ditunjuk').
        if ($pengganti->user) {
            $jadwal->loadMissing(['mataPelajaran', 'kelasRel']);
            $tipe = $jadwal->mataPelajaran?->tipe ?? 'reguler';
            \App\Services\NotifikasiService::event('pengganti.ditunjuk', [
                'user'  => $pengganti->user,
                'judul' => 'Anda Ditunjuk Guru Pengganti',
                'pesan' => 'Menggantikan ' . ($jadwal->mataPelajaran?->nama ?? '')
                    . ' ' . ($jadwal->kelasRel?->nama ?? $jadwal->kelas ?? '') . ' pada ' . $tanggal->format('d/m/Y')
                    . ' (' . substr((string) $jadwal->jam_mulai, 0, 5) . '–' . substr((string) $jadwal->jam_selesai, 0, 5) . ').'
                    . ($kelayakan['gabung'] ? ' Digabung dengan kelas Anda: ' . $kelayakan['gabung'] . ' — absen tiap kelas terpisah.' : '')
                    . ' Absen & jurnal hanya bisa diisi selama jam mengajar.',
                'tipe'  => 'tugas_baru',
                'data'  => ['type' => 'kegiatan', 'route' => '/kelas-pengganti', 'tipe_kelas' => $tipe],
                'dedup' => "pengganti-{$jadwal->id}-" . $tanggal->toDateString(),
            ]);
        }

        // Kelas digabung = dua rombongan belajar di satu ruang. Piket hari itu
        // dikabari agar ada yang mengawasi, bukan hanya guru yang merangkap.
        if ($kelayakan['gabung']) {
            $this->kabariPiketGabung($jadwal, $tanggal, $pengganti, $kelayakan['gabung']);
        }

        return $absen;
    }

    /** Kabari guru piket bahwa dua kelas berjalan digabung pada jam tersebut. */
    private function kabariPiketGabung(JadwalMengajar $jadwal, Carbon $tanggal, TenagaPendidik $pengganti, string $gabung): void
    {
        $piket = \App\Models\PiketJadwal::whereDate('tanggal', $tanggal->toDateString())
            ->with('tenagaPendidik.user:id,name')->get()
            ->map(fn ($p) => $p->tenagaPendidik?->user)->filter()->unique('id')->values();
        if ($piket->isEmpty()) return;

        $jadwal->loadMissing(['mataPelajaran:id,nama', 'kelasRel:id,nama']);
        $kelas = $jadwal->kelasRel?->nama ?? $jadwal->kelas ?? 'kelas';

        \App\Services\NotifikasiService::event('pengganti.ditunjuk', [
            'judul'     => 'Dua kelas digabung di satu jam',
            'pesan'     => ($pengganti->user?->name ?? 'Guru pengganti') . ' memegang ' . $kelas
                . ' sekaligus ' . $gabung . ' pukul ' . substr((string) $jadwal->jam_mulai, 0, 5)
                . '–' . substr((string) $jadwal->jam_selesai, 0, 5)
                . '. Absen & jurnal tiap kelas tetap terpisah — mohon dipantau.',
            'tipe'      => 'tugas_update',
            'prioritas' => 'tinggi',
            'data'      => ['route' => '/piket'],
            'dedup'     => 'gabung-piket-' . $jadwal->id . '-' . $tanggal->toDateString(),
        ], $piket->all());
    }

    /**
     * Tipe kelas yang boleh DIGABUNG dalam satu jam oleh satu guru.
     * Sejak 23 Sep 2026 semua tipe boleh (kekurangan tenaga pendidik), dengan
     * penjaga jenjang & jenis kelamin di kelayakanInval.
     */
    public const TIPE_BISA_GABUNG = ['tahfidz', 'tahsin', 'reguler'];

    /**
     * Tipe yang gabungnya SELALU ditawarkan karena kekurangan calonnya struktural
     * (semua kelas Quran berjalan di 8 slot yang sama). Tipe lain — reguler —
     * hanya menawarkan gabung bila tidak ada satu pun calon yang jamnya kosong.
     */
    public const TIPE_GABUNG_UTAMA = ['tahfidz', 'tahsin'];

    /** Jumlah kelas maksimal yang dipegang satu guru pada jam yang sama. */
    public const MAKS_KELAS_BERSAMAAN = 2;

    /**
     * Alasan seorang guru TIDAK bisa menginval sesi ini, atau null bila bisa.
     * Dipakai daftar calon dan penunjukan, supaya keduanya tak pernah berbeda.
     */
    public function alasanTidakBisaInval(int $tpId, JadwalMengajar $jadwal, Carbon $tanggal): ?string
    {
        return $this->kelayakanInval($tpId, $jadwal, $tanggal)['alasan'];
    }

    /**
     * Kelayakan satu guru menginval sesi ini.
     *
     * GABUNG KELAS (keputusan 17 Sep 2026): seluruh kelas tahfidz & tahsin berjalan
     * di 8 slot yang sama, dan guru yang ada di asrama pada jam itu adalah guru yang
     * sedang mengajar kelasnya sendiri. Data Tahfidz Putra 5 Kamis 18:00: 29 calon
     * "kosong", hanya 1 mukim dan 0 laki-laki-mukim — tak ada calon yang realistis,
     * dan belum pernah sekali pun terjadi inval tahfidz/tahsin. Maka guru tahfidz/
     * tahsin BOLEH memegang kelas Quran lain pada jam yang sama, dengan syarat:
     *   - kedua kelas bertipe tahfidz/tahsin (kelas reguler tidak bisa dirangkap);
     *   - total kelas bersamaan ≤ MAKS_KELAS_BERSAMAAN;
     *   - kelas putra & putri tidak digabung (santrinya belajar bersama).
     *
     * @return array{alasan: ?string, gabung: ?string}  gabung = nama kelas yang dirangkap
     */
    public function kelayakanInval(int $tpId, JadwalMengajar $jadwal, Carbon $tanggal): array
    {
        $tgl  = $tanggal->toDateString();
        $tolak = fn (string $a) => ['alasan' => $a, 'gabung' => null, 'peringatan' => null];

        $izin = PengajuanIzin::where('tenaga_pendidik_id', $tpId)->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tgl)->where('tanggal_selesai', '>=', $tgl)->exists();
        if ($izin) return $tolak('Guru pengganti sedang izin pada tanggal tersebut.');

        $jadwal->loadMissing(['mataPelajaran:id,tipe', 'kelasRel:id,nama,tingkat']);
        $bolehGabung = in_array($jadwal->mataPelajaran?->tipe, self::TIPE_BISA_GABUNG, true);

        // Kelas yang sudah dipegang guru ini pada jam yang sama: jadwal sendiri + inval lain.
        $sendiri = JadwalMengajar::with(['mataPelajaran:id,tipe', 'kelasRel:id,nama,tingkat'])
            ->where('tenaga_pendidik_id', $tpId)
            ->where('hari', $jadwal->hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->where('jam_mulai', '<', $jadwal->jam_selesai)
            ->where('jam_selesai', '>', $jadwal->jam_mulai)
            ->get();
        // Jadwal sendiri yang hari itu dialihkan ke pengganti lain tidak dipegangnya.
        $dialihkan = AbsensiMengajar::whereDate('tanggal', $tgl)->whereIn('jadwal_mengajar_id', $sendiri->pluck('id'))
            ->whereNotNull('digantikan_oleh')->pluck('jadwal_mengajar_id')->flip();
        $sendiri = $sendiri->reject(fn ($j) => $dialihkan->has($j->id));

        $invalLain = AbsensiMengajar::with(['jadwalMengajar.mataPelajaran:id,tipe', 'jadwalMengajar.kelasRel:id,nama,tingkat'])
            ->where('digantikan_oleh', $tpId)->whereDate('tanggal', $tgl)
            ->whereIn('status', ['pengganti', 'tidak_terlaksana'])
            ->where('jadwal_mengajar_id', '!=', $jadwal->id)
            ->whereHas('jadwalMengajar', fn ($q) => $q
                ->where('jam_mulai', '<', $jadwal->jam_selesai)
                ->where('jam_selesai', '>', $jadwal->jam_mulai))
            ->get()->map(fn ($a) => $a->jadwalMengajar);

        $dipegang = $sendiri->concat($invalLain)->filter()->values();
        if ($dipegang->isEmpty()) return ['alasan' => null, 'gabung' => null, 'peringatan' => null];

        // Ada kelas pada jam yang sama → hanya sah sebagai GABUNG kelas.
        if (!$bolehGabung) {
            return $tolak('Guru pengganti sudah mengajar kelas lain di jam yang sama.');
        }
        if ($dipegang->contains(fn ($j) => !in_array($j->mataPelajaran?->tipe, self::TIPE_BISA_GABUNG, true))) {
            return $tolak('Guru pengganti memegang kelas yang tidak bisa dirangkap di jam yang sama.');
        }
        if ($dipegang->count() + 1 > self::MAKS_KELAS_BERSAMAAN) {
            return $tolak('Guru pengganti sudah memegang ' . $dipegang->count() . ' kelas di jam yang sama.');
        }

        // Dua kelas belajar dalam satu ruang → JENIS KELAMIN wajib sama. Kelas campur
        // (X, XI, XII) hanya boleh digabung dengan kelas campur: tanpa aturan ini ia
        // lolos diam-diam karena jenis kelaminnya "tidak bisa dipastikan".
        //
        // Jenjang TIDAK dijadikan syarat, melainkan peringatan. Data 23 Sep 2026:
        // dari 72 pasang kelas reguler yang berjalan bersamaan, 18 pasang berjenis
        // kelamin sama tapi NOL yang sekaligus setingkat (tiap tingkat hanya punya
        // satu kelas putra & satu kelas putri). Mensyaratkan jenjang sama = fitur ini
        // tidak akan pernah bisa dipakai di kelas reguler.
        $jkTarget      = $this->jenisKelaminKelas($jadwal->kelas_id);
        $tingkatTarget = $jadwal->kelasRel?->tingkat;
        $bedaJenjang   = [];
        foreach ($dipegang as $j) {
            $jk   = $this->jenisKelaminKelas($j->kelas_id);
            $nama = $j->kelasRel?->nama ?? 'kelas lain';
            // null = kelas belum punya santri → tak ada yang bisa salah duduk, biarkan.
            if ($jkTarget !== null && $jk !== null && $jkTarget !== $jk) {
                return $tolak($jkTarget === self::KELAS_CAMPUR || $jk === self::KELAS_CAMPUR
                    ? "Kelas campur putra-putri tidak bisa digabung dengan kelas terpisah ({$nama})."
                    : "Kelas putra dan putri tidak bisa digabung ({$nama}).");
            }
            $tingkat = $j->kelasRel?->tingkat;
            if ($tingkatTarget && $tingkat && (string) $tingkatTarget !== (string) $tingkat) {
                $bedaJenjang[] = "{$nama} (tingkat {$tingkat})";
            }
        }

        return [
            'alasan'     => null,
            'gabung'     => $dipegang->map(fn ($j) => $j->kelasRel?->nama ?? $j->kelas)->implode(', '),
            'peringatan' => $bedaJenjang
                ? 'Beda jenjang dengan ' . implode(', ', $bedaJenjang) . ' — materi tiap kelas tetap terpisah.'
                : null,
        ];
    }

    /** Kelas yang santrinya memang campur putra-putri (mis. X, XI, XII). */
    public const KELAS_CAMPUR = 'CAMPUR';

    /**
     * Jenis kelamin kelas: dari nama (Putra/Putri), bila tidak ada dari mayoritas
     * ≥80% santri aktif (mis. "Persiapan Tahfidz 3").
     *
     * Tiga kemungkinan, dan bedanya penting untuk aturan gabung kelas:
     *   'L' / 'P'      → kelas satu jenis kelamin
     *   KELAS_CAMPUR   → ada santrinya, tapi campur (tak ada mayoritas 80%)
     *   null           → belum bisa dinilai (kelas belum punya santri aktif)
     */
    public function jenisKelaminKelas(?int $kelasId): ?string
    {
        if (!$kelasId) return null;
        static $memo = [];
        if (array_key_exists($kelasId, $memo)) return $memo[$kelasId];

        $nama = mb_strtolower((string) DB::table('kelas')->where('id', $kelasId)->value('nama'));
        if (str_contains($nama, 'putri')) return $memo[$kelasId] = 'P';
        if (str_contains($nama, 'putra')) return $memo[$kelasId] = 'L';

        $jk = DB::table('kelas_santri as ks')->join('santri as s', 's.id', '=', 'ks.santri_id')
            ->where('ks.kelas_id', $kelasId)->where('ks.is_aktif', true)
            ->selectRaw('s.jenis_kelamin jk, COUNT(*) n')->groupBy('s.jenis_kelamin')->pluck('n', 'jk');
        $total = $jk->sum();
        if (!$total) return $memo[$kelasId] = null;   // belum ada santri → tak bisa dinilai
        $atas = $jk->sortDesc()->keys()->first();
        return $memo[$kelasId] = ($jk[$atas] / $total >= 0.8 ? $atas : self::KELAS_CAMPUR);
    }

    /**
     * Calon pengganti untuk sesi ini, urut dari yang paling layak:
     * sesama jenis kelamin dengan kelas → mukim (ada di asrama) → guru yang kosong
     * → gabung kelas dengan program yang sama.
     */
    public function calonPengganti(JadwalMengajar $jadwal, Carbon $tanggal, int $guruTpId, bool $sertakanGabung = false): Collection
    {
        $jadwal->loadMissing('mataPelajaran:id,tipe');
        $jkKelas = $this->jenisKelaminKelas($jadwal->kelas_id);
        $tipe    = $jadwal->mataPelajaran?->tipe;

        $calon = TenagaPendidik::aktif()->where('id', '!=', $guruTpId)->with('user:id,name')->get()
            ->filter(fn ($g) => $g->user)
            ->map(function ($g) use ($jadwal, $tanggal, $jkKelas, $tipe) {
                $k = $this->kelayakanInval($g->id, $jadwal, $tanggal);
                if ($k['alasan'] !== null) return null;

                // Kelas campur / belum ada santri → tak ada preferensi jenis kelamin guru.
                $sejenis = !$jkKelas || $jkKelas === self::KELAS_CAMPUR || $g->jenis_kelamin === $jkKelas;
                $programSama = $k['gabung'] && JadwalMengajar::where('tenaga_pendidik_id', $g->id)->where('is_aktif', true)
                    ->whereHas('mataPelajaran', fn ($q) => $q->where('tipe', $tipe))->exists();

                return [
                    'id'         => $g->id,
                    'nama'       => $g->user->name,
                    'gabung'     => $k['gabung'],
                    'peringatan' => $k['peringatan'] ?? null,
                    'mukim'   => (bool) $g->is_mukim,
                    'sejenis' => $sejenis,
                    // Kunci urut: angka kecil = lebih layak.
                    '_urut'   => sprintf('%d%d%d%d-%s', $sejenis ? 0 : 1, $g->is_mukim ? 0 : 1,
                        $k['gabung'] ? 1 : 0, $programSama ? 0 : 1, $g->user->name),
                ];
            })
            ->filter()->sortBy('_urut')
            ->map(fn ($c) => collect($c)->except('_urut')->all())
            ->values();

        // Calon yang jamnya KOSONG selalu didahulukan. Untuk kelas reguler, guru
        // yang harus merangkap tidak ikut tampil secara bawaan bila masih ada calon
        // kosong — data 23 Sep 2026: tiap sesi reguler punya 3–13 calon kosong,
        // jadi merangkap adalah pengecualian, bukan pilihan biasa. Penunjuk tetap
        // bisa membukanya sendiri lewat $sertakanGabung (tautan di aplikasi guru).
        // Tahfidz & tahsin selalu menampilkannya: di sana kekurangannya struktural.
        if (!$sertakanGabung && !in_array($tipe, self::TIPE_GABUNG_UTAMA, true)) {
            $kosong = $calon->whereNull('gabung')->values();
            if ($kosong->isNotEmpty()) return $kosong;
        }

        return $calon;
    }

    /**
     * Calon yang hanya bisa mengisi dengan MERANGKAP kelasnya sendiri — daftar
     * tambahan di balik tautan "tampilkan juga guru yang merangkap".
     * Kosong bila memang tak ada, atau bila mereka sudah tampil di daftar utama.
     */
    public function calonMerangkap(JadwalMengajar $jadwal, Carbon $tanggal, int $guruTpId): Collection
    {
        $jadwal->loadMissing('mataPelajaran:id,tipe');
        if (in_array($jadwal->mataPelajaran?->tipe, self::TIPE_GABUNG_UTAMA, true)) {
            return collect();   // sudah menyatu di daftar utama
        }

        $utama = $this->calonPengganti($jadwal, $tanggal, $guruTpId)->pluck('id')->flip();

        return $this->calonPengganti($jadwal, $tanggal, $guruTpId, true)
            ->filter(fn ($c) => $c['gabung'] !== null && !$utama->has($c['id']))
            ->values();
    }

    /** Tugas inval AKTIF milik guru ini untuk satu jadwal hari ini (belum/sudah diisi). */
    public function tugasInvalHariIni(int $jadwalId, int $tpId): ?AbsensiMengajar
    {
        return AbsensiMengajar::with(['jadwalMengajar.mataPelajaran', 'tenagaPendidik.user:id,name'])
            ->where('jadwal_mengajar_id', $jadwalId)
            ->whereDate('tanggal', TimezoneHelper::today())
            ->where('digantikan_oleh', $tpId)
            ->where('status', 'pengganti')
            ->first();
    }

    /** Apakah catatan sesi ini adalah tugas inval aktif milik guru ini. */
    public function milikInval(AbsensiMengajar $absensi, int $tpId): bool
    {
        return (int) $absensi->digantikan_oleh === $tpId && $absensi->status === 'pengganti';
    }

    /** Inval sudah mengisi absen sesi ini. */
    public function sudahDiisiInval(AbsensiMengajar $absensi): bool
    {
        return !is_null($absensi->jam_selesai_aktual);
    }

    /** Sekarang berada di dalam jam mengajar sesi ini (tanggal sesi = hari ini). */
    public function dalamJamSesi(AbsensiMengajar $absensi, ?Carbon $now = null): bool
    {
        $now ??= TimezoneHelper::now();
        $j = $absensi->jadwalMengajar;
        $tgl = $absensi->tanggal?->toDateString();
        if (!$j || $tgl !== $now->toDateString()) return false;

        return $now->betweenIncluded($this->jamSesi($tgl, (string) $j->jam_mulai), $this->jamSesi($tgl, (string) $j->jam_selesai));
    }

    /**
     * Tolak bila inval mengisi di luar jam mengajar. Satu pesan untuk semua
     * pintu (absen reguler, tahfidz, tahsin, absensi santri, setoran).
     */
    public function pastikanJamInval(AbsensiMengajar $absensi): void
    {
        $j   = $absensi->jadwalMengajar;
        $tgl = $absensi->tanggal?->toDateString();
        $now = TimezoneHelper::now();

        if ($tgl !== $now->toDateString()) {
            throw new \DomainException('Kelas inval ini hanya bisa diisi pada tanggalnya.');
        }
        $mulai   = $this->jamSesi($tgl, (string) $j->jam_mulai);
        $selesai = $this->jamSesi($tgl, (string) $j->jam_selesai);
        if ($now->lt($mulai)) {
            throw new \DomainException('Kelas inval baru bisa diisi mulai pukul ' . $mulai->format('H:i') . '.');
        }
        if ($now->gt($selesai)) {
            throw new \DomainException('Jam mengajar sudah berakhir pukul ' . $selesai->format('H:i')
                . '. Inval hanya bisa diisi selama jam mengajar — sesi ini akan tercatat tidak terlaksana.');
        }
    }

    /**
     * Absensi kehadiran santri WAJIB dikirim bersama absen inval bila kelasnya
     * punya santri — sama untuk reguler, tahfidz, dan tahsin.
     *
     * Dulu inval reguler hanya mengirim jurnal (materi + foto); absensi santri
     * dianggap langkah terpisah yang opsional, sehingga sesi inval selesai tanpa
     * data kehadiran (September 2026: 24 sesi, 0 roster). Tahfidz & tahsin sejak
     * awal mewajibkan roster saat absen — aturan reguler kini disamakan.
     */
    public function pastikanRosterInval(AbsensiMengajar $absensi, array $santri): void
    {
        if (!empty($santri)) return;

        $kelasId = $absensi->jadwalMengajar?->kelas_id;
        if (!$kelasId) return; // kelas belum tersinkron — tidak ada roster untuk diisi

        $adaSantri = \App\Models\Santri::aktif()
            ->anggotaKelas($kelasId)->exists();
        if ($adaSantri) {
            throw new \DomainException('Absensi kehadiran santri wajib diisi bersama absen inval. '
                . 'Bila daftar santri tidak muncul, tutup lalu buka ulang aplikasi.');
        }
    }

    /**
     * Guru ini mengampu santri tsb di kelas bertipe $tipe (tahfidz/tahsin).
     * Penjaga untuk tindakan yang hanya boleh dilakukan pengampu: hafalan baru,
     * tasmi', penilaian materi, tasnif, naik level.
     */
    public function pengampuSantri(int $tpId, int $santriId, string $tipe): bool
    {
        return JadwalMengajar::where('tenaga_pendidik_id', $tpId)->where('is_aktif', true)
            ->whereHas('mataPelajaran', fn ($q) => $q->where('tipe', $tipe))
            ->whereNotNull('kelas_id')
            ->whereIn('kelas_id', DB::table('kelas_santri')->where('santri_id', $santriId)
                ->where('is_aktif', true)->pluck('kelas_id'))
            ->exists();
    }

    /**
     * Kartu "Kelas Inval Hari Ini" untuk halaman Tahfidz / Tahsin.
     * Bentuknya sama untuk kedua menu agar tampilannya tidak bisa menyimpang.
     */
    public function kelasInvalHariIni(int $tpId, string $tipe): array
    {
        $now = TimezoneHelper::now();

        return AbsensiMengajar::with(['jadwalMengajar.kelasRel', 'jadwalMengajar.mataPelajaran', 'tenagaPendidik.user:id,name'])
            ->where('digantikan_oleh', $tpId)
            ->whereDate('tanggal', $now->toDateString())
            ->whereIn('status', ['pengganti', 'tidak_terlaksana'])
            ->whereHas('jadwalMengajar.mataPelajaran', fn ($q) => $q->where('tipe', $tipe))
            ->get()
            ->sortBy(fn ($a) => $a->jadwalMengajar?->jam_mulai)
            ->map(function ($a) use ($now) {
                $j = $a->jadwalMengajar;
                return [
                    'absensi_mengajar_id' => $a->id,
                    'jadwal_id'   => $a->jadwal_mengajar_id,
                    'kelas'       => $j?->kelasRel?->nama ?? $j?->kelas ?? '—',
                    'mapel'       => $j?->mataPelajaran?->nama ?? '—',
                    'jam'         => substr((string) $j?->jam_mulai, 0, 5) . '–' . substr((string) $j?->jam_selesai, 0, 5),
                    'guru_asli'   => $a->tenagaPendidik?->user?->name ?? '—',
                    'status'      => $a->status,
                    'sudah_diisi' => $a->status === 'pengganti' && $this->sudahDiisiInval($a),
                    'dalam_jam'   => $this->dalamJamSesi($a, $now),
                ];
            })->values()->all();
    }

    /** Daftar tugas inval untuk guru pengganti: hari ini + mendatang. */
    public function penggantiSaya(int $penggantiTpId, ?Carbon $tanggal)
    {
        $tanggal ??= TimezoneHelper::today();
        return AbsensiMengajar::where('digantikan_oleh', $penggantiTpId)
            // tidak_terlaksana ikut ditampilkan agar pengganti tahu sesinya gagal,
            // bukan tiba-tiba hilang dari daftar.
            ->whereIn('status', ['pengganti', 'tidak_terlaksana'])
            ->whereDate('tanggal', '>=', $tanggal->toDateString()) // hari ini + mendatang
            ->with(['jadwalMengajar.mataPelajaran', 'jadwalMengajar.kelasRel', 'tenagaPendidik.user:id,name'])
            ->orderBy('tanggal')->get();
    }

    /**
     * Inval mengisi absen + jurnal (+ absensi santri) — dipakai reguler, tahfidz,
     * dan tahsin. JP penuh untuk pengganti; di luar jam mengajar ditolak.
     *
     * @param array{foto?:?string, materi?:?string, keterangan?:?string,
     *              absensi_santri?:array, actor?:?string, pembelajaran?:?string} $data
     */
    public function catatInval(int $absensiId, int $penggantiTpId, array $data): AbsensiMengajar
    {
        $absensi = AbsensiMengajar::with('jadwalMengajar.mataPelajaran')->findOrFail($absensiId);

        if ((int) $absensi->digantikan_oleh !== $penggantiTpId) {
            throw new \DomainException('Tugas pengganti ini bukan milik Anda.');
        }
        if ($absensi->status !== 'pengganti') {
            throw new \DomainException($absensi->status === 'tidak_terlaksana'
                ? 'Sesi inval ini sudah tercatat tidak terlaksana.'
                : 'Sesi ini bukan tugas pengganti aktif.');
        }
        if ($this->sudahDiisiInval($absensi)) {
            throw new \DomainException('Anda sudah absen untuk sesi pengganti ini.');
        }
        $this->pastikanJamInval($absensi);

        $santri = $data['absensi_santri'] ?? [];
        $this->pastikanRosterInval($absensi, $santri);
        $jp     = (int) ($absensi->jadwalMengajar?->jumlah_jp ?? 0);
        $now    = TimezoneHelper::now()->toTimeString();

        DB::transaction(function () use ($absensi, $jp, $now, $data, $santri) {
            $absensi->update([
                'jam_mulai_aktual'   => $now,
                'jam_selesai_aktual' => $now, // penanda "sudah diisi inval"
                'jp_terlaksana'      => $jp,
                'status'             => 'pengganti', // tetap; payroll kredit ke digantikan_oleh
                'foto_mengajar'      => $data['foto'] ?? null,
                'materi'             => $data['materi'] ?? null,
                'sudah_buka_jurnal'  => true,
                'keterangan'         => trim(($absensi->keterangan ? $absensi->keterangan . ' | ' : '')
                    . 'Diajar pengganti' . (!empty($data['keterangan']) ? ': ' . $data['keterangan'] : '')),
            ]);

            if (!empty($santri) && !AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->exists()) {
                foreach ($santri as $row) {
                    AbsensiSantri::create([
                        'absensi_mengajar_id' => $absensi->id,
                        'santri_id'           => $row['santri_id'],
                        'status'              => $row['status'],
                    ]);
                }
            }
        });

        if (!empty($santri)) {
            $this->setelahAbsensiSantri($absensi->fresh('jadwalMengajar.mataPelajaran'), $data['actor'] ?? null);
        }

        return $absensi->fresh();
    }

    /**
     * Efek samping setelah absensi santri inval tersimpan — sinkron RamahAnak &
     * WA wali lewat jalur yang sama dengan pengampu (aturan anti-ganda izin/sakit
     * ada di KehadiranSantriService).
     */
    public function setelahAbsensiSantri(AbsensiMengajar $absensi, ?string $actor): void
    {
        $pembelajaran = $absensi->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM';

        app(EducationTelatSync::class)->pushSesi($absensi, $pembelajaran, 'Pengganti: ' . ($actor ?? '—'));

        app(KehadiranSantriService::class)->kirimWa(
            AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->get(),
            $pembelajaran,
            $absensi->tanggal->toDateString()
        );
    }

    /** Kompatibilitas: absen pengganti reguler (dengan foto). */
    public function absenPengganti(
        int $absensiId, int $penggantiTpId, string $fotoPath, ?string $materi, ?string $keterangan,
        bool $sudahBukaJurnal, array $absensiSantri = [], ?string $actor = null
    ): AbsensiMengajar {
        return $this->catatInval($absensiId, $penggantiTpId, [
            'foto'           => $fotoPath,
            'materi'         => $materi,
            'keterangan'     => $keterangan,
            'absensi_santri' => $absensiSantri,
            'actor'          => $actor,
        ]);
    }

    /**
     * Guru asli membatalkan penunjukan pengganti — selama pengganti BELUM mengajar.
     * Sesi kembali ke "izin tanpa pengganti" (JP tidak dibayar).
     */
    public function batalkanPengganti(int $absensiId, int $guruTpId): AbsensiMengajar
    {
        $absensi = AbsensiMengajar::findOrFail($absensiId);

        if ((int) $absensi->tenaga_pendidik_id !== $guruTpId) {
            throw new \DomainException('Sesi ini bukan milik Anda.');
        }
        if ($absensi->status !== 'pengganti') {
            throw new \DomainException('Sesi ini tidak sedang menunjuk pengganti.');
        }
        if (!is_null($absensi->jam_selesai_aktual) || (int) $absensi->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sudah mengajar — tidak bisa dibatalkan.');
        }

        $absensi->update([
            'digantikan_oleh' => null,
            'status'          => 'izin',
            'jp_terlaksana'   => 0,
            'keterangan'      => 'Penunjukan pengganti dibatalkan — izin tanpa pengganti (JP tidak dibayar).',
        ]);

        return $absensi->fresh();
    }

    private function jamSesi(string $tanggal, string $jam): Carbon
    {
        return Carbon::parse("$tanggal $jam", TimezoneHelper::TZ);
    }
}
