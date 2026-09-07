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
 * Guru libur/izin dikecualikan (dianggap tidak wajib ikut).
 *
 * ABSEN HARIAN TIDAK LAGI MENENTUKAN APA PUN. Dulu guru yang belum tercatat
 * absen harian otomatis berstatus 'tidak_hadir', dan itu MENGHUKUM guru yang
 * sebenarnya hadir: shift asrama (15:15→07:00) tercatat pada tanggal KEMARIN,
 * sehingga pada kegiatan pagi hari ini mereka terlihat "belum masuk kerja"
 * lalu tersimpan absen — padahal justru sedang bertugas di lokasi. Kini semua
 * peserta mulai TANPA status; guru piket yang menentukan, sesuai kenyataan
 * di lapangan. `hadir_kerja` hanya keterangan tambahan.
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
            // Jam kerja WAJIB diresolusi per tanggal kegiatan — guru shift
            // (satpam/asrama) bisa memakai jadwal berbeda pada tanggal tertentu.
            $jk = $g->jamKerjaAktif($tanggal);
            if ($jk && $jk->isHariLibur($namaHari)) continue;                  // libur mingguan
            if (LiburTendik::isLibur($g->id, $tanggal)) continue;              // libur individu

            $rec = $records->get($g->id);
            $peserta->push([
                'tenaga_pendidik_id' => $g->id,
                'nama'        => $g->user?->name ?? ('Guru #' . $g->id),
                'jenis_guru'  => $g->jenis_guru,
                'hadir_kerja' => $this->tercatatMasukKerja($g, $tanggal, $absen),
                // Belum ditandai = null. TIDAK pernah diisi otomatis 'tidak_hadir'
                // hanya karena absen harian belum ada (lihat catatan kelas).
                'status'      => $rec?->status,
                'jam_hadir'   => $rec?->jam_hadir ? substr((string) $rec->jam_hadir, 0, 5) : null,
                'tercatat'    => (bool) $rec,
            ]);
        }

        return $peserta->sortBy('nama')->values();
    }

    /**
     * Sekadar KETERANGAN untuk guru piket: apakah guru ini tercatat masuk kerja?
     *
     * Overnight-aware: shift lintas hari (asrama 15:15→07:00) tercatat pada
     * tanggal MULAI shift, jadi pada kegiatan pagi hari berikutnya baris hari
     * ini memang kosong — bukan berarti guru tidak masuk. Tanpa penyesuaian
     * ini keterangannya menyesatkan dan piket ikut salah menandai.
     */
    private function tercatatMasukKerja(TenagaPendidik $g, string $tanggal, Collection $absen): bool
    {
        $sah = ['hadir', 'terlambat', 'dinas_luar'];

        $ah = $absen->get($g->id);
        if ($ah && $ah->jam_masuk && in_array($ah->status, $sah, true)) return true;

        // Shift kemarin yang lintas hari & belum ditutup → guru masih bertugas.
        $kemarin  = Carbon::parse($tanggal)->subDay();
        $jkKemarin = $g->jamKerjaAktif($kemarin->toDateString());
        $jadwal    = $jkKemarin?->getJamUntukHari(TimezoneHelper::namaHariDB($kemarin));
        if (!$jadwal || !($jadwal['lintas_hari'] ?? false)) return false;

        return AbsensiHarian::where('tenaga_pendidik_id', $g->id)
            ->whereDate('tanggal', $kemarin)
            ->whereNotNull('jam_masuk')->whereIn('status', $sah)->exists();
    }

    /** Simpan/mutakhirkan banyak status kehadiran sekaligus (dipakai guru piket). */
    public function simpanBanyak(KegiatanPenting $keg, string $tanggal, array $items, ?int $dicatatOleh): int
    {
        $n = 0;
        foreach ($items as $it) {
            $tpId = (int) ($it['tenaga_pendidik_id'] ?? 0);
            if (!$tpId) continue;

            // Status kosong = BELUM ditandai → lewati, jangan diam-diam dianggap
            // 'tidak_hadir'. Menandai absen memotong poin kinerja guru, jadi
            // harus lahir dari keputusan piket, bukan dari data yang belum diisi.
            $status = $it['status'] ?? null;
            if (!in_array($status, ['hadir', 'tidak_hadir'], true)) continue;

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
