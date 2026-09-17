<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap mengajar guru TAHFIDZ & TAHSIN per periode (harian / mingguan / bulanan).
 *
 * Menjawab "berapa kali guru ini mengajar", dibandingkan dengan berapa kali ia
 * SEHARUSNYA mengajar menurut jadwal — bukan sekadar menghitung absen.
 *
 * Klasifikasi setiap sesi mengikuti aturan yang sama dengan kinerja
 * (SesiMengajarService) dan inval (PenggantiMengajarService):
 *
 *   mengajar          : sesi jadwal sendiri, terlaksana
 *   inval             : mengajar sesi guru lain sebagai pengganti (sudah diisi)
 *   tidak_terlaksana  : sesi sendiri tak diisi sampai batas, ATAU inval yang tak datang
 *   izin / digantikan : sesi sendiri saat izin / dialihkan ke pengganti (netral)
 *   tanpa_catatan     : terjadwal & sudah lewat batas, tetapi tidak ada catatan apa
 *                       pun. Terjadi pada sesi sebelum pencatatan otomatis
 *                       berlaku (16 Sep 2026); setelahnya scheduler mengisinya.
 *
 * Keterlaksanaan = (mengajar) ÷ (terjadwal − izin − digantikan).
 * Inval tidak masuk pembilang karena di luar jadwal guru itu sendiri — ditampilkan
 * sebagai kolom terpisah.
 *
 * "Jurnal kosong" = sesi yang diabsen tetapi tidak ada satu pun catatan
 * pembelajaran: tahfidz tanpa setoran; tahsin tanpa penilaian materi maupun
 * materi tambahan dari guru itu pada hari itu.
 */
class RekapMengajarQuranService
{
    public const TIPE = ['tahfidz', 'tahsin'];

    public function __construct(private SesiMengajarService $sesi) {}

    /**
     * @return array{baris: Collection, total: array}
     */
    public function rekap(Carbon $mulai, Carbon $selesai, ?string $tipe = null, ?int $guruId = null): array
    {
        $sesi = $this->sesiPeriode($mulai, $selesai, $tipe);

        $baris = $sesi->filter(fn ($s) => !$guruId || $s['guru_id'] === $guruId)
            ->groupBy(fn ($s) => $s['guru_id'] . '|' . $s['tipe'])
            ->map(function (Collection $g) {
                $f = $g->first();
                $n = fn (string $k) => $g->where('kategori', $k)->count();

                $terjadwal = $g->where('milik_sendiri', true)->count();
                $netral    = $n('izin') + $n('digantikan');
                $mengajar  = $n('mengajar');
                $dasar     = $terjadwal - $netral;

                return [
                    'guru_id'          => $f['guru_id'],
                    'guru'             => $f['guru'],
                    'tipe'             => $f['tipe'],
                    'kelas'            => $g->where('milik_sendiri', true)->pluck('kelas')->unique()->values()->all(),
                    'terjadwal'        => $terjadwal,
                    'mengajar'         => $mengajar,
                    'inval'            => $n('inval'),
                    'tidak_terlaksana' => $n('tidak_terlaksana'),
                    'tanpa_catatan'    => $n('tanpa_catatan'),
                    'izin'             => $n('izin'),
                    'digantikan'       => $n('digantikan'),
                    'jp'               => (int) $g->whereIn('kategori', ['mengajar', 'inval'])->sum('jp'),
                    'hari_mengajar'    => $g->whereIn('kategori', ['mengajar', 'inval'])->pluck('tanggal')->unique()->count(),
                    'jurnal_kosong'    => $g->whereIn('kategori', ['mengajar', 'inval'])->where('aktivitas', 0)->count(),
                    'aktivitas'        => (int) $g->sum('aktivitas'),
                    'persen'           => $dasar > 0 ? round($mengajar / $dasar * 100, 1) : null,
                ];
            })
            ->sortBy([['tipe', 'asc'], ['persen', 'asc'], ['guru', 'asc']])
            ->values();

        $jumlah = fn (string $k) => (int) $baris->sum($k);
        $dasar  = $jumlah('terjadwal') - $jumlah('izin') - $jumlah('digantikan');

        return [
            'baris' => $baris,
            'total' => [
                'guru'             => $baris->pluck('guru_id')->unique()->count(),
                'terjadwal'        => $jumlah('terjadwal'),
                'mengajar'         => $jumlah('mengajar'),
                'inval'            => $jumlah('inval'),
                'tidak_terlaksana' => $jumlah('tidak_terlaksana'),
                'tanpa_catatan'    => $jumlah('tanpa_catatan'),
                'jurnal_kosong'    => $jumlah('jurnal_kosong'),
                'jp'               => $jumlah('jp'),
                'persen'           => $dasar > 0 ? round($jumlah('mengajar') / $dasar * 100, 1) : null,
            ],
        ];
    }

    /** Daftar sesi satu guru (rincian). */
    public function detail(int $guruId, Carbon $mulai, Carbon $selesai, ?string $tipe = null): Collection
    {
        return $this->sesiPeriode($mulai, $selesai, $tipe)
            ->where('guru_id', $guruId)
            ->sortBy([['tanggal', 'asc'], ['jam', 'asc']])
            ->values();
    }

    /**
     * Seluruh sesi tahfidz/tahsin dalam periode, satu baris per (guru, sesi).
     * Satu sesi yang diinval menghasilkan DUA baris: 'digantikan' untuk guru asli
     * dan 'inval'/'tidak_terlaksana' untuk penggantinya.
     */
    private function sesiPeriode(Carbon $mulai, Carbon $selesai, ?string $tipe): Collection
    {
        $tipeList = $tipe ? [$tipe] : self::TIPE;
        $now      = TimezoneHelper::now();
        $dari     = $mulai->toDateString();
        $sampai   = $selesai->toDateString();

        // ── Catatan sesi ─────────────────────────────────────────────────────
        $catatan = DB::table('absensi_mengajar as a')
            ->join('jadwal_mengajar as j', 'j.id', '=', 'a.jadwal_mengajar_id')
            ->join('mata_pelajaran as m', 'm.id', '=', 'j.mata_pelajaran_id')
            ->leftJoin('kelas as k', 'k.id', '=', 'j.kelas_id')
            ->whereIn('m.tipe', $tipeList)
            ->whereBetween('a.tanggal', [$dari, $sampai])
            ->get([
                'a.id', 'a.jadwal_mengajar_id', 'a.tanggal', 'a.status', 'a.tenaga_pendidik_id',
                'a.digantikan_oleh', 'a.jam_selesai_aktual', 'a.jp_terlaksana',
                'j.jam_mulai', 'j.jam_selesai', 'j.jumlah_jp', 'm.tipe', 'k.nama as kelas',
            ]);

        // Aktivitas pembelajaran per sesi.
        $ids = $catatan->pluck('id');
        $setoran = DB::table('setoran_tahfidz')->whereIn('absensi_mengajar_id', $ids)
            ->selectRaw('absensi_mengajar_id, COUNT(*) n')->groupBy('absensi_mengajar_id')->pluck('n', 'absensi_mengajar_id');
        $tambahan = DB::table('tahsin_materi_tambahan')->whereIn('absensi_mengajar_id', $ids)
            ->selectRaw('absensi_mengajar_id, COUNT(*) n')->groupBy('absensi_mengajar_id')->pluck('n', 'absensi_mengajar_id');
        // Riwayat penilaian tahsin tidak menyimpan id sesi → dicocokkan per guru per hari.
        $penilaian = DB::table('tahsin_penilaian_riwayat')->whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('tenaga_pendidik_id, DATE(tanggal) tgl, COUNT(*) n')->groupBy('tenaga_pendidik_id', 'tgl')
            ->get()->mapWithKeys(fn ($r) => [$r->tenaga_pendidik_id . '|' . $r->tgl => (int) $r->n]);
        $santri = DB::table('absensi_santri')->whereIn('absensi_mengajar_id', $ids)
            ->selectRaw("absensi_mengajar_id, COUNT(*) total, SUM(status IN ('hadir','telat')) hadir")
            ->groupBy('absensi_mengajar_id')->get()->keyBy('absensi_mengajar_id');

        $guruNama = DB::table('tenaga_pendidik as tp')->join('users as u', 'u.id', '=', 'tp.user_id')
            ->pluck('u.name', 'tp.id');

        $baris = collect();
        $tercatat = [];

        foreach ($catatan as $a) {
            $tgl = substr((string) $a->tanggal, 0, 10);
            $tercatat[$a->jadwal_mengajar_id . '|' . $tgl] = true;

            $aktivitasUntuk = function (int $guru) use ($a, $tgl, $setoran, $tambahan, $penilaian) {
                return $a->tipe === 'tahfidz'
                    ? (int) ($setoran[$a->id] ?? 0)
                    : (int) ($tambahan[$a->id] ?? 0) + (int) ($penilaian[$guru . '|' . $tgl] ?? 0);
            };
            $dasar = [
                'absensi_id' => $a->id,
                'tanggal'    => $tgl,
                'jam'        => substr((string) $a->jam_mulai, 0, 5) . '–' . substr((string) $a->jam_selesai, 0, 5),
                'tipe'       => $a->tipe,
                'kelas'      => $a->kelas ?? '—',
                'santri_hadir' => (int) ($santri[$a->id]->hadir ?? 0),
                'santri_total' => (int) ($santri[$a->id]->total ?? 0),
            ];

            if ($a->digantikan_oleh) {
                // Guru asli: netral.
                $baris->push($dasar + ['guru_id' => (int) $a->tenaga_pendidik_id, 'milik_sendiri' => true,
                    'kategori' => 'digantikan', 'jp' => 0, 'aktivitas' => 0,
                    'keterangan' => 'Digantikan ' . ($guruNama[$a->digantikan_oleh] ?? 'guru lain')]);

                // Pengganti: inval / tidak terlaksana / belum waktunya.
                $katInval = match (true) {
                    $a->status === 'pengganti' && !is_null($a->jam_selesai_aktual) => 'inval',
                    $a->status === 'tidak_terlaksana'                            => 'tidak_terlaksana',
                    default                                                     => null, // menunggu jam kelas
                };
                if ($katInval) {
                    $baris->push($dasar + ['guru_id' => (int) $a->digantikan_oleh, 'milik_sendiri' => false,
                        'kategori' => $katInval, 'jp' => (int) $a->jp_terlaksana,
                        'aktivitas' => $katInval === 'inval' ? $aktivitasUntuk((int) $a->digantikan_oleh) : 0,
                        'keterangan' => 'Inval untuk ' . ($guruNama[$a->tenaga_pendidik_id] ?? 'guru lain')]);
                }
                continue;
            }

            $kategori = match ($a->status) {
                'terlaksana', 'hadir' => 'mengajar',
                'tidak_terlaksana'    => 'tidak_terlaksana',
                'izin'                => 'izin',
                'libur'               => 'libur',
                default               => null,
            };
            if (!$kategori || $kategori === 'libur') continue; // hari libur bukan jadwal wajib

            $baris->push($dasar + ['guru_id' => (int) $a->tenaga_pendidik_id, 'milik_sendiri' => true,
                'kategori' => $kategori, 'jp' => $kategori === 'mengajar' ? (int) $a->jp_terlaksana : 0,
                'aktivitas' => $kategori === 'mengajar' ? $aktivitasUntuk((int) $a->tenaga_pendidik_id) : 0,
                'keterangan' => null]);
        }

        // ── Terjadwal tetapi tanpa catatan apa pun ──────────────────────────
        $jadwal = JadwalMengajar::with(['mataPelajaran:id,tipe', 'kelasRel:id,nama'])
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->whereHas('mataPelajaran', fn ($q) => $q->whereIn('tipe', $tipeList))
            ->whereHas('tenagaPendidik', fn ($q) => $q->where('is_aktif', true))
            ->get();

        $libur = [];
        $akhir = $selesai->copy()->min($now->copy()->endOfDay());
        for ($d = $mulai->copy()->startOfDay(); $d->lte($akhir); $d->addDay()) {
            $tgl = $d->toDateString();
            $libur[$tgl] ??= (bool) $this->sesi->hariLibur($tgl);
            if ($libur[$tgl]) continue;
            $hari = TimezoneHelper::namaHariDB($d);

            foreach ($jadwal as $j) {
                if (strtolower($j->hari) !== $hari) continue;
                if ($j->created_at && $j->created_at->toDateString() > $tgl) continue;
                if (isset($tercatat[$j->id . '|' . $tgl])) continue;
                // Belum lewat batas → masih mungkin diisi, belum dihitung.
                if ($now->lte(KebijakanMengajar::batasAbsenSesi($tgl, (string) $j->jam_selesai))) continue;

                $baris->push([
                    'absensi_id' => null, 'tanggal' => $tgl,
                    'jam'        => substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5),
                    'tipe'       => $j->mataPelajaran?->tipe, 'kelas' => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                    'santri_hadir' => 0, 'santri_total' => 0,
                    'guru_id'    => (int) $j->tenaga_pendidik_id, 'milik_sendiri' => true,
                    'kategori'   => 'tanpa_catatan', 'jp' => 0, 'aktivitas' => 0,
                    'keterangan' => 'Terjadwal, tidak ada catatan',
                ]);
            }
        }

        return $baris->map(fn ($b) => $b + ['guru' => $guruNama[$b['guru_id']] ?? '—']);
    }
}
