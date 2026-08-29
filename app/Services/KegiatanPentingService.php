<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\AbsensiKegiatanPenting;
use App\Models\HariLibur;
use App\Models\KegiatanPenting;
use App\Models\LiburTendik;
use App\Models\PengajuanIzin;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Kegiatan Penting Guru — resolusi peserta harian & pencatatan kehadiran.
 *
 * Peserta yang DIHARAPKAN = guru aktif dgn jenis_guru sesuai sasaran kegiatan,
 * yang hari itu BUKAN libur (mingguan/nasional/individu) & TIDAK sedang izin.
 *   - Sudah absen harian (masuk kerja)  → piket menandai hadir/tidak.
 *   - Hari kerja tapi TIDAK absen harian → otomatis 'tidak_hadir'.
 * Guru libur/izin dikecualikan (dianggap tidak wajib ikut).
 */
class KegiatanPentingService
{
    public function pesertaHariIni(KegiatanPenting $keg, string $tanggal): Collection
    {
        $tgl      = Carbon::parse($tanggal);
        $namaHari = TimezoneHelper::namaHariDB($tgl);
        $jenis    = $keg->jenisGuruSasaran();

        $guru = TenagaPendidik::where('is_aktif', true)
            ->whereIn('jenis_guru', $jenis)
            ->with(['user', 'jabatan'])
            ->get();

        $ids = $guru->pluck('id');

        $records = AbsensiKegiatanPenting::where('kegiatan_penting_id', $keg->id)
            ->whereDate('tanggal', $tanggal)->get()->keyBy('tenaga_pendidik_id');

        $absen = AbsensiHarian::whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $ids)->get()->keyBy('tenaga_pendidik_id');

        $izin = PengajuanIzin::where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal)->where('tanggal_selesai', '>=', $tanggal)
            ->whereIn('tenaga_pendidik_id', $ids)->pluck('tenaga_pendidik_id')->flip();

        $liburNasional = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
            ->exists();

        $peserta = collect();
        foreach ($guru as $g) {
            // Jabatan dikecualikan dari kegiatan (mis. Satpam, Kebersihan) → kinerja aman.
            if ($g->jabatan && $g->jabatan->wajib_kegiatan === false) continue;
            if ($izin->has($g->id)) continue;                                  // sedang izin
            if ($liburNasional) continue;                                      // libur nasional/pesantren
            $jk = $g->jamKerjaAktif();
            if ($jk && $jk->isHariLibur($namaHari)) continue;                  // libur mingguan
            if (LiburTendik::isLibur($g->id, $tanggal)) continue;              // libur individu

            $ah = $absen->get($g->id);
            $hadirKerja = $ah && $ah->jam_masuk && in_array($ah->status, ['hadir', 'terlambat', 'dinas_luar']);

            $rec = $records->get($g->id);
            $peserta->push([
                'tenaga_pendidik_id' => $g->id,
                'nama'        => $g->user?->name ?? ('Guru #' . $g->id),
                'jenis_guru'  => $g->jenis_guru,
                'hadir_kerja' => (bool) $hadirKerja,
                // belum masuk kerja (hari kerja) → otomatis tidak_hadir; jika sudah → belum ditandai (null)
                'status'      => $rec?->status ?? ($hadirKerja ? null : 'tidak_hadir'),
                'jam_hadir'   => $rec?->jam_hadir ? substr((string) $rec->jam_hadir, 0, 5) : null,
                'tercatat'    => (bool) $rec,
            ]);
        }

        return $peserta->sortByDesc('hadir_kerja')->sortBy('nama')->values();
    }

    /** Simpan/mutakhirkan banyak status kehadiran sekaligus (dipakai guru piket). */
    public function simpanBanyak(KegiatanPenting $keg, string $tanggal, array $items, ?int $dicatatOleh): int
    {
        $n = 0;
        foreach ($items as $it) {
            $tpId = (int) ($it['tenaga_pendidik_id'] ?? 0);
            if (!$tpId) continue;
            $status = in_array($it['status'] ?? null, ['hadir', 'tidak_hadir'], true) ? $it['status'] : 'tidak_hadir';

            AbsensiKegiatanPenting::updateOrCreate(
                ['kegiatan_penting_id' => $keg->id, 'tenaga_pendidik_id' => $tpId, 'tanggal' => $tanggal],
                [
                    'status'       => $status,
                    'jam_hadir'    => $status === 'hadir'
                        ? ($it['jam_hadir'] ?? TimezoneHelper::now()->format('H:i:s'))
                        : null,
                    'dicatat_oleh' => $dicatatOleh,
                ]
            );
            $n++;
        }
        return $n;
    }
}
