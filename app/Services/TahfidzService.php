<?php

namespace App\Services;

use App\Models\SetoranTahfidz;
use App\Models\HafalanSantri;
use App\Models\HafalanJuz;
use App\Models\SettingTahfidz;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Logika inti Smart Tahfidz: pencatatan setoran + tracking hafalan.
 * Gerbang:
 *  - murojaah wajib  : setelah ziyadah, ziyadah berikutnya dikunci sampai murojaah wajib.
 *  - tasmi' per juz   : saat 1 juz penuh → status 'selesai' (wajib tasmi'); lulus → 'tasmi_lulus'.
 */
class TahfidzService
{
    /** Ambang lulus fallback bila setting belum ada. */
    public const NILAI_LULUS = 7.0;

    /** Ambang lulus KHUSUS tasmi' (rata-rata 4 rubrik). Tetap 8, terpisah dari setoran biasa. */
    public const NILAI_LULUS_TASMI = 8.0;

    private QuranReferenceService $quran;
    private float $nilaiLulus;

    public function __construct(?QuranReferenceService $quran = null)
    {
        $this->quran      = $quran ?? new QuranReferenceService();
        $this->nilaiLulus = (float) (SettingTahfidz::get()->nilai_lulus ?? self::NILAI_LULUS);
    }

    /**
     * Catat satu setoran + update tracking. Lempar exception bila melanggar gerbang/validasi.
     */
    public function catatSetoran(array $d): SetoranTahfidz
    {
        $hafalan = HafalanSantri::firstOrCreate(
            ['santri_id' => $d['santri_id']],
            ['total_ayat' => 0, 'perlu_murojaah' => false]
        );
        $jenis = $d['jenis'];

        // ── Tentukan rentang sesuai jenis ──────────────────────────────────
        if ($jenis === 'murojaah_wajib') {
            // Otomatis = rentang ziyadah TERAKHIR (surat & ayat sama).
            if (!$hafalan->last_surah) {
                throw new \DomainException('Belum ada hafalan baru yang bisa dimurojaah.');
            }
            $sMulai   = (int) $hafalan->last_surah;
            $aMulai   = (int) $hafalan->last_ayat_mulai;
            $sSelesai = (int) ($hafalan->last_surah_selesai ?? $hafalan->last_surah);
            $aSelesai = (int) $hafalan->last_ayat_selesai;
        } elseif ($jenis === 'tasmi') {
            // Hanya boleh jika ada juz yang SELESAI; rentang = batas juz tsb.
            $juz = isset($d['juz']) ? (int) $d['juz'] : 0;
            if ($juz < 1) {
                throw new \DomainException("Pilih juz yang akan ditasmi'.");
            }
            $hj = HafalanJuz::where('santri_id', $d['santri_id'])->where('juz', $juz)->first();
            if (!$hj || $hj->status !== 'selesai') {
                throw new \DomainException("Tasmi' juz $juz belum bisa: selesaikan dulu 1 juz penuh.");
            }
            [$sMulai, $aMulai, $sSelesai, $aSelesai] = $this->quran->juzRange($juz);
        } else { // ziyadah | murojaah_tambahan → rentang dari input guru
            $sMulai   = (int) ($d['surah_mulai'] ?? 0);
            $aMulai   = (int) ($d['ayat_mulai'] ?? 0);
            $sSelesai = (int) ($d['surah_selesai'] ?? 0);
            $aSelesai = (int) ($d['ayat_selesai'] ?? 0);

            // Default cerdas (mendukung lintas-surah & "surah penuh"):
            //  - surah selesai kosong  → sama dgn surah mulai (1 surah)
            //  - ayat mulai kosong     → 1 (awal surah)
            //  - ayat selesai kosong   → akhir surah selesai (surah penuh)
            // Contoh: "Abasa 1 → At-Takwir (kosong)" = Abasa 1 s/d akhir At-Takwir;
            //         "Abasa (kosong) → At-Takwir 8"  = Abasa 1 s/d At-Takwir 8.
            if ($sSelesai < 1) $sSelesai = $sMulai;
            if ($aMulai   < 1) $aMulai   = 1;
            if ($aSelesai < 1) $aSelesai = $this->quran->jumlahAyatSurah($sSelesai);

            if (!$this->quran->ayatValid($sMulai, $aMulai) || !$this->quran->ayatValid($sSelesai, $aSelesai)) {
                throw new \InvalidArgumentException('Rentang surah/ayat tidak valid. Pastikan surah dipilih dan nomor ayat sesuai.');
            }
        }

        $start = $this->quran->posisiAbsolut($sMulai, $aMulai);
        $end   = $this->quran->posisiAbsolut($sSelesai, $aSelesai);
        if ($end < $start) {
            throw new \InvalidArgumentException('Akhir rentang berada sebelum awal.');
        }

        $jumlah     = $this->quran->hitungAyat($sMulai, $aMulai, $sSelesai, $aSelesai);
        $juzMulai   = $this->quran->juzDari($sMulai, $aMulai);
        $juzSelesai = $this->quran->juzDari($sSelesai, $aSelesai);

        // ── Hafalan baru tak boleh MENIMPA ayat yang sudah dihafal ──────────────
        // FIX: deteksi TUMPANG-TINDIH (bukan frontier linear), agar mendukung
        // hafidz non-berurutan (mis. juz 30/juz-amma dulu, lalu Juz 1). Hafalan baru
        // boleh mulai di region mana pun ASAL tidak overlap dengan yang sudah dihafal.
        // (Untuk mengulang ayat lama gunakan Murojaah, bukan Hafalan Baru.)
        if ($jenis === 'ziyadah') {
            $terpakai = SetoranTahfidz::where('santri_id', $d['santri_id'])
                ->where('jenis', 'ziyadah')->where('lulus', true)
                ->get(['surah_mulai', 'ayat_mulai', 'surah_selesai', 'ayat_selesai']);
            foreach ($terpakai as $t) {
                $ts = $this->quran->posisiAbsolut((int) $t->surah_mulai, (int) $t->ayat_mulai);
                $te = $this->quran->posisiAbsolut((int) $t->surah_selesai, (int) $t->ayat_selesai);
                if ($start <= $te && $end >= $ts) { // overlap
                    $nm = \App\Models\Surah::whereIn('nomor', [(int) $t->surah_mulai, (int) $t->surah_selesai])
                        ->pluck('nama', 'nomor');
                    throw new \DomainException(
                        'Sebagian ayat ini SUDAH dihafal ('
                        . ($nm[(int) $t->surah_mulai] ?? "Surah {$t->surah_mulai}") . " {$t->ayat_mulai} – "
                        . ($nm[(int) $t->surah_selesai] ?? "Surah {$t->surah_selesai}") . " {$t->ayat_selesai}). "
                        . 'Pilih rentang yang belum dihafal, atau gunakan Murojaah untuk mengulang.'
                    );
                }
            }
        }

        // ── Gerbang murojaah wajib untuk ziyadah ───────────────────────────
        if ($jenis === 'ziyadah' && $hafalan->perlu_murojaah) {
            throw new \DomainException(
                'Santri wajib menyelesaikan murojaah hafalan terakhir dulu sebelum menambah hafalan baru.'
            );
        }

        $nilai = isset($d['nilai']) && $d['nilai'] !== null ? (float) $d['nilai'] : null;
        // Tasmi' pakai ambang khusus (8); jenis lain pakai ambang setoran (setting/7).
        $ambangLulus = $jenis === 'tasmi' ? self::NILAI_LULUS_TASMI : $this->nilaiLulus;
        $lulus = $nilai !== null ? ($nilai >= $ambangLulus) : null;

        // Hafalan baru WAJIB dinilai: nilai penentu apakah batas hafalan maju (lulus) atau diulang.
        if ($jenis === 'ziyadah' && $nilai === null) {
            throw new \InvalidArgumentException('Nilai wajib diisi untuk hafalan baru (penentu lulus ≥ ambang ' . $this->nilaiLulus . ').');
        }

        return DB::transaction(function () use (
            $d, $jenis, $sMulai, $aMulai, $sSelesai, $aSelesai,
            $jumlah, $juzMulai, $juzSelesai, $nilai, $lulus, $hafalan
        ) {
            $setoran = SetoranTahfidz::create([
                'absensi_mengajar_id' => $d['absensi_mengajar_id'] ?? null,
                'santri_id'           => $d['santri_id'],
                'tenaga_pendidik_id'  => $d['tenaga_pendidik_id'],
                'tanggal'             => $d['tanggal'] ?? Carbon::today()->toDateString(),
                'jenis'               => $jenis,
                'surah_mulai'         => $sMulai,
                'ayat_mulai'          => $aMulai,
                'surah_selesai'       => $sSelesai,
                'ayat_selesai'        => $aSelesai,
                'jumlah_ayat'         => $jumlah,
                'juz_mulai'           => $juzMulai,
                'juz_selesai'         => $juzSelesai,
                'nilai'               => $nilai,
                'lulus'               => $lulus,
                'catatan'             => $d['catatan'] ?? null,
            ]);

            switch ($jenis) {
                case 'ziyadah':
                    // Batas hafalan HANYA maju bila LULUS (nilai ≥ ambang).
                    // Bila belum lulus: hanya tercatat sebagai riwayat → santri boleh mengulang
                    // ayat yang sama sebagai hafalan baru sampai lulus.
                    if ($lulus === true) {
                        $hafalan->total_ayat        += $jumlah;
                        $hafalan->perlu_murojaah     = true; // aktifkan gerbang
                        $hafalan->last_surah         = $sMulai;
                        $hafalan->last_surah_selesai = $sSelesai;
                        $hafalan->last_ayat_mulai    = $aMulai;
                        $hafalan->last_ayat_selesai  = $aSelesai;
                        $hafalan->last_juz           = $juzSelesai;
                        $hafalan->save();

                        foreach ($this->quran->pecahPerJuz($sMulai, $aMulai, $sSelesai, $aSelesai) as $juz => $cnt) {
                            $hj = HafalanJuz::firstOrCreate(
                                ['santri_id' => $d['santri_id'], 'juz' => $juz],
                                ['ayat_terkumpul' => 0, 'jumlah_ayat_juz' => $this->quran->jumlahAyatJuz($juz), 'status' => 'berjalan']
                            );
                            $hj->ayat_terkumpul = min($hj->jumlah_ayat_juz, $hj->ayat_terkumpul + $cnt);
                            if ($hj->ayat_terkumpul >= $hj->jumlah_ayat_juz && $hj->status === 'berjalan') {
                                $hj->status = 'selesai'; // wajib tasmi'
                            }
                            $hj->save();
                        }
                    }
                    break;

                case 'murojaah_wajib':
                    $hafalan->perlu_murojaah = false; // buka gerbang
                    $hafalan->save();
                    break;

                case 'tasmi':
                    if ($lulus === true) {
                        HafalanJuz::where('santri_id', $d['santri_id'])
                            ->where('juz', $juzMulai)
                            ->update(['status' => 'tasmi_lulus']);
                    }
                    break;

                // murojaah_tambahan: cukup tercatat sebagai laporan.
            }

            return $setoran;
        });
    }

    /** Ringkasan tracking hafalan santri (untuk UI/laporan). */
    public function statusSantri(int $santriId): array
    {
        $hafalan = HafalanSantri::firstOrCreate(
            ['santri_id' => $santriId],
            ['total_ayat' => 0, 'perlu_murojaah' => false]
        );
        $totalQuran = $this->quran->totalAyatQuran();
        $juz = HafalanJuz::where('santri_id', $santriId)->orderBy('juz')->get();

        // Posisi "lanjut dari" untuk hafalan baru berikutnya (setelah hafalan terakhir).
        $lanjut = null;
        if ($hafalan->last_surah) {
            $batasSurah = (int) ($hafalan->last_surah_selesai ?? $hafalan->last_surah);
            $batasPos   = $this->quran->posisiAbsolut($batasSurah, (int) ($hafalan->last_ayat_selesai ?? 0));
            if ($batasPos < $totalQuran) {
                [$ns, $na] = $this->quran->posisiKeSurahAyat($batasPos + 1);
                $lanjut = ['surah' => $ns, 'ayat' => $na];
            }
        }

        return [
            'total_ayat'        => $hafalan->total_ayat,
            'total_ayat_quran'  => $totalQuran,
            'persen'            => $totalQuran > 0 ? round($hafalan->total_ayat / $totalQuran * 100, 2) : 0,
            'perlu_murojaah'    => $hafalan->perlu_murojaah,
            'last'              => $hafalan->last_surah ? [
                'surah'        => $hafalan->last_surah,
                'surah_selesai'=> $hafalan->last_surah_selesai ?? $hafalan->last_surah,
                'ayat_mulai'   => $hafalan->last_ayat_mulai,
                'ayat_selesai' => $hafalan->last_ayat_selesai,
                'juz'          => $hafalan->last_juz,
            ] : null,
            'lanjut_dari'       => $lanjut, // {surah, ayat} saran hafalan baru; null bila kursor di akhir mushaf
            'selesai_semua'     => $hafalan->total_ayat >= $totalQuran, // benar2 30 juz (independen urutan)
            'juz_perlu_tasmi'   => $juz->where('status', 'selesai')->pluck('juz')->values(),
            'juz_selesai_total' => $juz->whereIn('status', ['selesai', 'tasmi_lulus'])->count(),
            'juz'               => $juz->map(fn($j) => [
                'juz'             => $j->juz,
                'ayat_terkumpul'  => $j->ayat_terkumpul,
                'jumlah_ayat_juz' => $j->jumlah_ayat_juz,
                'status'          => $j->status,
            ])->values(),
        ];
    }

    /**
     * KOREKSI ADMIN — perbaiki pencapaian awal WALAU santri sudah setoran.
     *
     * Tidak menambal, melainkan MEMBANGUN ULANG dari dua sumber kebenaran:
     *   1. baseline yang diberikan admin (juz lulus + posisi terakhir), lalu
     *   2. seluruh setoran ziyadah LULUS yang sudah ada, diputar ulang menurut
     *      urutan tanggal, ditambah juz yang sudah lulus tasmi'.
     *
     * Baris `setoran_tahfidz` TIDAK disentuh — itu riwayat asli apa yang benar-
     * benar disetorkan. Yang ditulis ulang hanya tabel turunannya
     * (`hafalan_juz` & `hafalan_santri`), sehingga hasilnya selalu konsisten
     * dengan riwayat dan tidak mungkin terhitung ganda: `total_ayat` diambil
     * dari jumlah ayat terkumpul tiap juz (sudah dibatasi kapasitas juz),
     * bukan dari penjumlahan bebas.
     *
     * @param  bool $simulasi  true = hitung & kembalikan pratinjau, lalu batalkan.
     */
    public function bangunUlangPencapaian(
        int $santriId, array $juzLulus, ?int $lastSurah = null, ?int $lastAyat = null,
        ?string $pola = null, bool $simulasi = false
    ): array {
        $hafLama = HafalanSantri::where('santri_id', $santriId)->first();
        $sebelum = [
            'total_ayat' => (int) ($hafLama->total_ayat ?? 0),
            'juz'        => HafalanJuz::where('santri_id', $santriId)->orderBy('juz')->pluck('juz')->all(),
        ];

        $juzLulus = collect($juzLulus)->map(fn($j) => (int) $j)
            ->filter(fn($j) => $j >= 1 && $j <= 30)->unique()->values()->all();

        $partialJuz = null;
        if ($lastSurah && $lastAyat) {
            if (!$this->quran->ayatValid($lastSurah, $lastAyat)) {
                throw new \DomainException('Surah/ayat terakhir tidak valid.');
            }
            $partialJuz = $this->quran->juzDari($lastSurah, $lastAyat);
            if ($pola) {
                foreach (self::urutanJuz($pola) as $j) {
                    if ($j === $partialJuz) break;
                    $juzLulus[] = $j;
                }
                $juzLulus = array_values(array_unique($juzLulus));
            }
            // Juz berjalan tidak boleh ikut daftar lulus.
            $juzLulus = array_values(array_diff($juzLulus, [$partialJuz]));
        }

        if (empty($juzLulus) && !$partialJuz) {
            throw new \DomainException('Isi minimal satu juz lulus atau posisi terakhir (surah & ayat).');
        }

        DB::beginTransaction();
        try {
            HafalanJuz::where('santri_id', $santriId)->delete();

            // ── 1. Baseline dari admin ────────────────────────────────────
            foreach ($juzLulus as $juz) {
                $full = $this->quran->jumlahAyatJuz($juz);
                HafalanJuz::create(['santri_id' => $santriId, 'juz' => $juz,
                    'ayat_terkumpul' => $full, 'jumlah_ayat_juz' => $full, 'status' => 'tasmi_lulus']);
            }
            if ($partialJuz) {
                [$sM, $aM] = $this->quran->juzRange($partialJuz);
                $full  = $this->quran->jumlahAyatJuz($partialJuz);
                $count = min($this->quran->hitungAyat($sM, $aM, $lastSurah, $lastAyat), $full);
                HafalanJuz::create(['santri_id' => $santriId, 'juz' => $partialJuz,
                    'ayat_terkumpul' => $count, 'jumlah_ayat_juz' => $full,
                    'status' => $count >= $full ? 'selesai' : 'berjalan']);
            }

            // ── 2. Putar ulang setoran ziyadah yang LULUS ─────────────────
            $ziyadah = SetoranTahfidz::where('santri_id', $santriId)
                ->where('jenis', 'ziyadah')->where('lulus', true)
                ->orderBy('tanggal')->orderBy('id')->get();

            foreach ($ziyadah as $z) {
                foreach ($this->quran->pecahPerJuz(
                    (int) $z->surah_mulai, (int) $z->ayat_mulai,
                    (int) $z->surah_selesai, (int) $z->ayat_selesai
                ) as $juz => $cnt) {
                    $hj = HafalanJuz::firstOrCreate(
                        ['santri_id' => $santriId, 'juz' => $juz],
                        ['ayat_terkumpul' => 0, 'jumlah_ayat_juz' => $this->quran->jumlahAyatJuz($juz), 'status' => 'berjalan']
                    );
                    // Juz yang sudah penuh dari baseline tidak bertambah lagi —
                    // inilah yang mencegah hitung ganda saat rentangnya beririsan.
                    if ($hj->status === 'tasmi_lulus') continue;

                    $hj->ayat_terkumpul = min($hj->jumlah_ayat_juz, $hj->ayat_terkumpul + $cnt);
                    if ($hj->ayat_terkumpul >= $hj->jumlah_ayat_juz && $hj->status === 'berjalan') {
                        $hj->status = 'selesai';
                    }
                    $hj->save();
                }
            }

            // ── 3. Juz yang sudah lulus tasmi' tetap lulus ────────────────
            $juzTasmi = SetoranTahfidz::where('santri_id', $santriId)
                ->where('jenis', 'tasmi')->where('lulus', true)
                ->pluck('juz_selesai')->filter()->unique();
            foreach ($juzTasmi as $juz) {
                $full = $this->quran->jumlahAyatJuz((int) $juz);
                HafalanJuz::updateOrCreate(['santri_id' => $santriId, 'juz' => (int) $juz],
                    ['ayat_terkumpul' => $full, 'jumlah_ayat_juz' => $full, 'status' => 'tasmi_lulus']);
            }

            // ── 4. Total & kursor ─────────────────────────────────────────
            $totalAyat = (int) HafalanJuz::where('santri_id', $santriId)->sum('ayat_terkumpul');

            $haf = HafalanSantri::firstOrCreate(['santri_id' => $santriId],
                ['total_ayat' => 0, 'perlu_murojaah' => false]);

            // Kursor "lanjut dari" mengikuti ziyadah TERAKHIR bila ada — bukan
            // baseline — supaya gerbang murojaah & saran ayat berikutnya tetap
            // menunjuk hafalan terbaru santri. Flag perlu_murojaah sengaja
            // dibiarkan apa adanya: itu keadaan gerbang yang sedang berjalan,
            // tidak ada hubungannya dengan baseline.
            $akhir = $ziyadah->last();
            if ($akhir) {
                $haf->update(['total_ayat' => $totalAyat,
                    'last_surah' => $akhir->surah_mulai, 'last_surah_selesai' => $akhir->surah_selesai,
                    'last_ayat_mulai' => $akhir->ayat_mulai, 'last_ayat_selesai' => $akhir->ayat_selesai,
                    'last_juz' => $akhir->juz_selesai]);
            } elseif ($partialJuz) {
                [$sM, $aM] = $this->quran->juzRange($partialJuz);
                $haf->update(['total_ayat' => $totalAyat,
                    'last_surah' => $lastSurah, 'last_surah_selesai' => $lastSurah,
                    'last_ayat_mulai' => $aM, 'last_ayat_selesai' => $lastAyat, 'last_juz' => $partialJuz]);
            } else {
                [$sM, $aM, $sS, $aS] = $this->quran->juzRange(max($juzLulus));
                $haf->update(['total_ayat' => $totalAyat,
                    'last_surah' => $sS, 'last_surah_selesai' => $sS,
                    'last_ayat_mulai' => $aM, 'last_ayat_selesai' => $aS, 'last_juz' => max($juzLulus)]);
            }

            $sesudah = [
                'total_ayat' => $totalAyat,
                'juz'        => HafalanJuz::where('santri_id', $santriId)->orderBy('juz')->pluck('juz')->all(),
            ];

            if ($simulasi) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return [
                'simulasi'        => $simulasi,
                'sebelum'         => $sebelum,
                'sesudah'         => $sesudah,
                'juz_baseline'    => count($juzLulus),
                'partial_juz'     => $partialJuz,
                'setoran_diputar' => $ziyadah->count(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** Urutan hafalan yang dipakai kelas — dasar penurunan juz dari posisi terakhir. */
    public const POLA = ['belakang', 'amma_maju', 'depan'];

    /**
     * Daftar juz menurut urutan hafalan.
     *  belakang  : 30, 29, 28 … 1
     *  amma_maju : 30, 29, lalu 1, 2, 3 … 28   (paling umum di An-Nur)
     *  depan     : 1, 2, 3 … 30
     *
     * @return int[]
     */
    public static function urutanJuz(string $pola): array
    {
        if ($pola === 'depan')     return range(1, 30);
        if ($pola === 'amma_maju') return array_merge([30, 29], range(1, 28));

        return range(30, 1);
    }

    /**
     * Sinkronisasi pencapaian AWAL santri (seeding) — untuk migrasi data hafalan
     * yang sudah berjalan sebelum sistem dipakai.
     *  - Juz pada $juzLulus → status 'tasmi_lulus' (ayat penuh).
     *  - Posisi tengah (last surah+ayat) → juz tsb 'berjalan' (ayat dari awal juz s/d posisi),
     *    semua ayat sebelum posisi dianggap ACC (nilai lulus 8). Kursor di-set ke posisi ini.
     * Hanya untuk santri yang BELUM punya data hafalan (anti-timpa). Tanpa baris riwayat setoran.
     *
     * @param int[]       $juzLulus
     * @param string|null $pola   Urutan hafalan kelas ini: 'belakang' (30,29,28…),
     *                            'amma_maju' (30,29, lalu 1,2,3…) atau 'depan' (1,2,3…).
     *                            Bila diisi bersama posisi terakhir, juz-juz SEBELUM posisi
     *                            menurut pola ikut dihitung lulus — lihat catatan di bawah.
     * @param bool        $ganti  Koreksi: buang hasil seed lama lalu tulis ulang.
     */
    public function seedPencapaian(
        int $santriId, array $juzLulus, ?int $lastSurah = null, ?int $lastAyat = null,
        ?string $pola = null, bool $ganti = false
    ): array
    {
        // Guard: cegah TIMPA / hitung ganda, bukan sekadar "pernah ada setoran".
        //
        // Yang benar-benar berbahaya hanyalah hafalan yang SUDAH masuk hitungan:
        // ziyadah LULUS (satu-satunya jalur yang menambah total_ayat & membuat
        // HafalanJuz). Setoran murojaah TIDAK menyentuh total_ayat sama sekali.
        //
        // Guard lama menolak setoran apa pun, sehingga santri yang baru murojaah
        // — justru murojaah hafalan LAMA yang belum pernah dicatat — terkunci
        // selamanya dan pencapaian awalnya mustahil dimasukkan.
        $adaJuz = HafalanJuz::where('santri_id', $santriId)->exists();
        $adaZiyadahLulus = SetoranTahfidz::where('santri_id', $santriId)
            ->where('jenis', 'ziyadah')->where('lulus', true)->exists();
        $haf = HafalanSantri::where('santri_id', $santriId)->first();

        if ($adaZiyadahLulus) {
            throw new \DomainException('Santri sudah punya setoran ziyadah yang lulus — pencapaian awalnya tidak bisa ditulis ulang dari sini. Perbaiki lewat admin.');
        }

        if ($adaJuz || ($haf && $haf->last_surah)) {
            if (!$ganti) {
                throw new \DomainException('Santri sudah punya pencapaian tercatat — pilih "Koreksi" bila ingin memperbaikinya.');
            }
            // Koreksi: seluruh isi lama murni hasil seed (tidak ada ziyadah lulus),
            // jadi aman dibuang lalu ditulis ulang. Tanpa ini guru terkunci pada
            // angka yang salah dan harus menunggu admin.
            HafalanJuz::where('santri_id', $santriId)->delete();
            HafalanSantri::where('santri_id', $santriId)->update([
                'total_ayat' => 0, 'perlu_murojaah' => false,
                'last_surah' => null, 'last_surah_selesai' => null,
                'last_ayat_mulai' => null, 'last_ayat_selesai' => null, 'last_juz' => null,
            ]);
        }

        $juzLulus = collect($juzLulus)->map(fn($j) => (int) $j)
            ->filter(fn($j) => $j >= 1 && $j <= 30)->unique()->values()->all();

        // Validasi posisi tengah.
        $partialJuz = null;
        if ($lastSurah && $lastAyat) {
            if (!$this->quran->ayatValid($lastSurah, $lastAyat)) {
                throw new \DomainException('Surah/ayat terakhir tidak valid.');
            }
            $partialJuz = $this->quran->juzDari($lastSurah, $lastAyat);

            // Posisi terakhir berarti santri sudah MELEWATI juz-juz sebelumnya
            // menurut urutan hafalan kelasnya. Tanpa ini, guru yang hanya mengisi
            // posisi ("santri sampai sini") menghasilkan progres nyaris kosong:
            // hanya ayat dari awal juz itu yang terhitung, sedangkan juz 30/29 dst
            // yang sudah dihafal lebih dulu hilang.
            if ($pola) {
                foreach (self::urutanJuz($pola) as $j) {
                    if ($j === $partialJuz) break;      // berhenti tepat di juz berjalan
                    $juzLulus[] = $j;
                }
                $juzLulus = array_values(array_unique($juzLulus));
            }

            if (in_array($partialJuz, $juzLulus, true)) {
                throw new \DomainException("Juz {$partialJuz} sudah dicentang lulus; jangan isi posisi tengah di juz yang sama.");
            }
        }

        if (empty($juzLulus) && !$partialJuz) {
            throw new \DomainException('Isi minimal satu juz lulus atau posisi terakhir (surah & ayat).');
        }

        return DB::transaction(function () use ($santriId, $juzLulus, $lastSurah, $lastAyat, $partialJuz) {
            $totalAyat = 0;

            // Juz lulus → tasmi_lulus penuh.
            foreach ($juzLulus as $juz) {
                $full = $this->quran->jumlahAyatJuz($juz);
                HafalanJuz::updateOrCreate(
                    ['santri_id' => $santriId, 'juz' => $juz],
                    ['ayat_terkumpul' => $full, 'jumlah_ayat_juz' => $full, 'status' => 'tasmi_lulus']
                );
                $totalAyat += $full;
            }

            // Posisi tengah → juz berjalan (ayat dari awal juz s/d posisi).
            if ($partialJuz) {
                [$sM, $aM] = $this->quran->juzRange($partialJuz);
                $count = $this->quran->hitungAyat($sM, $aM, $lastSurah, $lastAyat);
                $full  = $this->quran->jumlahAyatJuz($partialJuz);
                $count = min($count, $full);
                HafalanJuz::updateOrCreate(
                    ['santri_id' => $santriId, 'juz' => $partialJuz],
                    ['ayat_terkumpul' => $count, 'jumlah_ayat_juz' => $full,
                     'status' => $count >= $full ? 'selesai' : 'berjalan']
                );
                $totalAyat += $count;
            }

            // Kursor hafalan_santri (untuk "lanjut dari").
            $haf = HafalanSantri::firstOrCreate(['santri_id' => $santriId], ['total_ayat' => 0, 'perlu_murojaah' => false]);
            $upd = ['total_ayat' => $totalAyat];
            if ($partialJuz) {
                [$sM, $aM] = $this->quran->juzRange($partialJuz);
                $upd += ['last_surah' => $lastSurah, 'last_surah_selesai' => $lastSurah,
                         'last_ayat_mulai' => $aM, 'last_ayat_selesai' => $lastAyat, 'last_juz' => $partialJuz];
            } else {
                $maxJuz = max($juzLulus);
                [$sM, $aM, $sS, $aS] = $this->quran->juzRange($maxJuz);
                $upd += ['last_surah' => $sS, 'last_surah_selesai' => $sS,
                         'last_ayat_mulai' => $aM, 'last_ayat_selesai' => $aS, 'last_juz' => $maxJuz];
            }
            $haf->update($upd);

            return [
                'juz_lulus'   => count($juzLulus),
                'partial_juz' => $partialJuz,
                'total_ayat'  => $totalAyat,
            ];
        });
    }
}
