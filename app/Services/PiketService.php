<?php

namespace App\Services;

use App\Models\User;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\Santri;
use App\Models\PiketJadwal;
use App\Models\PiketKategori;
use App\Models\PiketPenilaian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inti Guru Piket: status penugasan + penegakan window jam kerja + simpan penilaian.
 * Window aktif = piket SUDAH absen masuk & BELUM absen pulang pada hari penugasan.
 */
class PiketService
{
    /** Batas maksimal pengajuan sanggah per penilaian (1 awal + 1 ulang). */
    private const MAX_SANGGAH = 2;

    /** Status piket hari ini untuk seorang user (+ kondisi window). */
    public function status(User $user): array
    {
        $tp = $user->tenagaPendidik;
        if (!$tp) {
            throw new \DomainException('Akun ini bukan tenaga pendidik.', 403);
        }

        $today  = TimezoneHelper::today()->toDateString();
        $jadwal = PiketJadwal::where('tanggal', $today)->where('tenaga_pendidik_id', $tp->id)->first();
        $absen  = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)->whereDate('tanggal', $today)->first();

        $sudahMasuk  = (bool) ($absen && $absen->jam_masuk);
        $sudahPulang = (bool) ($absen && $absen->jam_pulang);
        $aktif       = $jadwal && $sudahMasuk && !$sudahPulang;

        $alasan = !$jadwal ? 'Anda tidak ditugaskan piket hari ini.'
            : (!$sudahMasuk ? 'Anda belum absen masuk — piket aktif setelah absen masuk.'
            : ($sudahPulang ? 'Anda sudah absen pulang — jam piket berakhir.' : null));

        return [
            'is_piket'       => (bool) $jadwal,
            'jadwal_id'      => $jadwal?->id,
            'tanggal'        => $today,
            'catatan_harian' => $jadwal?->catatan_harian,
            'window'         => [
                'aktif'       => $aktif,
                'sudah_masuk' => $sudahMasuk,
                'sudah_pulang'=> $sudahPulang,
                'jam_masuk'   => $absen?->jam_masuk ? substr($absen->jam_masuk, 0, 5) : null,
                'jam_pulang'  => $absen?->jam_pulang ? substr($absen->jam_pulang, 0, 5) : null,
            ],
            'alasan'         => $alasan,
        ];
    }

    /** Window ketat: piket sedang bertugas (masuk–belum pulang). Untuk absen kelas. */
    private function jadwalAktif(User $user): PiketJadwal
    {
        $st = $this->status($user);
        if (!$st['is_piket']) {
            throw new \DomainException('Anda tidak ditugaskan piket hari ini.', 403);
        }
        if (!$st['window']['aktif']) {
            throw new \DomainException($st['alasan'] ?? 'Di luar jam kerja piket.', 422);
        }
        return PiketJadwal::findOrFail($st['jadwal_id']);
    }

    /** Gerbang longgar: cukup ditugaskan piket HARI INI (sepanjang hari). Untuk penilaian. */
    private function jadwalPiketHariIni(User $user): PiketJadwal
    {
        $st = $this->status($user);
        if (!$st['is_piket']) {
            throw new \DomainException('Anda tidak ditugaskan piket hari ini.', 403);
        }
        return PiketJadwal::findOrFail($st['jadwal_id']);
    }

    /** Simpan satu penilaian (poin & dimensi di-snapshot dari kategori). */
    public function simpanPenilaian(User $user, array $d): PiketPenilaian
    {
        $jadwal = $this->jadwalPiketHariIni($user); // boleh sepanjang hari penugasan
        $tp     = $user->tenagaPendidik;

        if ((int) $d['guru_dinilai_id'] === (int) $tp->id) {
            throw new \DomainException('Tidak bisa menilai diri sendiri.', 422);
        }

        $kat = PiketKategori::where('is_aktif', true)->find($d['kategori_id']);
        if (!$kat) {
            throw new \DomainException('Kategori tidak ditemukan / nonaktif.', 404);
        }

        // Anti-duplikat: kategori yang SAMA untuk guru yang SAMA pada penugasan (hari) ini.
        $dobel = PiketPenilaian::where('piket_jadwal_id', $jadwal->id)
            ->where('guru_dinilai_id', (int) $d['guru_dinilai_id'])
            ->where('kategori_id', $kat->id)->exists();
        if ($dobel) {
            throw new \DomainException("Anda sudah memberi kategori \"{$kat->nama}\" untuk guru ini hari ini.", 422);
        }

        return PiketPenilaian::create([
            'piket_jadwal_id'    => $jadwal->id,
            'guru_dinilai_id'    => (int) $d['guru_dinilai_id'],
            'kategori_id'        => $kat->id,
            'jenis'              => $kat->jenis,
            'dimensi'            => $kat->dimensi,
            'poin'               => $kat->poin,
            'catatan'            => $d['catatan'] ?? null,
            'jadwal_mengajar_id' => $d['jadwal_mengajar_id'] ?? null,
            'bukti_foto'         => $d['bukti_foto'] ?? null,
        ]);
    }

    /** Isi laporan harian piket (mis. "semua aman"). */
    public function laporanHarian(User $user, string $catatan): PiketJadwal
    {
        $st = $this->status($user);
        if (!$st['is_piket']) {
            throw new \DomainException('Anda tidak ditugaskan piket hari ini.', 403);
        }
        $jadwal = PiketJadwal::findOrFail($st['jadwal_id']);
        $jadwal->update(['catatan_harian' => $catatan]);
        return $jadwal;
    }

    // ── Hak sanggah (guru yang dinilai) ─────────────────────────────────────────

    /** Penilaian piket atas DIRI guru ini (untuk dilihat & disanggah). */
    public function penilaianSaya(User $user, int $hariTerakhir = 60): array
    {
        $tp = $user->tenagaPendidik;
        if (!$tp) throw new \DomainException('Akun ini bukan tenaga pendidik.', 403);

        $sejak = TimezoneHelper::today()->subDays($hariTerakhir)->toDateString();

        return PiketPenilaian::with(['kategori:id,nama'])
            ->where('guru_dinilai_id', $tp->id)
            ->whereHas('jadwal', fn($q) => $q->whereDate('tanggal', '>=', $sejak))
            ->orderByDesc('id')->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'kategori'       => $p->kategori?->nama ?? '—',
                'jenis'          => $p->jenis,
                'dimensi'        => $p->dimensi,
                'poin'           => $p->poin_bertanda,
                'catatan'        => $p->catatan,
                'bukti_foto'     => $p->bukti_foto ? asset('storage/'.$p->bukti_foto) : null,
                'tanggal'        => optional($p->jadwal?->tanggal)->toDateString(),
                'status_sanggah' => $p->status_sanggah,
                'alasan_sanggah' => $p->alasan_sanggah,
                'catatan_tinjauan' => $p->catatan_tinjauan,
                'bisa_sanggah'   => in_array($p->status_sanggah, ['-', 'ditolak'], true)
                    && $p->jumlah_sanggah < self::MAX_SANGGAH,
            ])->values()->all();
    }

    /** Ajukan sanggah atas sebuah penilaian (hanya oleh guru yang dinilai). */
    public function ajukanSanggah(User $user, int $penilaianId, string $alasan): PiketPenilaian
    {
        $tp = $user->tenagaPendidik;
        if (!$tp) throw new \DomainException('Akun ini bukan tenaga pendidik.', 403);

        $p = PiketPenilaian::findOrFail($penilaianId);
        if ((int) $p->guru_dinilai_id !== (int) $tp->id) {
            throw new \DomainException('Penilaian ini bukan atas diri Anda.', 403);
        }
        if (!in_array($p->status_sanggah, ['-', 'ditolak'], true)) {
            throw new \DomainException('Penilaian ini sedang/sudah ditinjau, tidak bisa disanggah lagi.', 422);
        }
        if ($p->jumlah_sanggah >= self::MAX_SANGGAH) {
            throw new \DomainException('Batas pengajuan sanggah tercapai (maks ' . self::MAX_SANGGAH . '×).', 422);
        }

        $p->update([
            'status_sanggah'   => 'diajukan',
            'alasan_sanggah'   => $alasan,
            'jumlah_sanggah'   => $p->jumlah_sanggah + 1,
            'ditinjau_oleh'    => null,
            'ditinjau_pada'    => null,
            'catatan_tinjauan' => null,
        ]);
        return $p;
    }

    // ── Handoff absensi santri (guru tidak konfirmasi → piket) ──────────────────

    /** Daftar jadwal hari ini yang gurunya BELUM konfirmasi (window lewat, tanpa absensi). */
    public function sesiPerluAbsen(User $user): array
    {
        $st = $this->status($user);
        if (!$st['is_piket'] || !$st['window']['aktif']) {
            return ['boleh' => false, 'alasan' => $st['alasan'] ?? 'Window piket tidak aktif.', 'sesi' => []];
        }

        $now   = TimezoneHelper::now();
        $today = $now->toDateString();
        $hari  = TimezoneHelper::namaHariDB($now);

        $jadwalHariIni = JadwalMengajar::with(['mataPelajaran', 'kelasRel', 'tenagaPendidik.user:id,name'])
            ->where('hari', $hari)->where('is_aktif', true)->whereNotNull('kelas_id')
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->get();

        $sudah = AbsensiMengajar::whereDate('tanggal', $today)
            ->whereIn('jadwal_mengajar_id', $jadwalHariIni->pluck('id'))
            ->pluck('jadwal_mengajar_id')->flip();

        $sesi = $jadwalHariIni->filter(function ($j) use ($now, $today, $sudah) {
            if ($sudah->has($j->id)) return false; // guru sudah konfirmasi → bukan urusan piket
            $end = Carbon::parse("$today {$j->jam_selesai}", TimezoneHelper::TZ);
            return $now->gt($end); // hanya yang window-nya sudah lewat
        })->map(fn($j) => [
            'jadwal_id'      => $j->id,
            'mata_pelajaran' => $j->mataPelajaran?->nama ?? '—',
            'tipe'           => $j->mataPelajaran?->tipe,
            'kelas'          => $j->kelasRel?->nama ?? $j->kelas,
            'guru'           => $j->tenagaPendidik?->user?->name ?? '—',
            'jam'            => substr($j->jam_mulai, 0, 5) . '–' . substr($j->jam_selesai, 0, 5),
        ])->values()->all();

        return ['boleh' => true, 'alasan' => null, 'sesi' => $sesi];
    }

    /** Roster santri untuk satu jadwal (untuk diisi piket). */
    public function rosterJadwal(User $user, int $jadwalId): array
    {
        $this->jadwalAktif($user); // pastikan piket & window aktif

        $jadwal = JadwalMengajar::with(['mataPelajaran', 'kelasRel'])->findOrFail($jadwalId);
        if (!$jadwal->kelas_id) {
            throw new \DomainException('Kelas jadwal ini belum tersinkron.', 422);
        }

        $santri = Santri::aktif()
            ->whereHas('kelas', fn($q) => $q->where('kelas.id', $jadwal->kelas_id))
            ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['santri_id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap])
            ->values()->all();

        return [
            'jadwal_id'      => $jadwal->id,
            'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? '—',
            'kelas'          => $jadwal->kelasRel?->nama ?? $jadwal->kelas,
            'santri'         => $santri,
        ];
    }

    /** Piket mengisi absensi santri untuk sesi yang gurunya tak konfirmasi. */
    public function absenKelas(User $user, int $jadwalId, array $absensi, ?string $materi): AbsensiMengajar
    {
        $this->jadwalAktif($user); // pastikan piket & window aktif
        $piketNama = $user->name ?? 'Piket';

        $jadwal = JadwalMengajar::with('mataPelajaran')->findOrFail($jadwalId);
        $now    = TimezoneHelper::now();
        $today  = $now->toDateString();

        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($now)) {
            throw new \DomainException('Jadwal ini tidak berlangsung hari ini.', 422);
        }
        $end = Carbon::parse("$today {$jadwal->jam_selesai}", TimezoneHelper::TZ);
        if ($now->lte($end)) {
            throw new \DomainException('Sesi belum berakhir — piket mengisi hanya setelah jam selesai & guru tak konfirmasi.', 422);
        }
        if (AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->whereDate('tanggal', $today)->exists()) {
            throw new \DomainException('Sesi ini sudah tercatat — tidak perlu diisi piket.', 422);
        }

        $am = DB::transaction(function () use ($jadwal, $today, $now, $absensi, $materi, $piketNama) {
            $am = AbsensiMengajar::create([
                'jadwal_mengajar_id' => $jadwal->id,
                'tenaga_pendidik_id' => $jadwal->tenaga_pendidik_id, // guru terjadwal (jejak); tidak_terlaksana → tanpa vakasi
                'tanggal'            => $today,
                'jam_mulai_aktual'   => $now->format('H:i:s'),
                'jp_terlaksana'      => 0,
                'status'             => 'tidak_terlaksana',
                'materi'             => $materi,
                'keterangan'         => 'Guru tidak konfirmasi — absensi santri diisi guru piket (' . $piketNama . ').',
                'sudah_buka_jurnal'  => false,
            ]);
            foreach ($absensi as $row) {
                AbsensiSantri::create([
                    'absensi_mengajar_id' => $am->id,
                    'santri_id'           => $row['santri_id'],
                    'status'              => $row['status'],
                ]);
            }
            return $am;
        });

        // Telat/Alpha → RamahAnak (sinkron sama seperti absen guru).
        app(EducationTelatSync::class)->pushSesi($am, $jadwal->mataPelajaran?->nama ?? 'KBM', 'Piket: ' . $piketNama);

        // Notifikasi WA wali per santri — konsisten dgn absen normal (siapa pun pencatatnya).
        $pembelajaran = $jadwal->mataPelajaran?->nama ?? 'KBM';
        foreach (AbsensiSantri::where('absensi_mengajar_id', $am->id)->get() as $as) {
            app(WaService::class)->absenMengajar($as->santri_id, $as->status, $pembelajaran, $today, $as->id);
        }

        return $am;
    }
}
