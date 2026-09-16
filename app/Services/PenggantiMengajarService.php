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
        if ($alasan = $this->alasanTidakBisaInval($penggantiTpId, $jadwal, $tanggal)) {
            throw new \DomainException($alasan);
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
                    . ' Absen & jurnal hanya bisa diisi selama jam mengajar.',
                'tipe'  => 'tugas_baru',
                'data'  => ['type' => 'kegiatan', 'route' => '/kelas-pengganti', 'tipe_kelas' => $tipe],
                'dedup' => "pengganti-{$jadwal->id}-" . $tanggal->toDateString(),
            ]);
        }

        return $absen;
    }

    /**
     * Alasan seorang guru TIDAK bisa menginval sesi ini, atau null bila bisa.
     * Dipakai daftar calon dan penunjukan, supaya keduanya tak pernah berbeda.
     */
    public function alasanTidakBisaInval(int $tpId, JadwalMengajar $jadwal, Carbon $tanggal): ?string
    {
        $tgl = $tanggal->toDateString();

        $izin = PengajuanIzin::where('tenaga_pendidik_id', $tpId)->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tgl)->where('tanggal_selesai', '>=', $tgl)->exists();
        if ($izin) return 'Guru pengganti sedang izin pada tanggal tersebut.';

        // Jadwal sendiri (tipe apa pun) yang beririsan jam.
        $bentrok = JadwalMengajar::where('tenaga_pendidik_id', $tpId)
            ->where('hari', $jadwal->hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->where('jam_mulai', '<', $jadwal->jam_selesai)
            ->where('jam_selesai', '>', $jadwal->jam_mulai)
            ->exists();
        if ($bentrok) return 'Guru pengganti punya jadwal mengajar sendiri di jam yang sama.';

        // Sudah menginval sesi lain yang beririsan jam di tanggal yang sama.
        $invalLain = AbsensiMengajar::where('digantikan_oleh', $tpId)->whereDate('tanggal', $tgl)
            ->where('jadwal_mengajar_id', '!=', $jadwal->id)
            ->whereHas('jadwalMengajar', fn ($q) => $q
                ->where('jam_mulai', '<', $jadwal->jam_selesai)
                ->where('jam_selesai', '>', $jadwal->jam_mulai))
            ->exists();
        if ($invalLain) return 'Guru pengganti sudah menginval kelas lain di jam yang sama.';

        return null;
    }

    /** Calon pengganti yang benar-benar kosong di jam sesi ini. */
    public function calonPengganti(JadwalMengajar $jadwal, Carbon $tanggal, int $guruTpId): Collection
    {
        return TenagaPendidik::aktif()->where('id', '!=', $guruTpId)->with('user:id,name')->get()
            ->filter(fn ($g) => $g->user && $this->alasanTidakBisaInval($g->id, $jadwal, $tanggal) === null)
            ->map(fn ($g) => ['id' => $g->id, 'nama' => $g->user->name])
            ->sortBy('nama')->values();
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
     * Guru ini mengampu santri tsb di kelas bertipe $tipe (tahfidz/tahsin).
     * Penjaga untuk tindakan yang hanya boleh dilakukan pengampu: hafalan baru,
     * tasmi', penilaian materi, tasnif, naik level.
     */
    public function pengampuSantri(int $tpId, int $santriId, string $tipe): bool
    {
        return JadwalMengajar::where('tenaga_pendidik_id', $tpId)->where('is_aktif', true)
            ->whereHas('mataPelajaran', fn ($q) => $q->where('tipe', $tipe))
            ->whereNotNull('kelas_id')
            ->whereIn('kelas_id', DB::table('kelas_santri')->where('santri_id', $santriId)->pluck('kelas_id'))
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
