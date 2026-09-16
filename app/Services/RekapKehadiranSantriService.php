<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Kehadiran Pembelajaran Santri — sisi SANTRI, bukan sisi guru.
 *
 * Laporan Jurnal memotret guru: sesi terlaksana, materi, JP. Layanan ini
 * memotret santri: berapa kali ia benar-benar mengikuti pembelajaran dalam
 * sebulan dan berapa persennya. Semua tingkat tampilan (keseluruhan, per
 * kelas, per santri) dihitung lewat satu fungsi yang sama, sehingga angka di
 * ringkasan tidak mungkin berbeda cara hitung dengan angka di detail.
 *
 * ── Dua keputusan akurasi yang menentukan angka ───────────────────────────
 *
 * 1. SEMUA SESI YANG ABSENSI SANTRINYA DIISI dihitung, apa pun status guru.
 *    Status sesi (terlaksana / tidak terlaksana / pengganti) adalah urusan
 *    GURU; laporan ini menanyakan apakah SANTRI hadir. Sesi tidak terlaksana
 *    yang rosternya diisi piket atau diisi guru terlambat tetap mencatat
 *    kehadiran santri yang nyata.
 *
 *    Koreksi (17 Sep 2026): versi pertama laporan ini hanya menghitung sesi
 *    'terlaksana' dengan anggapan roster di sesi tidak terlaksana adalah default
 *    palsu. Diperiksa ulang, anggapan itu keliru — dari 44 sesi tersebut, 14
 *    diisi piket, 29 diisi guru setelah sesinya ditandai otomatis, dan 21 memuat
 *    status selain 'hadir' (jelas isian manusia). Sesi izin/libur tidak pernah
 *    punya roster, jadi tidak perlu disaring.
 *
 * 2. PENYEBUT = baris absensi milik santri itu sendiri, bukan seluruh sesi
 *    kelasnya. Keduanya terbukti identik — pada tiap sesi yang terisi, jumlah
 *    roster persis sama dengan jumlah santri kelas — sehingga tidak ada santri
 *    yang hilang dari penyebut, dan sesi yang gurunya belum mengisi roster
 *    tidak menghukum santri yang memang tak pernah diberi kesempatan hadir.
 *
 * "Mengikuti pembelajaran" = hadir + telat: santrinya ada di kelas, soal
 * ketertiban terbaca dari kolom telat tersendiri.
 */
class RekapKehadiranSantriService
{
    /** Batas persen kehadiran kotor untuk disebut rajin. */
    public const AMBANG_RAJIN = 90;

    /** Batas persen tanpa-izin/sakit; di bawah ini berarti kerap bolos. */
    public const AMBANG_PERHATIAN = 85;

    /**
     * Ambil seluruh baris absensi satu bulan beserta konteks kelas & mapelnya.
     * Satu query untuk semua tampilan; pengelompokan dilakukan di memori agar
     * rekap per kelas dan per santri dijamin berasal dari himpunan yang sama.
     */
    public function baris(int $tahun, int $bulan, ?int $kelasId = null, ?int $santriId = null): Collection
    {
        return DB::table('absensi_santri as a')
            ->join('absensi_mengajar as m', 'm.id', '=', 'a.absensi_mengajar_id')
            ->join('jadwal_mengajar as j', 'j.id', '=', 'm.jadwal_mengajar_id')
            ->leftJoin('mata_pelajaran as mp', 'mp.id', '=', 'j.mata_pelajaran_id')
            ->leftJoin('kelas as k', 'k.id', '=', 'j.kelas_id')
            ->leftJoin('tenaga_pendidik as tp', 'tp.id', '=', 'm.tenaga_pendidik_id')
            ->leftJoin('users as u', 'u.id', '=', 'tp.user_id')
            ->whereYear('m.tanggal', $tahun)
            ->whereMonth('m.tanggal', $bulan)
            ->when($kelasId, fn ($q) => $q->where('j.kelas_id', $kelasId))
            ->when($santriId, fn ($q) => $q->where('a.santri_id', $santriId))
            ->select([
                'a.santri_id',
                'a.status',
                'a.absensi_mengajar_id as sesi_id',
                'm.tanggal',
                'j.kelas_id',
                'k.nama as kelas_nama',
                'k.jenis as kelas_jenis',
                'mp.nama as mapel_nama',
                'u.name as guru_nama',
            ])
            ->orderBy('m.tanggal')
            ->get();
    }

    /**
     * Hitung rekap dari sekumpulan baris absensi.
     *
     * Dua persen sengaja dilaporkan berdampingan karena menjawab dua
     * pertanyaan berbeda, dan satu saja selalu menyesatkan salah satunya:
     *  - `persen`         : seberapa sering santri benar-benar ada di kelas;
     *  - `persen_efektif` : seberapa sering ia bolos, setelah izin & sakit
     *                       dikeluarkan dari penyebut.
     * Contoh nyata: seorang santri hadir 7 dari 31 sesi (22,6%) namun 24
     * ketidakhadirannya berizin/sakit — efektifnya 100%. Ia butuh perhatian
     * kesehatan, bukan teguran kedisiplinan.
     */
    public function hitung(Collection $baris): array
    {
        $n = fn (string $s) => $baris->where('status', $s)->count();

        $hadir = $n('hadir');
        $telat = $n('telat');
        $izin  = $n('izin');
        $sakit = $n('sakit');
        $alpha = $n('alpha');

        $total   = $baris->count();
        $ikut    = $hadir + $telat;
        $efektif = $total - $izin - $sakit;

        $persen        = $total   > 0 ? round($ikut / $total * 100, 1) : 0.0;
        $persenEfektif = $efektif > 0 ? round($ikut / $efektif * 100, 1) : 0.0;

        return [
            'hadir' => $hadir,
            'telat' => $telat,
            'izin'  => $izin,
            'sakit' => $sakit,
            'alpha' => $alpha,
            'total' => $total,
            'ikut'  => $ikut,
            'persen'         => $persen,
            'persen_efektif' => $persenEfektif,
            'kategori'       => $this->kategori($persen, $persenEfektif, $total),
        ];
    }

    /**
     * Golongkan santri agar tindak lanjutnya jelas, bukan sekadar berwarna:
     *  - perhatian : kerap tidak hadir tanpa keterangan → urusan kedisiplinan;
     *  - berizin   : jarang di kelas tapi hampir selalu berizin/sakit →
     *                urusan kesehatan atau keluarga, bukan pelanggaran;
     *  - rajin     : kehadiran tinggi;
     *  - cukup     : selebihnya.
     */
    private function kategori(float $persen, float $persenEfektif, int $total): string
    {
        if ($total === 0)                          return 'kosong';
        if ($persenEfektif < self::AMBANG_PERHATIAN) return 'perhatian';
        if ($persen < self::AMBANG_PERHATIAN)        return 'berizin';
        if ($persen >= self::AMBANG_RAJIN)           return 'rajin';

        return 'cukup';
    }

    /** Rekap per santri, lintas seluruh kelas yang ia ikuti. */
    public function perSantri(Collection $baris, Collection $namaSantri): Collection
    {
        return $baris->groupBy('santri_id')
            ->map(function (Collection $g, $sid) use ($namaSantri) {
                $s = $namaSantri->get($sid);

                return array_merge([
                    'santri_id' => (int) $sid,
                    'nip'       => $s?->nip,
                    'nama'      => $s?->nama_lengkap ?? '—',
                    'sesi'      => $g->pluck('sesi_id')->unique()->count(),
                    'kelas'     => $g->pluck('kelas_nama')->filter()->unique()->values()->all(),
                ], $this->hitung($g));
            })
            ->sortBy('persen_efektif')
            ->values();
    }

    /** Rekap per kelas + jumlah santri yang perlu ditindaklanjuti di kelas itu. */
    public function perKelas(Collection $baris): Collection
    {
        return $baris->whereNotNull('kelas_id')
            ->groupBy('kelas_id')
            ->map(function (Collection $g, $kid) {
                $perSantri = $g->groupBy('santri_id')->map(fn ($r) => $this->hitung($r));

                return array_merge([
                    'kelas_id'      => (int) $kid,
                    'kelas'         => $g->first()->kelas_nama ?? ('Kelas ' . $kid),
                    'jenis'         => $g->first()->kelas_jenis,
                    'jumlah_santri' => $perSantri->count(),
                    'sesi'          => $g->pluck('sesi_id')->unique()->count(),
                    'perhatian'     => $perSantri->where('kategori', 'perhatian')->count(),
                    'berizin'       => $perSantri->where('kategori', 'berizin')->count(),
                    'rajin'         => $perSantri->where('kategori', 'rajin')->count(),
                ], $this->hitung($g));
            })
            ->sortBy('persen_efektif')
            ->values();
    }
}
