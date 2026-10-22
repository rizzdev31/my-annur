<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\Santri;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keanggotaan kelas santri — SATU pintu untuk semua perubahan: panel centang
 * "Atur Santri", form edit santri, dan naik kelas massal.
 *
 * Aturan:
 *  1. RIWAYAT DIJAGA. kelas_santri tidak pernah dihapus; keanggotaan lama ditutup
 *     (is_aktif=false + tanggal_keluar), keanggotaan baru dibuka.
 *  2. SATU KELAS AKTIF PER SLOT (Kelas::SLOT): sekolah = 1, pesantren = 1, program
 *     Quran (tahfidz ∪ tahsin) = 1. Memasukkan santri ke kelas lain di slot yang
 *     sama otomatis MEMINDAHKAN-nya; slot berbeda berjalan berdampingan, sehingga
 *     santri kelas X bisa sekaligus menjadi anggota kelas pesantren.
 *  2b. NAIK KELAS ≠ PINDAH KELAS. Naik kelas = tahsin → tahfidz (ganti program)
 *     ATAU sekolah → tingkat lebih tinggi; pindah kelas = antar kelas SEJAJAR
 *     di pembelajaran yang sama. Ditegakkan di buka() lewat $mode, dan setiap
 *     baris riwayat diberi label sesuai SIFATNYA (naik / turun / pindah /
 *     penempatan), bukan sesuai tombol yang dipakai.
 *  3. PENCAPAIAN TIDAK BERUBAH KARENA PINDAH: hafalan tahfidz & level/nilai tahsin
 *     melekat pada santri. Level tahsin hanya ditempatkan saat santri tanpa progres
 *     tahsin masuk kelas tahsin. Tahsin → tahfidz memulai hafalan tahfidz baru.
 *  4. Setelah berubah: data disinkron ke RamahAnak (outbox, asinkron) dan pengampu
 *     kelas tahfidz/tahsin dikabari.
 *
 * Dulu form edit santri memakai sync() yang menghapus riwayat dan membolehkan dua
 * kelas sejenis, sementara naik kelas menjaga riwayat — dua jalur, dua aturan.
 */
class KenaikanKelasService
{
    /** Jenis kelas yang pengampunya dikabari saat santri berubah. */
    public const JENIS_DIKABARI = ['tahfidz', 'tahsin'];

    /**
     * NAIK KELAS vs PINDAH KELAS - dua hal berbeda (keputusan 22 Okt 2026).
     *
     * NAIK KELAS punya DUA jalur:
     *   1. tahsin -> tahfidz. Ini ganti PROGRAM: santri berhenti dinilai per
     *      materi tahsin dan mulai menyetor hafalan. Tidak ada tingkat yang
     *      dibandingkan, sebab tahfidz tak berjenjang kelas.
     *   2. sekolah -> sekolah dengan TINGKAT lebih tinggi (VII -> VIII -> ...).
     *      Program & pembelajarannya sama, yang naik adalah jenjangnya.
     *
     * PINDAH KELAS = perpindahan tanpa kenaikan: kelas sejajar di pembelajaran
     * yang sama (VII A -> VII B, Tahfidz 1 -> Tahfidz 2, Tahsin Putra 1 ->
     * Tahsin Putra 2, Muhadoroh Putri -> Putra).
     *
     * Dulu keduanya memakai satu syarat yang sama ("slot harus sama"), sehingga
     * satu tombol mengerjakan dua hal. Bukti kerusakannya ada di riwayat
     * produksi: baris berketerangan "Naik kelas dari Tahfidz Putri 5" (padahal
     * itu pindah halaqoh) dan 19 perpindahan tahsin -> tahfidz yang sebagian
     * hanya tercatat "Diatur lewat panel kelas" (padahal itu naik program).
     */
    public const NAIK_DARI = 'tahsin';
    public const NAIK_KE   = 'tahfidz';

    /**
     * Jenis kelas yang BERJENJANG, sehingga perpindahan antar tingkatnya
     * adalah kenaikan - bukan sekadar pindah kelas.
     *
     * Tahsin sengaja TIDAK termasuk walau kelasnya punya `level_tahsin`:
     * level tahsin melekat pada SANTRI (`santri.tahsin_level`, dinaikkan oleh
     * TasnifService), bukan pada keanggotaan kelas. Memindahkan santri ke
     * halaqoh tahsin berlevel lain adalah perombakan halaqoh, bukan kenaikan.
     */
    public const JENIS_BERTINGKAT = ['sekolah'];

    /** Tingkat sebagai angka; null bila tak berjenjang angka (mis. "SMA"). */
    public static function angkaTingkat(?string $tingkat): ?int
    {
        $t = trim((string) $tingkat);

        return $t !== '' && ctype_digit($t) ? (int) $t : null;
    }

    /**
     * Sifat perpindahan: dasar tunggal bagi penegakan aturan DAN label riwayat.
     *
     * @return string 'penempatan' | 'pindah' | 'naik' | 'turun'
     */
    public function sifatPerpindahan(
        ?string $jenisLama,
        string $jenisBaru,
        ?string $tingkatLama = null,
        ?string $tingkatBaru = null,
    ): string {
        if ($jenisLama === null) {
            return 'penempatan';
        }

        // Lintas pembelajaran: hanya tahsin <-> tahfidz yang punya makna naik/turun.
        if ($jenisLama !== $jenisBaru) {
            if ($jenisLama === self::NAIK_DARI && $jenisBaru === self::NAIK_KE) return 'naik';
            if ($jenisLama === self::NAIK_KE && $jenisBaru === self::NAIK_DARI) return 'turun';

            return 'pindah';
        }

        // Pembelajaran sama: untuk jenjang bertingkat, tingkatnya yang menentukan.
        if (in_array($jenisBaru, self::JENIS_BERTINGKAT, true)) {
            $a = self::angkaTingkat($tingkatLama);
            $b = self::angkaTingkat($tingkatBaru);
            if ($a !== null && $b !== null && $a !== $b) {
                return $b > $a ? 'naik' : 'turun';
            }
        }

        return 'pindah';
    }

    /** Label riwayat yang jujur, dipakai semua jalur. */
    private function labelSifat(string $sifat, ?string $namaLama): string
    {
        return match ($sifat) {
            'naik'   => 'Naik kelas dari ' . $namaLama,
            'turun'  => 'Turun kelas dari ' . $namaLama,
            'pindah' => 'Pindah dari ' . $namaLama,
            default  => 'Penempatan kelas',
        };
    }

    /** Perubahan per kelas selama satu operasi: [kelas_id => ['masuk'=>[nama], 'keluar'=>[nama]]]. */
    private array $perubahan = [];
    private array $santriBerubah = [];
    /** Santri yang baru dibuka keanggotaannya di kelas TAHSIN: [santri_id => Kelas]. */
    private array $masukTahsin = [];

    public function __construct(private SantriSyncService $sync) {}

    // ══════════════════════════════════════════════════════════════════════
    // API publik
    // ══════════════════════════════════════════════════════════════════════

    /** Pindahkan 1 santri ke kelas tujuan (menutup kelas aktif di slot yang sama). */
    public function pindahKelas(int $santriId, Kelas $tujuan, ?string $tanggal = null, ?string $keterangan = null): bool
    {
        $this->mulai();
        $berubah = DB::transaction(fn () => $this->buka(
            $santriId, $tujuan, $tanggal ?? $this->hariIni(), $keterangan, 'pindah') !== false);
        $this->selesai();
        return $berubah;
    }

    /**
     * Panel centang: jadikan $santriIds sebagai anggota AKTIF kelas ini.
     *   - dicentang & belum anggota → masuk (dipindah dari kelas sejenisnya bila ada)
     *   - anggota & tidak dicentang → keluar (ditutup, tanpa kelas pengganti)
     *
     * @return array{masuk: array, keluar: array}
     */
    public function aturAnggota(Kelas $kelas, array $santriIds, ?string $tanggal = null): array
    {
        $tanggal ??= $this->hariIni();
        $target  = Santri::aktif()->whereIn('id', array_map('intval', $santriIds))->pluck('id');
        $aktif   = DB::table('kelas_santri')->where('kelas_id', $kelas->id)->where('is_aktif', true)->pluck('santri_id');

        $masukIds  = $target->diff($aktif)->values();
        $keluarIds = $aktif->diff($target)->values();

        $this->mulai();
        $hasil = DB::transaction(function () use ($kelas, $masukIds, $keluarIds, $tanggal) {
            $masuk = [];
            foreach ($masukIds as $sid) {
                $dari = $this->buka((int) $sid, $kelas, $tanggal, 'Diatur lewat panel kelas');
                $masuk[] = ['santri_id' => (int) $sid, 'dari' => $dari ?: null];
            }
            $keluar = [];
            foreach ($keluarIds as $sid) {
                $this->tutup((int) $sid, [$kelas->id], $tanggal);
                $keluar[] = ['santri_id' => (int) $sid];
            }
            return ['masuk' => $masuk, 'keluar' => $keluar];
        });
        $this->selesai();

        return $hasil;
    }

    /**
     * Form edit santri: jadikan $kelasIds sebagai kelas aktif santri ini.
     * Maksimal satu kelas per slot; slot yang tidak dipilih ditutup.
     *
     * @throws \DomainException bila dua kelas dari slot yang sama dipilih
     */
    public function aturKelasSantri(Santri $santri, array $kelasIds, ?string $tanggal = null): void
    {
        $tanggal ??= $this->hariIni();
        $kelas = $this->pastikanSatuPerSlot($kelasIds);

        $this->mulai();
        DB::transaction(function () use ($santri, $kelas, $tanggal) {
            foreach ($kelas as $k) {
                $this->buka($santri->id, $k, $tanggal, 'Diatur lewat data santri');
            }
            // Slot yang tidak dipilih sama sekali → keanggotaannya ditutup.
            $jenisDipilih = $kelas->flatMap(fn ($k) => $k->jenisSeslot())->unique()->all();
            $sisa = DB::table('kelas_santri')->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
                ->where('kelas_santri.santri_id', $santri->id)->where('kelas_santri.is_aktif', true)
                ->whereNotIn('kelas.jenis', $jenisDipilih ?: ['__kosong__'])
                ->pluck('kelas_santri.kelas_id')->all();
            if ($sisa) $this->tutup($santri->id, $sisa, $tanggal);
        });
        $this->selesai();
    }

    /**
     * Tolak pilihan dua kelas dari slot yang sama. Dipanggil sebelum menyimpan
     * apa pun, agar santri baru tidak terlanjur dibuat.
     * @return Collection<Kelas>
     * @throws \DomainException
     */
    public function pastikanSatuPerSlot(array $kelasIds): Collection
    {
        $kelas = Kelas::whereIn('id', array_map('intval', $kelasIds))->get();

        foreach ($kelas->groupBy(fn ($k) => implode('+', $k->jenisSeslot())) as $slot => $daftar) {
            if ($daftar->count() > 1) {
                $batas = match ($slot) {
                    'sekolah'   => 'satu kelas sekolah',
                    'pesantren' => 'satu kelas pesantren',
                    default     => 'satu kelas tahfidz ATAU tahsin',
                };
                throw new \DomainException("Santri hanya boleh punya {$batas} (dipilih: "
                    . $daftar->pluck('nama')->implode(', ') . ').');
            }
        }
        return $kelas;
    }

    /**
     * NAIK KELAS massal — seluruh santri satu kelas naik ke kelas tujuan.
     *
     * Dua jalur sah (lihat sifatPerpindahan): tahsin → tahfidz, dan sekolah →
     * sekolah bertingkat lebih tinggi. Perpindahan antar kelas SEJAJAR (VII A →
     * VII B, antar halaqoh tahfidz/tahsin) bukan naik kelas — itu pindah kelas,
     * dikerjakan lewat rombak()/papan Perombakan Kelas.
     *
     * @return array{dipindah:int,dilewati:int}
     */
    public function naikKelasMassal(Kelas $sumber, Kelas $tujuan, array $kecuali = [], ?string $tanggal = null, ?string $keterangan = null): array
    {
        if ($sumber->id === $tujuan->id) {
            throw new \DomainException('Kelas tujuan tidak boleh sama dengan kelas sumber.');
        }
        // Gerbang naik kelas memakai sifatPerpindahan yang sama dengan buka(),
        // supaya validasi tingkat kelas (sekolah) dan validasi program
        // (tahsin → tahfidz) tidak pernah berbeda pendapat.
        $sifat = $this->sifatPerpindahan(
            $sumber->jenis, $tujuan->jenis, $sumber->tingkat, $tujuan->tingkat
        );
        if ($sifat !== 'naik') {
            $detil = $sumber->jenis === $tujuan->jenis
                ? "{$sumber->nama} (tingkat " . ($sumber->tingkat ?: '—') . ") → {$tujuan->nama} (tingkat "
                    . ($tujuan->tingkat ?: '—') . ')'
                : "{$sumber->jenis} → {$tujuan->jenis}";
            throw new \DomainException(
                "Itu bukan kenaikan kelas: $detil. Naik kelas hanya dari kelas tahsin ke kelas "
                . 'tahfidz, atau dari kelas sekolah ke tingkat yang lebih tinggi. Untuk memindahkan '
                . 'santri antar kelas sejajar, gunakan Perombakan Kelas.'
            );
        }

        $santriAktif = DB::table('kelas_santri')
            ->where('kelas_id', $sumber->id)->where('is_aktif', true)->pluck('santri_id');

        $dipindah = 0; $dilewati = 0;
        $this->mulai();
        DB::transaction(function () use ($santriAktif, $kecuali, $tujuan, $tanggal, $keterangan, $sumber, &$dipindah, &$dilewati) {
            foreach ($santriAktif as $sid) {
                if (in_array($sid, $kecuali)) { $dilewati++; continue; }
                $this->buka((int) $sid, $tujuan, $tanggal ?? $this->hariIni(), $keterangan, 'naik');
                $dipindah++;
            }
        });
        $this->selesai();

        return ['dipindah' => $dipindah, 'dilewati' => $dilewati];
    }

    /**
     * PEROMBAKAN — pindahkan santri TERPILIH ke halaqoh tujuan masing-masing,
     * sekali simpan.
     *
     * Berbeda dari naikKelasMassal() yang memindah SATU kelas utuh ke SATU
     * tujuan (pola "naik kelas"): perombakan halaqoh tahfidz tidak begitu.
     * Satu halaqoh dipecah ke beberapa halaqoh lain menurut kemampuan santri,
     * dan sebagian tetap di tempat. Dengan alat lama hal itu harus dikerjakan
     * kelas per kelas lewat panel centang — yang juga MENGELUARKAN santri yang
     * tidak dicentang, sehingga sekali lupa mencentang berarti santri terlempar
     * dari halaqohnya.
     *
     * Di sini tidak ada yang dikeluarkan: hanya santri yang disebut yang
     * berpindah, masing-masing ke tujuannya sendiri. Riwayat, aturan satu kelas
     * per slot, penempatan level tahsin, sinkron RamahAnak, dan pemberitahuan
     * pengampu semuanya ikut karena memakai buka() yang sama.
     *
     * @param  array<int,int>  $peta  santri_id => kelas_tujuan_id
     * @return array{dipindah:array, tetap:array, gagal:array}
     * @throws \DomainException
     */
    public function rombak(array $peta, ?string $tanggal = null, ?string $keterangan = null): array
    {
        $peta = collect($peta)
            ->mapWithKeys(fn ($kelasId, $santriId) => [(int) $santriId => (int) $kelasId])
            ->filter(fn ($kelasId, $santriId) => $santriId > 0 && $kelasId > 0);

        if ($peta->isEmpty()) {
            throw new \DomainException('Belum ada santri yang dipilih untuk dipindah.');
        }

        $tujuanList = Kelas::whereIn('id', $peta->values()->unique())->get()->keyBy('id');
        foreach ($peta->values()->unique() as $kid) {
            $k = $tujuanList->get($kid);
            if (!$k) {
                throw new \DomainException("Kelas tujuan #$kid tidak ditemukan.");
            }
            if (!$k->is_aktif) {
                throw new \DomainException("Kelas {$k->nama} nonaktif — aktifkan dulu sebelum menerima santri.");
            }
        }

        $tanggal ??= $this->hariIni();
        $santri   = Santri::aktif()->whereIn('id', $peta->keys())->get(['id', 'nama_lengkap'])->keyBy('id');

        $this->mulai();
        $hasil = DB::transaction(function () use ($peta, $santri, $tujuanList, $tanggal, $keterangan) {
            $dipindah = []; $tetap = []; $gagal = [];

            foreach ($peta as $santriId => $kelasId) {
                $s = $santri->get($santriId);
                if (!$s) {
                    // Santri nonaktif/terhapus tidak dipindah diam-diam.
                    $gagal[] = ['santri_id' => $santriId, 'alasan' => 'Santri tidak aktif atau tidak ditemukan.'];
                    continue;
                }
                $tujuan = $tujuanList->get($kelasId);
                // Mode 'pindah': lintas program (tahsin ↔ tahfidz) ditolak di sini
                // dan harus lewat Naik Kelas. Galatnya dibiarkan naik ke pemanggil
                // agar SELURUH perombakan dibatalkan — satu baris salah tidak boleh
                // menghasilkan pemindahan separuh jalan yang sulit ditelusuri.
                $dari   = $this->buka($santriId, $tujuan, $tanggal, $keterangan ?? 'Perombakan kelas', 'pindah');

                if ($dari === false) {   // sudah anggota aktif kelas itu
                    $tetap[] = ['santri_id' => $santriId, 'nama' => $s->nama_lengkap, 'kelas' => $tujuan->nama];
                } else {
                    $dipindah[] = ['santri_id' => $santriId, 'nama' => $s->nama_lengkap,
                                   'dari' => $dari ?: null, 'ke' => $tujuan->nama];
                }
            }

            return ['dipindah' => $dipindah, 'tetap' => $tetap, 'gagal' => $gagal];
        });
        $this->selesai();

        return $hasil;
    }

    /**
     * Pindahkan beberapa santri terpilih ke SATU kelas tujuan.
     * Jalan pintas rombak() untuk kasus "ambil 8 santri ini, taruh di Tahfidz 2".
     */
    public function pindahTerpilih(array $santriIds, Kelas $tujuan, ?string $tanggal = null, ?string $keterangan = null): array
    {
        $peta = collect($santriIds)->mapWithKeys(fn ($id) => [(int) $id => $tujuan->id])->all();

        return $this->rombak($peta, $tanggal, $keterangan);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Inti
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Buka keanggotaan santri di $tujuan, menutup kelas aktif lain di slot yang sama.
     * @return string|false|null  nama kelas asal bila dipindah; null bila sebelumnya
     *                            tanpa kelas di slot ini; false bila sudah anggota (no-op)
     */
    private function buka(
        int $santriId, Kelas $tujuan, string $tanggal, ?string $keterangan, ?string $mode = null
    ): string|false|null {
        $sudah = DB::table('kelas_santri')->where('santri_id', $santriId)
            ->where('kelas_id', $tujuan->id)->where('is_aktif', true)->exists();
        if ($sudah) return false;

        $lama = DB::table('kelas_santri')->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->where('kelas_santri.santri_id', $santriId)->where('kelas_santri.is_aktif', true)
            ->whereIn('kelas.jenis', $tujuan->jenisSeslot())
            ->get(['kelas_santri.kelas_id', 'kelas.nama', 'kelas.jenis', 'kelas.tingkat']);

        $namaLama    = $lama->isNotEmpty() ? $lama->pluck('nama')->implode(', ') : null;
        $jenisLama   = $lama->first()->jenis ?? null;
        $tingkatLama = $lama->first()->tingkat ?? null;
        $sifat       = $this->sifatPerpindahan(
            $jenisLama, $tujuan->jenis, $tingkatLama, $tujuan->tingkat
        );

        // ── Penegakan aturan, satu tempat untuk semua jalur ─────────────────
        if ($mode === 'pindah' && in_array($sifat, ['naik', 'turun'], true)) {
            $nama = Santri::find($santriId)?->nama_lengkap ?? "santri #$santriId";
            // Dua sebab berbeda, dijelaskan berbeda — admin perlu tahu harus ke mana.
            $alasan = $jenisLama !== $tujuan->jenis
                ? "ada di kelas $jenisLama ($namaLama), sedangkan {$tujuan->nama} adalah kelas "
                    . "{$tujuan->jenis}. Perpindahan tahsin → tahfidz adalah NAIK KELAS (ganti program)"
                : "ada di $namaLama (tingkat $tingkatLama), sedangkan {$tujuan->nama} tingkat "
                    . "{$tujuan->tingkat}. Perpindahan antar tingkat adalah NAIK KELAS";
            throw new \DomainException(
                "$nama $alasan. Pindah kelas hanya untuk kelas sejajar; gunakan tombol Naik Kelas."
            );
        }
        if ($mode === 'naik' && $sifat !== 'naik') {
            $nama = Santri::find($santriId)?->nama_lengkap ?? "santri #$santriId";
            throw new \DomainException(
                "$nama tidak bisa dinaikkan ke {$tujuan->nama}. Naik kelas hanya dari kelas tahsin "
                . 'ke kelas tahfidz, atau dari kelas sekolah ke tingkat yang lebih tinggi'
                . ($jenisLama
                    ? " (santri ini ada di $namaLama, kelas $jenisLama"
                        . ($tingkatLama ? " tingkat $tingkatLama" : '') . ').'
                    : ' (santri ini belum punya kelas di kelompok itu).')
            );
        }

        if ($lama->isNotEmpty()) {
            $this->tutup($santriId, $lama->pluck('kelas_id')->all(), $tanggal);
        }

        // Riwayat diberi label sesuai SIFATNYA, bukan sesuai tombol yang dipakai.
        $keterangan = $this->labelSifat($sifat, $namaLama)
            . ($keterangan ? ' — ' . $keterangan : '');

        $payload = [
            'is_aktif'        => true,
            'tanggal_masuk'   => $tanggal,
            'tanggal_keluar'  => null,
            'tahun_ajaran_id' => $tujuan->tahun_ajaran_id,
            'keterangan'      => $keterangan,
            'updated_at'      => now(),
        ];
        // Satu baris per pasangan (unique kelas_id+santri_id): kembali ke kelas yang
        // pernah diikuti mengaktifkan ulang barisnya.
        $ada = DB::table('kelas_santri')->where('santri_id', $santriId)->where('kelas_id', $tujuan->id)->first();
        if ($ada) {
            DB::table('kelas_santri')->where('id', $ada->id)->update($payload);
        } else {
            DB::table('kelas_santri')->insert($payload + [
                'kelas_id' => $tujuan->id, 'santri_id' => $santriId, 'created_at' => now(),
            ]);
        }

        $this->catat($tujuan->id, $santriId, 'masuk');
        if ($tujuan->jenis === 'tahsin') $this->masukTahsin[$santriId] = $tujuan;
        return $lama->isNotEmpty() ? $lama->pluck('nama')->implode(', ') : null;
    }

    /** Tutup keanggotaan aktif santri di kelas-kelas tsb (riwayat tetap tersimpan). */
    private function tutup(int $santriId, array $kelasIds, string $tanggal): void
    {
        $n = DB::table('kelas_santri')->where('santri_id', $santriId)
            ->whereIn('kelas_id', $kelasIds)->where('is_aktif', true)
            ->update(['is_aktif' => false, 'tanggal_keluar' => $tanggal, 'updated_at' => now()]);
        if ($n) {
            foreach ($kelasIds as $kid) $this->catat((int) $kid, $santriId, 'keluar');
        }
    }

    private function catat(int $kelasId, int $santriId, string $arah): void
    {
        $this->perubahan[$kelasId][$arah][] = $santriId;
        $this->santriBerubah[$santriId] = true;
    }

    private function mulai(): void
    {
        $this->perubahan = [];
        $this->santriBerubah = [];
        $this->masukTahsin = [];
    }

    /**
     * Efek lanjutan SETELAH transaksi selesai.
     *
     * Pencapaian tidak disentuh oleh perpindahan:
     *  - Tahfidz: hafalan, juz, setoran, tasmi' semuanya melekat pada santri_id —
     *    pindah halaqoh langsung lanjut dari pencapaian terakhir.
     *  - Tahsin → tahfidz: tahfidz mulai dari data hafalannya sendiri (baru);
     *    level & nilai tahsin tetap tersimpan.
     *  - Masuk kelas tahsin: level hanya ditempatkan bila santri belum punya
     *    progres tahsin (Santri::tempatkanLevelTahsin).
     */
    private function selesai(): void
    {
        foreach (array_keys($this->santriBerubah) as $sid) {
            $s = Santri::find($sid);
            if (!$s) continue;
            if (isset($this->masukTahsin[$sid])) {
                $s->tempatkanLevelTahsin($this->masukTahsin[$sid]);
            }
            $this->sync->sync($s);         // RamahAnak (outbox)
        }
        $this->kabariPengampu();
        $this->mulai();
    }

    /**
     * Kabari pengampu kelas tahfidz/tahsin yang anggotanya berubah — termasuk
     * kelas ASAL santri yang dipindah, karena pengampu di sana kehilangan santri.
     */
    private function kabariPengampu(): void
    {
        if (!$this->perubahan) return;

        $kelas = Kelas::whereIn('id', array_keys($this->perubahan))
            ->whereIn('jenis', self::JENIS_DIKABARI)->get()->keyBy('id');
        if ($kelas->isEmpty()) return;

        $nama = Santri::whereIn('id', collect($this->perubahan)->flatten()->unique())->pluck('nama_lengkap', 'id');
        $ringkas = function (array $ids) use ($nama): string {
            $n = collect($ids)->unique()->map(fn ($i) => $nama[$i] ?? '—');
            return $n->take(5)->implode(', ') . ($n->count() > 5 ? ' +' . ($n->count() - 5) . ' lainnya' : '');
        };

        foreach ($kelas as $k) {
            $p = $this->perubahan[$k->id];
            $bagian = collect([
                !empty($p['masuk'])  ? count(array_unique($p['masuk'])) . ' masuk: ' . $ringkas($p['masuk']) : null,
                !empty($p['keluar']) ? count(array_unique($p['keluar'])) . ' keluar: ' . $ringkas($p['keluar']) : null,
            ])->filter()->implode(' · ');

            $pengampu = JadwalMengajar::where('kelas_id', $k->id)->where('is_aktif', true)
                ->with('tenagaPendidik.user')->get()
                ->map(fn ($j) => $j->tenagaPendidik?->user)->filter()->unique('id')->values();

            foreach ($pengampu as $u) {
                NotifikasiService::event('kelas.anggota_berubah', [
                    'judul' => "Santri {$k->nama} diperbarui",
                    'pesan' => $bagian . '.',
                    'tipe'  => 'pengumuman',
                    'data'  => ['route' => '/' . $k->jenis],
                    'dedup' => 'anggota-' . $k->id . '-' . md5(json_encode($p)),
                ], [$u]);
            }
        }
    }

    private function hariIni(): string
    {
        return TimezoneHelper::today()->toDateString();
    }
}
