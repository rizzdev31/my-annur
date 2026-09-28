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
    /** Memo peserta yang diharapkan, per (sasaran + tanggal) dalam satu request. */
    private array $memoPeserta = [];

    /**
     * Guru yang WAJIB ikut kegiatan bersasaran ini pada tanggal tsb.
     * Dipisah agar bisa dipakai berulang (daftar kegiatan memanggilnya sekali
     * per sasaran, bukan sekali per kegiatan).
     *
     * @return array{0: Collection<TenagaPendidik>, 1: Collection, 2: Collection, 3: array}
     *         [guru wajib, absensi harian, izin disetujui, id peserta tambahan]
     */
    private function pesertaDiharapkan(KegiatanPenting $keg, string $tanggal): array
    {
        // Memo per KEGIATAN (bukan per sasaran) karena peserta khusus berbeda
        // tiap kegiatan.
        $kunci = 'keg' . $keg->id . '@' . $tanggal;
        if (isset($this->memoPeserta[$kunci])) return $this->memoPeserta[$kunci];

        $namaHari = TimezoneHelper::namaHariDB(Carbon::parse($tanggal));

        $keg->loadMissing('pesertaKhusus');
        $tambahan     = $keg->idsTambahan();       // wajib walau di luar sasaran
        $dikecualikan = $keg->idsDikecualikan();   // tidak wajib walau sesuai sasaran

        // Sasaran tetap dasar utamanya, lalu disesuaikan daftar khusus:
        // ada guru mukim yang memang ikut kegiatan non-mukim, dan sebaliknya.
        $guru = TenagaPendidik::where('is_aktif', true)
            ->where(fn ($q) => $q
                ->whereIn('jenis_guru', $keg->jenisGuruSasaran())
                ->orWhereIn('id', $tambahan ?: [0]))
            ->whereNotIn('id', $dikecualikan ?: [0])
            ->with(['user', 'jabatan'])
            ->get();

        $ids = $guru->pluck('id');

        $absen = AbsensiHarian::whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $ids)->get()->keyBy('tenaga_pendidik_id');

        // HANYA izin sehari penuh yang membebaskan guru dari kegiatan.
        //  - "Datang terlambat": guru tetap masuk, cuma telat — jelas hadir saat
        //    kegiatan siang seperti Sholat Dzuhur.
        //  - "Izin sementara"  : berbasis JAM; dibebaskan hanya bila jam kegiatan
        //    jatuh di dalam rentang izinnya (ditangani di pesertaHariIni).
        // Aturan ini disamakan dengan AbsensiWindowService::deteksiIzinAktif;
        // sebelumnya di sini dipakai query mentah sehingga guru yang sekadar
        // telat ikut hilang dari daftar peserta.
        // Guru dengan izin disetujui TIDAK lagi dihilangkan dari daftar: dulu
        // namanya hilang begitu saja sehingga piket bingung dan guru tak punya
        // bukti dibebaskan. Sekarang tetap tampil, berstatus 'izin' (netral).
        $izin = PengajuanIzin::where('status', 'disetujui')
            ->where('is_sementara', false)
            ->where('is_datang_terlambat', false)
            ->where('tanggal_mulai', '<=', $tanggal)->where('tanggal_selesai', '>=', $tanggal)
            ->whereIn('tenaga_pendidik_id', $ids)
            ->get(['tenaga_pendidik_id', 'jenis_izin', 'alasan'])
            ->keyBy('tenaga_pendidik_id');

        $liburNasional = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
            ->exists();

        $wajib = $guru->filter(function ($g) use ($liburNasional, $namaHari, $tanggal) {
            // Jabatan dikecualikan dari kegiatan (mis. Satpam, Kebersihan) → kinerja aman.
            if ($g->jabatan && $g->jabatan->wajib_kegiatan === false) return false;
            if ($liburNasional) return false;                                  // libur nasional/pesantren
            // Jam kerja WAJIB diresolusi per tanggal kegiatan — guru shift
            // (satpam/asrama) bisa memakai jadwal berbeda pada tanggal tertentu.
            // Guru yang dibebaskan absen harian tidak hadir sepanjang hari kerja →
            // bukan peserta kegiatan. Sama perlakuannya dengan libur mingguan.
            if (!$g->wajibAbsenHarian()) return false;
            $jk = $g->jamKerjaAktif($tanggal);
            if ($jk && $jk->isHariLibur($namaHari)) return false;              // libur mingguan

            return !LiburTendik::isLibur($g->id, $tanggal);                    // libur individu
        })->values();

        return $this->memoPeserta[$kunci] = [$wajib, $absen, $izin, $tambahan];
    }

    /** Ringkasan kelengkapan penandaan satu kegiatan (untuk daftar & peringatan). */
    public function ringkasan(KegiatanPenting $keg, string $tanggal): array
    {
        [$wajib, , $izin] = $this->pesertaDiharapkan($keg, $tanggal);

        // Saringan yang sama dengan pesertaHariIni — kalau tidak, hitungan
        // "belum ditandai" di daftar kegiatan tak akan pernah mencapai nol.
        $bebas = $this->izinSementaraBentrok($wajib->pluck('id'), $tanggal, (string) $keg->jam);
        if ($bebas->isNotEmpty()) {
            $wajib = $wajib->reject(fn($g) => $bebas->has($g->id))->values();
        }

        $rec = AbsensiKegiatanPenting::where('kegiatan_penting_id', $keg->id)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tenaga_pendidik_id', $wajib->pluck('id'))->get();

        // Guru yang izinnya sudah disetujui dianggap SELESAI ditandai (netral),
        // walau barisnya belum tersimpan — piket tidak perlu mengejarnya.
        $izinBelumTersimpan = $wajib->filter(fn ($g) => $izin->has($g->id)
            && !$rec->firstWhere('tenaga_pendidik_id', $g->id))->count();

        return [
            'total'    => $wajib->count(),
            'hadir'    => $rec->where('status', 'hadir')->count(),
            'tidak'    => $rec->where('status', 'tidak_hadir')->count(),
            'izin'     => $rec->where('status', 'izin')->count() + $izinBelumTersimpan,
            'ditandai' => $rec->count() + $izinBelumTersimpan,
            'belum'    => max($wajib->count() - $rec->count() - $izinBelumTersimpan, 0),
        ];
    }

    public function pesertaHariIni(KegiatanPenting $keg, string $tanggal): Collection
    {
        [$guru, $absen, $izin, $tambahan] = $this->pesertaDiharapkan($keg, $tanggal);

        // Izin sementara berbasis jam: hanya membebaskan bila jam kegiatannya
        // memang jatuh di dalam rentang izin. Disaring di sini (bukan di
        // pesertaDiharapkan) karena bergantung jam kegiatan, sedangkan daftar
        // peserta di-memo per sasaran+tanggal.
        $bebas = $this->izinSementaraBentrok($guru->pluck('id'), $tanggal, (string) $keg->jam);
        if ($bebas->isNotEmpty()) {
            $guru = $guru->reject(fn($g) => $bebas->has($g->id))->values();
        }

        $records = AbsensiKegiatanPenting::where('kegiatan_penting_id', $keg->id)
            ->whereDate('tanggal', $tanggal)->get()->keyBy('tenaga_pendidik_id');

        $peserta = collect();
        foreach ($guru as $g) {
            $rec     = $records->get($g->id);
            $izinRow = $izin->get($g->id);

            // Izin yang sudah disetujui mengunci statusnya jadi 'izin' (netral):
            // piket tidak perlu menandai, dan tidak boleh menandai absen.
            $status = $rec?->status;
            $sumber = $rec?->sumber;
            $ket    = $rec?->keterangan;
            if ($izinRow && $status !== 'hadir') {
                $status = 'izin';
                $sumber = 'perizinan';
                $ket    = $ket ?: trim('Izin disetujui: ' . ($izinRow->jenis_izin ?? '') . ' — ' . ($izinRow->alasan ?? ''), ' —');
            }

            $peserta->push([
                'tenaga_pendidik_id' => $g->id,
                'nama'        => $g->user?->name ?? ('Guru #' . $g->id),
                'jenis_guru'  => $g->jenis_guru,
                'hadir_kerja' => $this->tercatatMasukKerja($g, $tanggal, $absen),
                // Belum ditandai = null. TIDAK pernah diisi otomatis 'tidak_hadir'
                // hanya karena absen harian belum ada (lihat catatan kelas).
                'status'       => $status,
                'status_label' => $status ? (\App\Models\AbsensiKegiatanPenting::LABEL_STATUS[$status] ?? $status) : null,
                'sumber'       => $sumber,
                'keterangan'   => $ket,
                // Bersumber izin resmi → tidak bisa diubah guru piket.
                'terkunci'     => (bool) $izinRow,
                'tambahan'     => in_array($g->id, $tambahan, true),
                'jam_hadir'    => $rec?->jam_hadir ? substr((string) $rec->jam_hadir, 0, 5) : null,
                'tercatat'     => (bool) $rec,
            ]);
        }

        return $peserta->sortBy('nama')->values();
    }

    /**
     * Guru yang izin sementaranya menutupi jam kegiatan ini.
     * Izin tanpa jam (data lama) dianggap TIDAK menutupi — lebih baik guru
     * tetap muncul lalu ditandai piket daripada hilang diam-diam.
     *
     * @return Collection himpunan tenaga_pendidik_id
     */
    private function izinSementaraBentrok(Collection $ids, string $tanggal, string $jamKegiatan): Collection
    {
        if ($ids->isEmpty() || $jamKegiatan === '') return collect();

        return PengajuanIzin::where('status', 'disetujui')
            ->where('is_sementara', true)
            ->whereIn('tenaga_pendidik_id', $ids)
            ->where('tanggal_mulai', '<=', $tanggal)->where('tanggal_selesai', '>=', $tanggal)
            ->whereNotNull('jam_mulai')->whereNotNull('jam_selesai')
            ->where('jam_mulai', '<=', $jamKegiatan)
            ->where('jam_selesai', '>=', $jamKegiatan)
            ->pluck('tenaga_pendidik_id')->flip();
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

    /**
     * Simpan/mutakhirkan banyak status kehadiran sekaligus (dipakai guru piket).
     *
     * Aturan:
     *  - Status kosong = BELUM ditandai → dilewati, tidak pernah diam-diam
     *    dianggap 'tidak_hadir'.
     *  - 'izin' bersifat NETRAL (tidak masuk penyebut rasio kinerja) dan wajib
     *    berketerangan supaya ada alasan yang bisa ditelusuri.
     *  - Guru yang izinnya sudah disetujui TERKUNCI: hanya boleh 'izin'
     *    atau 'hadir' (kalau ternyata ia tetap datang), tidak boleh 'tidak_hadir'.
     *  - Guru di luar daftar wajib boleh ditandai HADIR saja (peserta ad-hoc) —
     *    memberi kredit tanpa menciptakan kewajiban baru.
     */
    public function simpanBanyak(KegiatanPenting $keg, string $tanggal, array $items, ?int $dicatatOleh): int
    {
        [$wajib, , $izin] = $this->pesertaDiharapkan($keg, $tanggal);
        $idWajib = $wajib->pluck('id')->flip();

        $n = 0;
        foreach ($items as $it) {
            $tpId = (int) ($it['tenaga_pendidik_id'] ?? 0);
            if (!$tpId) continue;

            $status = $it['status'] ?? null;
            if (!in_array($status, ['hadir', 'tidak_hadir', 'izin'], true)) continue;

            $adaIzin = $izin->has($tpId);
            if ($adaIzin && $status === 'tidak_hadir') continue;     // terkunci izin resmi
            if (!$idWajib->has($tpId) && $status !== 'hadir') continue; // ad-hoc: hanya hadir

            $sumber = $adaIzin && $status === 'izin' ? 'perizinan'
                : (!$idWajib->has($tpId) ? 'ad_hoc' : 'piket');

            $keterangan = trim((string) ($it['keterangan'] ?? '')) ?: null;
            if ($status === 'izin' && $keterangan === null) {
                $izinRow    = $izin->get($tpId);
                $keterangan = $izinRow
                    ? trim('Izin disetujui: ' . ($izinRow->jenis_izin ?? '') . ' — ' . ($izinRow->alasan ?? ''), ' —')
                    : 'Diizinkan guru piket';
            }

            AbsensiKegiatanPenting::updateOrCreate(
                ['kegiatan_penting_id' => $keg->id, 'tenaga_pendidik_id' => $tpId, 'tanggal' => $tanggal],
                [
                    'status'       => $status,
                    'sumber'       => $sumber,
                    'keterangan'   => $keterangan,
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

    /**
     * Guru di luar daftar wajib yang boleh ditambahkan piket sebagai peserta
     * ad-hoc (mis. guru mukim yang kebetulan ikut kegiatan non-mukim).
     */
    public function kandidatTambahan(KegiatanPenting $keg, string $tanggal): Collection
    {
        [$wajib] = $this->pesertaDiharapkan($keg, $tanggal);
        $sudah = $wajib->pluck('id')->flip();

        return TenagaPendidik::where('is_aktif', true)->with('user')->get()
            ->reject(fn ($g) => $sudah->has($g->id))
            ->map(fn ($g) => [
                'tenaga_pendidik_id' => $g->id,
                'nama'       => $g->user?->name ?? ('Guru #' . $g->id),
                'jenis_guru' => $g->jenis_guru,
            ])->sortBy('nama')->values();
    }
}
