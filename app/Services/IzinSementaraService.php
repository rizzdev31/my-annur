<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\SettingJenisPengajuan;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Izin Sementara (partial-day) — Tahap 1: fondasi backend.
 *
 * Membuat izin berbasis JAM (dalam satu hari) yang:
 *   - auto-disetujui (self-service, tanpa approval),
 *   - TIDAK mengubah absen harian (guru tetap hadir — ditangani di AbsensiWindowService),
 *   - mengembalikan sesi mengajar yang beririsan window untuk disambung ke Guru Pengganti.
 *
 * Netral untuk kinerja: sesi yang dialihkan tidak dihitung pelanggaran (Tahap 2).
 */
class IzinSementaraService
{
    /** Kode jenis pengajuan untuk izin sementara (di-seed via migration). */
    public const KODE_JENIS = 'IZIN_SEMENTARA';

    /**
     * Buat izin sementara (langsung disetujui).
     *
     * @param  string  $jamMulai   Format H:i atau H:i:s
     * @param  string  $jamSelesai Format H:i atau H:i:s
     * @param  int|null $olehUserId Aktor (admin bila dibuatkan; guru sendiri bila self-service)
     */
    public function ajukan(
        TenagaPendidik $guru,
        string $jamMulai,
        string $jamSelesai,
        string $alasan,
        ?Carbon $tanggal = null,
        ?int $olehUserId = null,
    ): PengajuanIzin {
        $tgl = ($tanggal ?? TimezoneHelper::now())->toDateString();

        $jenis = SettingJenisPengajuan::where('kode', self::KODE_JENIS)->firstOrFail();

        return PengajuanIzin::create([
            'tenaga_pendidik_id'         => $guru->id,
            'setting_jenis_pengajuan_id' => $jenis->id,
            'tanggal_mulai'              => $tgl,
            'tanggal_selesai'            => $tgl,
            'jam_mulai'                  => $this->normalJam($jamMulai),
            'jam_selesai'                => $this->normalJam($jamSelesai),
            'is_sementara'               => true,
            'jumlah_hari'                => 1,
            'alasan'                     => $alasan,
            'status'                     => 'disetujui',   // auto-approve
            'diproses_oleh'              => $olehUserId,
            'tanggal_keputusan'          => TimezoneHelper::now(),
        ]);
    }

    /**
     * Sesi mengajar guru pada tanggal tsb yang BERIRISAN dengan window izin.
     * Overlap: jadwal.jam_mulai < izin.jam_selesai AND jadwal.jam_selesai > izin.jam_mulai.
     *
     * @return Collection<int,JadwalMengajar>
     */
    public function sesiTerdampak(
        TenagaPendidik $guru,
        Carbon $tanggal,
        string $jamMulai,
        string $jamSelesai,
    ): Collection {
        $hari = TimezoneHelper::namaHariDB($tanggal);
        $awal = $this->normalJam($jamMulai);
        $akhir = $this->normalJam($jamSelesai);

        return JadwalMengajar::with(['mataPelajaran', 'kelasRel'])
            ->where('tenaga_pendidik_id', $guru->id)
            ->where('hari', $hari)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->where('jam_mulai', '<', $akhir)
            ->where('jam_selesai', '>', $awal)
            ->orderBy('jam_mulai')
            ->get();
    }

    /** Validasi dasar window (dipakai endpoint Tahap 2). */
    public function windowValid(string $jamMulai, string $jamSelesai): bool
    {
        return $this->normalJam($jamMulai) < $this->normalJam($jamSelesai);
    }

    /** Normalisasi ke H:i:s agar perbandingan string konsisten dengan kolom TIME. */
    private function normalJam(string $jam): string
    {
        return strlen($jam) === 5 ? $jam . ':00' : $jam;
    }
}
