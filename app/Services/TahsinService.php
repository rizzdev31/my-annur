<?php

namespace App\Services;

use App\Models\SettingTahsinMateri;
use App\Models\TahsinPenilaian;
use App\Models\Santri;
use App\Models\SettingTahfidz;
use App\Models\TahsinPenilaianRiwayat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Logika Smart Tahsin: penilaian materi per level + naik level.
 * - Penilaian wajib per materi (ambang lulus dari setting, ≥7).
 * - Naik level perlu semua materi lulus; guru bisa OVERRIDE (otoritas pedagogis).
 * - Level 1–5 materi tahsin; level 6 = "Persiapan Tahfidz" (tahap akhir).
 * - Lulus Persiapan Tahfidz (level 6) → otomatis pindah program ke tahfidz.
 */
class TahsinService
{
    /** Level terakhir tahsin = Persiapan Tahfidz. */
    public const LEVEL_MAX = 6;

    /** Label level: 1–5 = "Level N", 6 = "Persiapan Tahfidz". */
    public static function levelLabel(int $level): string
    {
        return $level >= self::LEVEL_MAX ? 'Persiapan Tahfidz' : "Level $level";
    }

    private float $nilaiLulus;

    public function __construct()
    {
        $this->nilaiLulus = (float) (SettingTahfidz::get()->nilai_lulus ?? 7);
    }

    /** Daftar materi 1 level + status penilaian seorang santri. */
    public function materiSantri(int $santriId, int $level): array
    {
        $materi = SettingTahsinMateri::aktif()->level($level)->orderBy('urutan')->orderBy('id')->get();
        $nilai  = TahsinPenilaian::where('santri_id', $santriId)->where('level', $level)->get()->keyBy('materi_id');

        return $materi->map(function ($m) use ($nilai) {
            $p = $nilai->get($m->id);
            return [
                'materi_id'     => $m->id,
                'nama'          => $m->nama,
                'urutan'        => $m->urutan,
                'nilai'         => $p?->nilai,
                'lulus'         => $p?->lulus,
                'sudah_dinilai' => $p !== null,
                'catatan'       => $p?->catatan,
            ];
        })->values()->all();
    }

    /** Simpan/perbarui nilai satu materi (wajib nilai). */
    public function nilaiMateri(array $d): TahsinPenilaian
    {
        $materi  = SettingTahsinMateri::findOrFail($d['materi_id']);
        $nilai   = (float) $d['nilai'];
        $lulus   = $nilai >= $this->nilaiLulus;
        $tanggal = $d['tanggal'] ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($d, $materi, $nilai, $lulus, $tanggal) {
            // Snapshot terkini (1 baris per santri+materi).
            $penilaian = TahsinPenilaian::updateOrCreate(
                ['santri_id' => $d['santri_id'], 'materi_id' => $d['materi_id']],
                [
                    'absensi_mengajar_id' => $d['absensi_mengajar_id'] ?? null,
                    'tenaga_pendidik_id'  => $d['tenaga_pendidik_id'],
                    'level'               => $materi->level,
                    'nilai'               => $nilai,
                    'lulus'               => $lulus,
                    'catatan'             => $d['catatan'] ?? null,
                    'tanggal'             => $tanggal,
                ]
            );

            // Riwayat: SETIAP input/perubahan disimpan (tak pernah ditimpa) untuk evaluasi.
            \App\Models\TahsinPenilaianRiwayat::create([
                'santri_id'          => $d['santri_id'],
                'materi_id'          => $d['materi_id'],
                'level'              => $materi->level,
                'nilai'              => $nilai,
                'lulus'              => $lulus,
                'catatan'            => $d['catatan'] ?? null,
                'tenaga_pendidik_id' => $d['tenaga_pendidik_id'],
                'tanggal'            => $tanggal,
            ]);

            return $penilaian;
        });
    }

    /**
     * Catat materi TAMBAHAN (pelengkap jurnal) — di luar materi wajib, tak
     * memengaruhi naik level. Append: boleh banyak entri per santri per hari.
     */
    public function catatMateriTambahan(array $d): \App\Models\TahsinMateriTambahan
    {
        return \App\Models\TahsinMateriTambahan::create([
            'santri_id'           => $d['santri_id'],
            'tenaga_pendidik_id'  => $d['tenaga_pendidik_id'] ?? null,
            'absensi_mengajar_id' => $d['absensi_mengajar_id'] ?? null,
            'nama_materi'         => trim($d['nama_materi']),
            'nilai'               => isset($d['nilai']) && $d['nilai'] !== '' ? (float) $d['nilai'] : null,
            'catatan'             => $d['catatan'] ?? null,
            'tanggal'             => $d['tanggal'] ?? Carbon::today()->toDateString(),
        ]);
    }

    /** Daftar materi tambahan terbaru seorang santri (utk tampilan jurnal). */
    public function materiTambahanSantri(int $santriId, int $limit = 15): array
    {
        return \App\Models\TahsinMateriTambahan::where('santri_id', $santriId)
            ->orderByDesc('tanggal')->orderByDesc('id')->limit($limit)->get()
            ->map(fn($t) => [
                'id'       => $t->id,
                'nama'     => $t->nama_materi,
                'nilai'    => $t->nilai,
                'catatan'  => $t->catatan,
                'tanggal'  => $t->tanggal?->toDateString(),
            ])->all();
    }

    /** Apakah semua materi (aktif) level tertentu sudah lulus. */
    public function levelSelesai(int $santriId, int $level): bool
    {
        $materiIds = SettingTahsinMateri::aktif()->level($level)->pluck('id');
        if ($materiIds->isEmpty()) return false;

        $lulus = TahsinPenilaian::where('santri_id', $santriId)
            ->where('lulus', true)->whereIn('materi_id', $materiIds)->count();
        return $lulus >= $materiIds->count();
    }

    /** Naik level. $override = otoritas guru meski belum semua lulus. */
    public function naikLevel(int $santriId, bool $override = false): array
    {
        $santri = Santri::findOrFail($santriId);
        $level  = $santri->tahsin_level ?? 1;

        if (!$override && !$this->levelSelesai($santriId, $level)) {
            throw new \DomainException('Belum semua materi level ini lulus. Aktifkan override untuk tetap menaikkan level.');
        }

        if ($level >= self::LEVEL_MAX) {
            // Lulus Persiapan Tahfidz (level terakhir) → pindah ke program tahfidz.
            $santri->update(['program_quran' => 'tahfidz']);
            return ['level' => self::LEVEL_MAX, 'tahsin_selesai' => true, 'program' => 'tahfidz'];
        }

        $santri->update(['tahsin_level' => $level + 1]);
        return ['level' => $level + 1, 'tahsin_selesai' => false];
    }
}
