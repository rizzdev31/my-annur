<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\LiburPembelajaran;
use App\Models\PeriodePenggajian;
use App\Models\Santri;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA penulis status 'libur' pada absensi mengajar. Dua sumber:
 *
 *  1. HARI LIBUR PENUH (HariLibur) — nasional/pesantren/darurat. Semua jadwal
 *     hari itu jadi 'libur', JP penuh, dan seluruh sistem menganggap hari itu
 *     libur (absen harian, hari kerja gaji, piket, dst).
 *
 *  2. LIBUR PEMBELAJARAN (LiburPembelajaran) — pembelajaran diganti kegiatan,
 *     tetapi ORANGNYA TETAP MASUK. Bisa seluruh pembelajaran, kelas tertentu,
 *     atau sesi tertentu, dan bisa dibatasi jam. Jurnal terisi nama kegiatan
 *     dan santri tercatat hadir — kecuali yang izin/sakit, yang mengikuti
 *     Perizinan Santri & Smart Health lewat KehadiranSantriService.
 *
 * Libur penuh MENANG atas libur pembelajaran: pada tanggal yang sudah libur
 * penuh, libur pembelajaran tidak menulis apa pun (agar tidak dobel-tulis).
 *
 * JP tetap diberikan penuh (gaji jalan) dan status 'libur' netral di kinerja
 * (SesiMengajarService::STATUS_NETRAL), jadi guru tidak dirugikan.
 */
class LiburMengajarService
{
    /** Peta [tanggal => [jadwal_id => LiburPembelajaran]] — dibaca banyak tempat per request. */
    private static array $peta = [];

    // ══════════════════════════════════════════════════════════════════════
    // 1. HARI LIBUR PENUH
    // ══════════════════════════════════════════════════════════════════════

    /** Isi libur untuk satu tanggal (semua jadwal). Return jumlah sesi yang ditandai libur. */
    public function isiLiburTanggal(string $tanggal): int
    {
        $libur = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
            ->orderByDesc('is_darurat')->first();
        if (!$libur) return 0;

        $hari = TimezoneHelper::namaHariDB(Carbon::parse($tanggal));
        $ket  = 'Libur ' . ucfirst($libur->sumber ?: $libur->tipe ?: 'pesantren') . ': ' . $libur->nama;

        $jadwal = JadwalMengajar::where('hari', $hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->get(['id', 'tenaga_pendidik_id', 'jumlah_jp']);

        $n = 0;
        foreach ($jadwal as $j) {
            $am = AbsensiMengajar::where('jadwal_mengajar_id', $j->id)->whereDate('tanggal', $tanggal)->first();

            if (!$am) {
                AbsensiMengajar::create([
                    'jadwal_mengajar_id' => $j->id,
                    'tenaga_pendidik_id' => $j->tenaga_pendidik_id,
                    'tanggal'            => $tanggal,
                    'jp_terlaksana'      => $j->jumlah_jp, // JP tetap diberikan saat libur
                    'status'             => 'libur',
                    'sudah_buka_jurnal'  => false,
                    'keterangan'         => $ket,
                ]);
                $n++;
            } elseif ($am->status === 'tidak_terlaksana') {
                // Sempat ter-mark "tidak hadir" sebelum libur ditetapkan → koreksi ke libur.
                $am->update(['status' => 'libur', 'jp_terlaksana' => $j->jumlah_jp, 'keterangan' => $ket]);
                $n++;
            }
            // status lain (terlaksana/izin/pengganti/libur) dibiarkan apa adanya.
        }
        return $n;
    }

    /** Isi semua tanggal dalam rentang sebuah HariLibur s/d hari ini (dipakai saat libur dibuat). */
    public function isiUntukLibur(HariLibur $libur): int
    {
        $today = TimezoneHelper::today();
        $start = $libur->tanggal instanceof Carbon ? $libur->tanggal->copy() : Carbon::parse($libur->tanggal);
        $end   = $libur->tanggal_selesai
            ? ($libur->tanggal_selesai instanceof Carbon ? $libur->tanggal_selesai->copy() : Carbon::parse($libur->tanggal_selesai))
            : $start->copy();
        if ($end->gt($today)) $end = $today->copy(); // tanggal masa depan ditangani command harian

        $n = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $n += $this->isiLiburTanggal($d->toDateString());
        }
        return $n;
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. LIBUR PEMBELAJARAN — gerbang baca
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Peta jadwal yang diliburkan pada tanggal tsb: [jadwal_id => LiburPembelajaran].
     * Dipakai scheduler, papan piket, PWA, dan laporan agar semuanya menyebut
     * kondisi yang sama untuk sesi yang sama.
     */
    public function petaPembelajaran(string $tanggal): array
    {
        if (array_key_exists($tanggal, self::$peta)) return self::$peta[$tanggal];

        // Libur penuh menang — tidak perlu peta per jadwal.
        if (HariLibur::isLibur($tanggal)) return self::$peta[$tanggal] = [];

        $peta = [];
        foreach (LiburPembelajaran::berlaku()->padaTanggal($tanggal)->get() as $lp) {
            foreach ($this->jadwalTerdampak($lp, $tanggal) as $j) {
                $peta[$j->id] ??= $lp;      // kegiatan pertama yang mencakup sesi ini
            }
        }
        return self::$peta[$tanggal] = $peta;
    }

    /** Libur pembelajaran yang berlaku untuk satu sesi (null bila sesi tetap berjalan). */
    public function pembelajaranDiliburkan(string $tanggal, int $jadwalId): ?LiburPembelajaran
    {
        return $this->petaPembelajaran($tanggal)[$jadwalId] ?? null;
    }

    /** Buang cache peta — dipakai setelah menulis/membatalkan agar pembaca berikutnya segar. */
    public static function lupakanPeta(?string $tanggal = null): void
    {
        if ($tanggal === null) { self::$peta = []; return; }
        unset(self::$peta[$tanggal]);
    }

    /**
     * Jadwal yang terdampak satu kegiatan pada satu tanggal.
     * Penyaringnya berlapis: hari → cakupan → jenis kelas → jam.
     *
     * @return Collection<JadwalMengajar>
     */
    public function jadwalTerdampak(LiburPembelajaran $lp, string $tanggal): Collection
    {
        if (!$lp->mencakupTanggal($tanggal)) return collect();

        $hari = TimezoneHelper::namaHariDB(Carbon::parse($tanggal));

        $q = JadwalMengajar::with(['kelasRel:id,nama,jenis', 'mataPelajaran:id,nama,tipe', 'tenagaPendidik.user:id,name'])
            ->where('hari', $hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($s) => $s->where('is_aktif', true));

        if ($lp->cakupan === 'kelas') {
            $ids = $lp->kelas()->pluck('kelas.id');
            if ($ids->isEmpty()) return collect();
            $q->whereIn('kelas_id', $ids);
        } elseif ($lp->cakupan === 'sesi') {
            $ids = $lp->jadwal()->pluck('jadwal_mengajar.id');
            if ($ids->isEmpty()) return collect();
            $q->whereIn('id', $ids);
        }

        $rows = $q->orderBy('jam_mulai')->get();

        if ($lp->jenis_kelas) {
            $rows = $rows->filter(fn ($j) => in_array($j->kelasRel?->jenis, $lp->jenis_kelas, true));
        }

        // Kegiatan berjam: hanya sesi yang beririsan dengan jamnya.
        if ($lp->jam_mulai && $lp->jam_selesai) {
            $jam = fn ($t) => substr((string) $t, 0, 5);
            $kMulai = $jam($lp->jam_mulai); $kSelesai = $jam($lp->jam_selesai);
            $rows = $rows->filter(fn ($j) =>
                $jam($j->jam_mulai) < $kSelesai && $jam($j->jam_selesai) > $kMulai);
        }

        return $rows->values();
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. LIBUR PEMBELAJARAN — tulis & batalkan
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Isi absensi mengajar + roster santri untuk satu kegiatan.
     *
     * Urutan presedensi per sesi:
     *   1. sudah 'terlaksana', atau pengganti yang SUDAH mengajar → DILEWATI
     *      (pekerjaan guru tidak pernah ditimpa)
     *   2. 'tidak_terlaksana' / 'izin' / pengganti belum mengisi → diubah jadi
     *      'libur', status lamanya disimpan untuk pemulihan
     *   3. belum ada catatan → dibuat sebagai 'libur'
     *
     * Idempotent: dijalankan dua kali hasilnya sama.
     *
     * @param  bool $simulasi  true = hitung lalu batalkan (pratinjau).
     * @return array ringkasan dampak
     */
    public function isiPembelajaran(LiburPembelajaran $lp, bool $simulasi = false): array
    {
        $hasil = [
            'sesi_total' => 0, 'sesi_dibuat' => 0, 'sesi_diperbarui' => 0, 'sesi_dilewati' => 0,
            'roster_dibuat' => 0, 'dilewati_detail' => [], 'per_tanggal' => [],
            'tanggal_libur_penuh' => [], 'tanggal_terkunci' => [], 'guru_ids' => [],
        ];

        $today = TimezoneHelper::today();
        $akhir = $lp->tanggal_akhir->copy();
        if ($akhir->gt($today)) $akhir = $today->copy();   // masa depan: command harian

        DB::beginTransaction();
        try {
            for ($d = $lp->tanggal->copy(); $d->lte($akhir); $d->addDay()) {
                $tgl = $d->toDateString();

                if (HariLibur::isLibur($tgl)) { $hasil['tanggal_libur_penuh'][] = $tgl; continue; }
                if ($this->periodeTerkunci($tgl))  { $hasil['tanggal_terkunci'][] = $tgl;  continue; }

                $jadwal = $this->jadwalTerdampak($lp, $tgl);
                $ringkas = ['tanggal' => $tgl, 'sesi' => $jadwal->count(), 'dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0];

                foreach ($jadwal as $j) {
                    $hasil['sesi_total']++;
                    $am = AbsensiMengajar::where('jadwal_mengajar_id', $j->id)->whereDate('tanggal', $tgl)->first();

                    if ($am && $this->sudahDiajar($am)) {
                        $hasil['sesi_dilewati']++; $ringkas['dilewati']++;
                        $hasil['dilewati_detail'][] = [
                            'tanggal' => $tgl,
                            'kelas'   => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                            'mapel'   => $j->mataPelajaran?->nama ?? '—',
                            'guru'    => $j->tenagaPendidik?->user?->name ?? '—',
                            'status'  => $am->status,
                        ];
                        continue;
                    }

                    $jp = $lp->hitung_jp ? (int) $j->jumlah_jp : 0;

                    if ($am) {
                        $am->update([
                            'status'                => 'libur',
                            'status_sebelum'        => $am->status_sebelum ?: $am->status,
                            'jp_terlaksana'         => $jp,
                            'materi'                => $lp->teksJurnal(),
                            'keterangan'            => $lp->keteranganSesi(),
                            'libur_pembelajaran_id' => $lp->id,
                        ]);
                        $hasil['sesi_diperbarui']++; $ringkas['diperbarui']++;
                    } else {
                        $am = AbsensiMengajar::create([
                            'jadwal_mengajar_id'    => $j->id,
                            'tenaga_pendidik_id'    => $j->tenaga_pendidik_id,
                            'tanggal'               => $tgl,
                            'jp_terlaksana'         => $jp,
                            'status'                => 'libur',
                            'sudah_buka_jurnal'     => false,
                            'materi'                => $lp->teksJurnal(),
                            'keterangan'            => $lp->keteranganSesi(),
                            'libur_pembelajaran_id' => $lp->id,
                        ]);
                        $hasil['sesi_dibuat']++; $ringkas['dibuat']++;
                    }

                    $hasil['guru_ids'][$j->tenaga_pendidik_id] = true;
                    $hasil['roster_dibuat'] += $this->isiRoster($am, $j, $lp, $tgl);
                }

                $hasil['per_tanggal'][] = $ringkas;
            }

            $simulasi ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $hasil['guru_ids'] = array_keys($hasil['guru_ids']);
        $hasil['simulasi'] = $simulasi;
        self::lupakanPeta();

        return $hasil;
    }

    /** Isi semua kegiatan yang berlaku pada satu tanggal (dipakai command harian). */
    public function isiPembelajaranTanggal(string $tanggal): array
    {
        $ringkas = ['kegiatan' => 0, 'sesi' => 0, 'roster' => 0];
        foreach (LiburPembelajaran::berlaku()->padaTanggal($tanggal)->get() as $lp) {
            $r = $this->isiPembelajaran($lp);
            $ringkas['kegiatan']++;
            $ringkas['sesi']   += $r['sesi_dibuat'] + $r['sesi_diperbarui'];
            $ringkas['roster'] += $r['roster_dibuat'];
        }
        return $ringkas;
    }

    /**
     * Batalkan kegiatan: pulihkan keadaan sebelum diliburkan.
     *   - baris absensi yang DIBUAT kegiatan ini → dihapus
     *   - baris yang hanya diubah → status_sebelum dipulihkan
     *   - roster bersumber 'kegiatan' → dihapus (catatan guru tidak disentuh)
     *
     * @param  string|null $dariTanggal  hanya bersihkan tanggal >= ini (mis. sisa rentang).
     */
    public function batalkanPembelajaran(
        LiburPembelajaran $lp, ?string $alasan = null, ?int $olehUserId = null, ?string $dariTanggal = null
    ): array {
        $hasil = ['sesi_dihapus' => 0, 'sesi_dipulihkan' => 0, 'roster_dihapus' => 0];

        DB::transaction(function () use ($lp, $alasan, $olehUserId, $dariTanggal, &$hasil) {
            $q = AbsensiMengajar::where('libur_pembelajaran_id', $lp->id);
            if ($dariTanggal) $q->whereDate('tanggal', '>=', $dariTanggal);

            foreach ($q->with('jadwalMengajar:id,jumlah_jp')->get() as $am) {
                $hasil['roster_dihapus'] += AbsensiSantri::where('absensi_mengajar_id', $am->id)
                    ->where('sumber', 'kegiatan')->delete();

                if ($am->status_sebelum) {
                    $am->update([
                        'status'                => $am->status_sebelum,
                        'status_sebelum'        => null,
                        'libur_pembelajaran_id' => null,
                        // JP mengikuti aturan status aslinya: hanya 'izin' yang tetap
                        // diakui penuh; tidak_terlaksana & pengganti-belum-mengisi = 0.
                        'jp_terlaksana'         => $am->status_sebelum === 'izin'
                            ? (int) ($am->jadwalMengajar?->jumlah_jp ?? 0) : 0,
                        'materi'                => null,
                        'keterangan'            => 'Dipulihkan: kegiatan "' . $lp->nama . '" dibatalkan',
                    ]);
                    $hasil['sesi_dipulihkan']++;
                } else {
                    $am->delete();          // roster lain ikut terhapus (cascade)
                    $hasil['sesi_dihapus']++;
                }
            }

            // Pembatalan sebagian rentang tidak mematikan kegiatannya.
            if (!$dariTanggal) {
                $lp->update([
                    'is_aktif'          => false,
                    'dibatalkan_pada'   => now(),
                    'alasan_pembatalan' => $alasan,
                    'dibatalkan_oleh'   => $olehUserId,
                ]);
            }
        });

        self::lupakanPeta();
        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pembantu
    // ══════════════════════════════════════════════════════════════════════

    /** Sesi yang benar-benar sudah diajar — tidak boleh ditimpa kegiatan. */
    private function sudahDiajar(AbsensiMengajar $am): bool
    {
        if (in_array($am->status, ['terlaksana', 'hadir'], true)) return true;

        // Pengganti: hanya yang SUDAH mengisi. Yang baru ditunjuk tapi belum
        // mengajar ikut diliburkan — penugasannya gugur karena statusnya 'libur',
        // sehingga vakasinya tidak dibayar untuk sesi yang tidak pernah ada.
        return $am->status === 'pengganti'
            && ((int) $am->jp_terlaksana > 0 || $am->jam_selesai_aktual);
    }

    /**
     * Roster santri otomatis. Aturan izin & sakit TIDAK ditulis ulang di sini —
     * memakai KehadiranSantriService, gerbang yang sama dengan roster guru,
     * supaya hasilnya tidak mungkin berbeda.
     *
     * WA ke wali sengaja TIDAK dikirim: ini kehadiran kegiatan yang ditulis
     * sistem, bukan absensi yang diisi guru (satu hari kegiatan bisa ratusan pesan).
     */
    private function isiRoster(AbsensiMengajar $am, JadwalMengajar $j, LiburPembelajaran $lp, string $tanggal): int
    {
        if (!$lp->isi_absensi_santri || !$j->kelas_id) return 0;

        $santri = Santri::aktif()->anggotaKelas($j->kelas_id)->pluck('id');
        if ($santri->isEmpty()) return 0;

        $sudah = AbsensiSantri::where('absensi_mengajar_id', $am->id)->pluck('santri_id')->flip();
        $kh      = app(KehadiranSantriService::class);
        $konteks = $kh->konteks($santri, $tanggal);

        $n = 0;
        foreach ($santri as $sid) {
            if ($sudah->has($sid)) continue;         // catatan guru tidak ditimpa

            $baris  = $kh->baris((int) $sid, $konteks);
            $status = $baris['status'] === 'hadir' ? ($lp->status_santri ?: 'hadir') : $baris['status'];

            AbsensiSantri::create([
                'absensi_mengajar_id' => $am->id,
                'santri_id'           => (int) $sid,
                'status'              => $status,
                'sumber'              => 'kegiatan',
                'catatan'             => $lp->nama,
            ]);
            $n++;
        }
        return $n;
    }

    /** Periode penggajian tanggal itu sudah dikunci/difinalisasi? */
    private function periodeTerkunci(string $tanggal): bool
    {
        $periode = PeriodePenggajian::untukTanggal($tanggal);
        return $periode && $periode->dikunci_pada !== null;
    }
}
