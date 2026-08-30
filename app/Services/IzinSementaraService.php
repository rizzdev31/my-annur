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

    /**
     * Batalkan izin sementara + rollback penunjukan pengganti yang BELUM diajar.
     * Sesi yang penggantinya sudah terlanjur mengajar (jp>0/absen) DIPERTAHANKAN.
     * Sesi yang dibatalkan → record AbsensiMengajar dihapus (revert bersih ke normal).
     *
     * @return array{pengganti_dibatalkan:int, pengganti_terlanjur:int}
     */
    public function batalkan(PengajuanIzin $izin): array
    {
        if (!$izin->is_sementara) {
            throw new \DomainException('Bukan izin sementara.');
        }
        if ($izin->status === 'dibatalkan') {
            throw new \DomainException('Izin sudah dibatalkan.');
        }

        $guru = $izin->tenagaPendidik;
        $tgl  = $izin->tanggal_mulai;

        // Batasi hanya sesi yang beririsan window izin ini (hindari menyentuh
        // pengganti dari izin lain di hari sama).
        $jadwalIds = $this->sesiTerdampak(
            $guru, $tgl, (string) $izin->jam_mulai, (string) $izin->jam_selesai
        )->pluck('id')->all();

        $dibatalkan = 0; $terlanjur = 0;

        if ($jadwalIds) {
            $sesi = \App\Models\AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
                ->whereDate('tanggal', $tgl->toDateString())
                ->whereIn('jadwal_mengajar_id', $jadwalIds)
                ->where('status', 'pengganti')
                ->whereNotNull('digantikan_oleh')
                ->get();

            foreach ($sesi as $s) {
                if (!is_null($s->jam_selesai_aktual) || (int) $s->jp_terlaksana > 0) {
                    $terlanjur++; continue;   // pengganti sudah mengajar → pertahankan
                }
                $s->delete();                 // revert bersih
                $dibatalkan++;
            }
        }

        $izin->update(['status' => 'dibatalkan', 'tanggal_keputusan' => TimezoneHelper::now()]);

        return ['pengganti_dibatalkan' => $dibatalkan, 'pengganti_terlanjur' => $terlanjur];
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
