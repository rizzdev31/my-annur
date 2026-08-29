<?php

namespace App\Services;

use App\Models\Surah;
use App\Models\Juz;

/**
 * Referensi struktur Al-Qur'an untuk Smart Tahfidz.
 * - Hitung jumlah ayat dari rentang (lintas surah, urutan mushaf).
 * - Deteksi juz dari posisi ayat (setoran tidak harus urut/mulai Al-Baqarah).
 * - Info juz (jumlah ayat, posisi awal/akhir) untuk gerbang tasmi'.
 */
class QuranReferenceService
{
    private array $offset      = []; // nomor surah => jumlah ayat SEBELUM surah ini
    private array $surahAyat   = []; // nomor surah => jumlah ayat
    private array $juzStartPos = []; // nomor juz   => posisi absolut awal juz (1-based)
    private array $juzMarker   = []; // nomor juz   => [surah, ayat]
    private int   $total       = 0;

    public function __construct()
    {
        $cum = 0;
        foreach (Surah::orderBy('nomor')->get(['nomor', 'jumlah_ayat']) as $s) {
            $this->surahAyat[$s->nomor] = $s->jumlah_ayat;
            $this->offset[$s->nomor]    = $cum;
            $cum += $s->jumlah_ayat;
        }
        $this->total = $cum;

        foreach (Juz::orderBy('nomor')->get() as $j) {
            $this->juzMarker[$j->nomor]   = [$j->mulai_surah, $j->mulai_ayat];
            $this->juzStartPos[$j->nomor] = $this->posisiAbsolut($j->mulai_surah, $j->mulai_ayat);
        }
    }

    /** Total ayat Al-Qur'an (patokan jumlah hafalan) = 6236. */
    public function totalAyatQuran(): int
    {
        return $this->total;
    }

    /** Posisi absolut sebuah ayat dalam mushaf (1-based). */
    public function posisiAbsolut(int $surah, int $ayat): int
    {
        return ($this->offset[$surah] ?? 0) + $ayat;
    }

    /** Jumlah ayat dalam rentang [surahMulai:ayatMulai .. surahSelesai:ayatSelesai]. */
    public function hitungAyat(int $sMulai, int $aMulai, int $sSelesai, int $aSelesai): int
    {
        $start = $this->posisiAbsolut($sMulai, $aMulai);
        $end   = $this->posisiAbsolut($sSelesai, $aSelesai);
        return max(0, $end - $start + 1);
    }

    /** Juz tempat sebuah ayat berada. */
    public function juzDari(int $surah, int $ayat): int
    {
        $pos    = $this->posisiAbsolut($surah, $ayat);
        $hasil  = 1;
        foreach ($this->juzStartPos as $nomor => $startPos) {
            if ($pos >= $startPos) {
                $hasil = $nomor;
            } else {
                break;
            }
        }
        return $hasil;
    }

    /** Info satu juz: posisi awal/akhir + jumlah ayat (untuk gerbang tasmi'). */
    public function infoJuz(int $juz): array
    {
        $startPos = $this->juzStartPos[$juz] ?? 1;
        $endPos   = $this->juzStartPos[$juz + 1] ?? ($this->total + 1);
        $endPos   = isset($this->juzStartPos[$juz + 1]) ? $endPos - 1 : $this->total;

        return [
            'nomor'       => $juz,
            'mulai'       => $this->juzMarker[$juz] ?? [1, 1],
            'mulai_pos'   => $startPos,
            'selesai_pos' => $endPos,
            'jumlah_ayat' => max(0, $endPos - $startPos + 1),
        ];
    }

    /**
     * Pecah sebuah rentang menjadi jumlah ayat per juz (untuk progres juz).
     * @return array<int,int>  [nomor_juz => jumlah_ayat_di_juz_itu]
     */
    public function pecahPerJuz(int $sMulai, int $aMulai, int $sSelesai, int $aSelesai): array
    {
        $start = $this->posisiAbsolut($sMulai, $aMulai);
        $end   = $this->posisiAbsolut($sSelesai, $aSelesai);
        $hasil = [];
        foreach ($this->juzStartPos as $nomor => $jStart) {
            $jEnd = isset($this->juzStartPos[$nomor + 1]) ? $this->juzStartPos[$nomor + 1] - 1 : $this->total;
            $overlap = min($end, $jEnd) - max($start, $jStart) + 1;
            if ($overlap > 0) $hasil[$nomor] = $overlap;
        }
        return $hasil;
    }

    /** Jumlah total ayat dalam satu juz. */
    public function jumlahAyatJuz(int $juz): int
    {
        return $this->infoJuz($juz)['jumlah_ayat'];
    }

    /** Kebalikan posisiAbsolut: posisi mushaf → [surah, ayat]. */
    public function posisiKeSurahAyat(int $pos): array
    {
        foreach ($this->surahAyat as $nomor => $ayat) {
            $off = $this->offset[$nomor];
            if ($pos > $off && $pos <= $off + $ayat) {
                return [$nomor, $pos - $off];
            }
        }
        return [114, 6]; // fallback akhir mushaf
    }

    /** Rentang satu juz sebagai [surahMulai, ayatMulai, surahSelesai, ayatSelesai]. */
    public function juzRange(int $juz): array
    {
        $info = $this->infoJuz($juz);
        [$sMulai, $aMulai]     = $info['mulai'];
        [$sSelesai, $aSelesai] = $this->posisiKeSurahAyat($info['selesai_pos']);
        return [$sMulai, $aMulai, $sSelesai, $aSelesai];
    }

    /** Validasi nomor surah & ayat. */
    public function ayatValid(int $surah, int $ayat): bool
    {
        return isset($this->surahAyat[$surah]) && $ayat >= 1 && $ayat <= $this->surahAyat[$surah];
    }

    /** Daftar surah (untuk dropdown). */
    public function daftarSurah(): array
    {
        return Surah::orderBy('nomor')->get(['nomor', 'nama', 'jumlah_ayat'])->toArray();
    }
}
