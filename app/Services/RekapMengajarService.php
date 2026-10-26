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
class RekapMengajarService
{
    /** Program Qur'an. Dipertahankan namanya karena dirujuk LaporanController. */
    public const TIPE = ['tahfidz', 'tahsin'];

    /**
     * Kata kunci untuk "semua mapel SELAIN program Qur'an" — dipakai halaman
     * Sesi Kosong (penggajian) yang memeriksa kelas reguler SMP/SMA.
     *
     * Dulu service ini hanya melayani tahfidz/tahsin, sementara kelas reguler
     * tidak punya laporan rentang tanggal sama sekali: admin harus membuka
     * halaman Absensi Mengajar hari per hari (22 hari = 22 kali muat) untuk
     * mencari sesi yang kosong sebelum penggajian. Semua penjaga rumit di
     * sini (hari libur, libur pembelajaran, jadwal belum dibuat, batas absen,
     * sesi inval) digeneralisasi daripada ditulis ulang — kalau ditulis ulang,
     * angka reguler dan angka Qur'an pasti akan berbeda tafsir.
     */
    public const TIPE_REGULER = 'reguler';

    public function __construct(private SesiMengajarService $sesi) {}

    /**
     * @return array{baris: Collection, total: array}
     */
    public function rekap(
        Carbon $mulai, Carbon $selesai, ?string $tipe = null, ?int $guruId = null, ?array $tingkat = null
    ): array {
        $sesi = $this->sesiPeriode($mulai, $selesai, $tipe, $tingkat);

        $baris = $sesi->filter(fn ($s) => !$guruId || $s['guru_id'] === $guruId)
            ->groupBy(fn ($s) => $s['guru_id'] . '|' . $s['tipe'])
            ->map(function (Collection $g) {
                $f = $g->first();
                $n = fn (string $k) => $g->where('kategori', $k)->count();
                // Kegagalan pada sesi SENDIRI dan pada tugas INVAL harus dipisah:
                // inval berada di luar jadwal guru ini, jadi tak boleh ikut penyebut
                // "seharusnya masuk" (dulu ikut → angkanya tidak pernah rekonsiliasi).
                $nSendiri = fn (string $k) => $g->where('milik_sendiri', true)->where('kategori', $k)->count();

                $terjadwal = $g->where('milik_sendiri', true)->count();
                $netral    = $n('izin') + $n('digantikan');
                $mengajar  = $n('mengajar');
                $dasar     = $terjadwal - $netral;

                return [
                    'guru_id'          => $f['guru_id'],
                    'guru'             => $f['guru'],
                    'tipe'             => $f['tipe'],
                    // Tiga angka utama dalam bahasa admin: "seharusnya masuk berapa
                    // kali, masuk berapa, tidak masuk berapa". Dihitung di sini agar
                    // laporan tidak menyusun ulang aturannya sendiri.
                    'seharusnya'       => $dasar,
                    'tidak_masuk'      => $nSendiri('tidak_terlaksana') + $nSendiri('tanpa_catatan'),
                    'inval_tidak_datang' => $g->where('milik_sendiri', false)
                        ->where('kategori', 'tidak_terlaksana')->count(),
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

                    // ── TANGGALNYA, bukan hanya jumlahnya ────────────────────
                    // Angka "tidak masuk 4" tidak bisa diaudit: yang diperiksa
                    // adalah tanggal mana, kelas mana, dan sebabnya. Dulu itu
                    // hanya terlihat bila admin menyaring satu guru lalu membuka
                    // tabel rincian — sehingga memeriksa 30 guru = 30 kali muat.
                    'tanggal_masuk'       => $g->where('milik_sendiri', true)->where('kategori', 'mengajar')
                        ->pluck('tanggal')->unique()->sort()->values()->all(),
                    'tanggal_tidak_masuk' => $g->where('milik_sendiri', true)
                        ->whereIn('kategori', ['tidak_terlaksana', 'tanpa_catatan'])
                        ->sortBy('tanggal')
                        ->map(fn ($x) => ['tanggal' => $x['tanggal'], 'kelas' => $x['kelas'],
                            'jam' => $x['jam'], 'kategori' => $x['kategori'],
                            'sebab' => $x['keterangan']])->values()->all(),
                    'tanggal_izin'        => $g->where('milik_sendiri', true)->where('kategori', 'izin')
                        ->sortBy('tanggal')
                        ->map(fn ($x) => ['tanggal' => $x['tanggal'], 'kelas' => $x['kelas'],
                            'sebab' => $x['keterangan']])->values()->all(),
                    'tanggal_digantikan'  => $g->where('milik_sendiri', true)->where('kategori', 'digantikan')
                        ->sortBy('tanggal')
                        ->map(fn ($x) => ['tanggal' => $x['tanggal'], 'kelas' => $x['kelas'],
                            'sebab' => $x['keterangan']])->values()->all(),
                    'tanggal_inval_tidak_datang' => $g->where('milik_sendiri', false)
                        ->where('kategori', 'tidak_terlaksana')->sortBy('tanggal')
                        ->map(fn ($x) => ['tanggal' => $x['tanggal'], 'kelas' => $x['kelas'],
                            'sebab' => $x['keterangan']])->values()->all(),
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
                'seharusnya'         => $dasar,
                'tidak_masuk'        => $jumlah('tidak_masuk'),
                'inval_tidak_datang' => $jumlah('inval_tidak_datang'),
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
    public function detail(
        int $guruId, Carbon $mulai, Carbon $selesai, ?string $tipe = null, ?array $tingkat = null
    ): Collection {
        return $this->sesiPeriode($mulai, $selesai, $tipe, $tingkat)
            ->where('guru_id', $guruId)
            ->sortBy([['tanggal', 'asc'], ['jam', 'asc']])
            ->values();
    }

    /**
     * SESI KOSONG dalam rentang — bentuk siap-pakai untuk halaman penggajian.
     *
     * "Kosong" = sesi MILIK GURU SENDIRI yang tidak terlaksana atau tidak
     * pernah dicatat sama sekali. Sesi yang dialihkan (digantikan) TIDAK
     * dihitung sebagai kosong bagi guru asli — itu netral; kegagalan pengganti
     * yang tak datang dilaporkan terpisah sebagai `inval_tidak_datang`,
     * sebab itu tanggung jawab pengganti, bukan pemilik jadwal.
     *
     * @param  bool  $gabungSemua  true = reguler + Qur'an sekaligus
     * @return array{ringkasan:array, kosong:array, per_guru:array}
     */
    public function detailKosong(
        Carbon $mulai,
        Carbon $selesai,
        ?string $tipe = self::TIPE_REGULER,
        ?int $guruId = null,
        ?array $tingkat = null,
        bool $gabungSemua = false,
    ): array {
        $sesi = $gabungSemua
            ? $this->sesiPeriode($mulai, $selesai, self::TIPE_REGULER, $tingkat)
                ->concat($this->sesiPeriode($mulai, $selesai, null, $tingkat))
            : $this->sesiPeriode($mulai, $selesai, $tipe, $tingkat);

        if ($guruId) {
            $sesi = $sesi->where('guru_id', $guruId);
        }

        $milik   = $sesi->where('milik_sendiri', true);
        $n       = fn (string $k) => $milik->where('kategori', $k)->count();
        $kosong  = $milik->whereIn('kategori', ['tidak_terlaksana', 'tanpa_catatan'])
            ->sortBy([['tanggal', 'asc'], ['guru', 'asc'], ['jam', 'asc']])
            ->map(fn ($x) => [
                'absensi_id' => $x['absensi_id'] ?? null,
                'tanggal'    => $x['tanggal'],
                'guru_id'    => $x['guru_id'],
                'guru'       => $x['guru'],
                'kelas'      => $x['kelas'],
                'tingkat'    => $x['tingkat'] ?? null,
                'mapel'      => $x['mapel'] ?? '-',
                'jam'        => $x['jam'],
                'tipe'       => $x['tipe'],
                'kategori'   => $x['kategori'],
                'sebab'      => $x['keterangan'],
            ])->values();

        $terjadwal = $milik->count();
        $dasar     = $terjadwal - $n('izin') - $n('digantikan');

        $perGuru = $kosong->groupBy('guru_id')->map(fn ($g) => [
            'guru_id' => $g->first()['guru_id'],
            'guru'    => $g->first()['guru'],
            'jumlah'  => $g->count(),
            'tanggal' => $g->pluck('tanggal')->unique()->sort()->values()->all(),
        ])->sortByDesc('jumlah')->values();

        return [
            'ringkasan' => [
                'terjadwal'          => $terjadwal,
                'seharusnya'         => $dasar,
                'terlaksana'         => $n('mengajar'),
                'izin'               => $n('izin'),
                'digantikan'         => $n('digantikan'),
                'tidak_terlaksana'   => $n('tidak_terlaksana'),
                'tanpa_catatan'      => $n('tanpa_catatan'),
                'kosong'             => $n('tidak_terlaksana') + $n('tanpa_catatan'),
                'inval_tidak_datang' => $sesi->where('milik_sendiri', false)
                    ->where('kategori', 'tidak_terlaksana')->count(),
                'guru_terdampak'     => $perGuru->count(),
                'persen_terlaksana'  => $dasar > 0 ? round($n('mengajar') / $dasar * 100, 1) : null,
            ],
            'kosong'   => $kosong->all(),
            'per_guru' => $perGuru->all(),
        ];
    }

    /**
     * Seluruh sesi tahfidz/tahsin dalam periode, satu baris per (guru, sesi).
     * Satu sesi yang diinval menghasilkan DUA baris: 'digantikan' untuk guru asli
     * dan 'inval'/'tidak_terlaksana' untuk penggantinya.
     */
    private function sesiPeriode(
        Carbon $mulai, Carbon $selesai, ?string $tipe, ?array $tingkat = null
    ): Collection {
        // 'reguler' = semua mapel di LUAR program Qur'an; null = Qur'an saja
        // (perilaku lama, dipakai laporan Mengajar Tahfidz & Tahsin).
        $reguler  = $tipe === self::TIPE_REGULER;
        $tipeList = $reguler ? self::TIPE : ($tipe ? [$tipe] : self::TIPE);
        $tingkat  = $tingkat ? array_map('strval', $tingkat) : null;
        $now      = TimezoneHelper::now();
        $dari     = $mulai->toDateString();
        $sampai   = $selesai->toDateString();

        // ── Catatan sesi ─────────────────────────────────────────────────────
        $catatan = DB::table('absensi_mengajar as a')
            ->join('jadwal_mengajar as j', 'j.id', '=', 'a.jadwal_mengajar_id')
            ->join('mata_pelajaran as m', 'm.id', '=', 'j.mata_pelajaran_id')
            ->leftJoin('kelas as k', 'k.id', '=', 'j.kelas_id')
            ->when($reguler, fn ($q) => $q->whereNotIn('m.tipe', $tipeList),
                             fn ($q) => $q->whereIn('m.tipe', $tipeList))
            ->when($tingkat, fn ($q) => $q->whereIn('k.tingkat', $tingkat))
            ->whereBetween('a.tanggal', [$dari, $sampai])
            ->get([
                'a.id', 'a.jadwal_mengajar_id', 'a.tanggal', 'a.status', 'a.tenaga_pendidik_id',
                'a.digantikan_oleh', 'a.jam_mulai_aktual', 'a.jam_selesai_aktual', 'a.jp_terlaksana',
                // Alasan yang SUDAH tercatat sistem/guru. Dulu tidak diambil sama
                // sekali, padahal inilah bahan audit: 156/156 sesi tidak terlaksana,
                // 32/32 izin, dan 38/38 sesi pengganti punya isinya.
                'a.keterangan as keterangan_tercatat',
                'j.jam_mulai', 'j.jam_selesai', 'j.jumlah_jp', 'm.tipe', 'm.nama as mapel',
                'k.nama as kelas', 'k.tingkat as tingkat',
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

        // ── Rujukan izin: guru|tanggal → "Dinas Luar #156" ──────────────────
        // Keterangan tersimpan sudah menyebut JENIS izinnya, tetapi tidak nomor
        // pengajuannya. Nomor itulah yang membuat keterangan bisa DIPERIKSA:
        // auditor bisa membuka pengajuan aslinya, bukan hanya membaca kalimat.
        $izinPeta = [];
        foreach (DB::table('pengajuan_izin as p')
            ->join('setting_jenis_pengajuan as sj', 'sj.id', '=', 'p.setting_jenis_pengajuan_id')
            ->where('p.status', 'disetujui')
            ->where('p.tanggal_mulai', '<=', $sampai)->where('p.tanggal_selesai', '>=', $dari)
            ->get(['p.id', 'p.tenaga_pendidik_id', 'p.tanggal_mulai', 'p.tanggal_selesai',
                   'p.is_sementara', 'p.jam_mulai', 'p.jam_selesai', 'sj.nama']) as $iz) {
            $d1 = Carbon::parse($iz->tanggal_mulai)->max($mulai);
            $d2 = Carbon::parse($iz->tanggal_selesai)->min($selesai);
            $jam = $iz->is_sementara && $iz->jam_mulai
                ? ' ' . substr((string) $iz->jam_mulai, 0, 5) . '–' . substr((string) $iz->jam_selesai, 0, 5)
                : '';
            for ($d = $d1->copy(); $d->lte($d2); $d->addDay()) {
                $izinPeta[$iz->tenaga_pendidik_id . '|' . $d->toDateString()]
                    = $iz->nama . $jam . ' #' . $iz->id;
            }
        }

        /** Gabung beberapa penggal keterangan tanpa duplikasi/penggal kosong. */
        $rangkai = fn (array $bagian) => implode(' · ', array_values(array_unique(array_filter(
            array_map(fn ($x) => trim(rtrim((string) $x, '.')), $bagian),
            fn ($x) => $x !== ''
        )))) ?: null;

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
                'mapel'      => $a->mapel ?? '-',
                'tingkat'    => $a->tingkat,
                'jam'        => substr((string) $a->jam_mulai, 0, 5) . '–' . substr((string) $a->jam_selesai, 0, 5),
                'tipe'       => $a->tipe,
                'kelas'      => $a->kelas ?? '—',
                'santri_hadir' => (int) ($santri[$a->id]->hadir ?? 0),
                'santri_total' => (int) ($santri[$a->id]->total ?? 0),
            ];

            $izinRujukan = $izinPeta[$a->tenaga_pendidik_id . '|' . $tgl] ?? null;
            $ditutup = $a->jam_selesai_aktual
                ? 'ditutup ' . substr((string) $a->jam_selesai_aktual, 0, 5) : null;

            // Untuk baris MASUK, fakta auditnya adalah jam PENGABSENAN. Terukur
            // 30 hari: 543/543 sesi masuk punya jam_mulai_aktual, hanya 5 yang
            // punya jam_selesai_aktual — jadi bila hanya jam penutupan yang
            // dipakai, 477 baris "masuk" tetap tanpa keterangan.
            $diabsen = null;
            if ($a->jam_mulai_aktual) {
                $jm = substr((string) $a->jam_mulai_aktual, 0, 5);
                $diabsen = 'Diabsen ' . $jm;
                // Diabsen setelah kelas berakhir = fakta yang perlu dilihat
                // auditor, disebut apa adanya tanpa menghakimi.
                if ($jm > substr((string) $a->jam_selesai, 0, 5)) {
                    $diabsen .= ' (setelah kelas berakhir ' . substr((string) $a->jam_selesai, 0, 5) . ')';
                } elseif ($jm > substr((string) $a->jam_mulai, 0, 5)) {
                    $diabsen .= ' (jadwal ' . substr((string) $a->jam_mulai, 0, 5) . ')';
                }
            }

            if ($a->digantikan_oleh) {
                // Guru asli: netral.
                $baris->push($dasar + ['guru_id' => (int) $a->tenaga_pendidik_id, 'milik_sendiri' => true,
                    'kategori' => 'digantikan', 'jp' => 0, 'aktivitas' => 0,
                    'keterangan' => $rangkai([
                        'Digantikan ' . ($guruNama[$a->digantikan_oleh] ?? 'guru lain'),
                        $izinRujukan ? 'izin ' . $izinRujukan : null,
                        $a->keterangan_tercatat,
                    ])]);

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
                        'keterangan' => $rangkai([
                            'Inval untuk ' . ($guruNama[$a->tenaga_pendidik_id] ?? 'guru lain'),
                            $katInval === 'inval' ? $rangkai([$diabsen, $ditutup]) : 'tidak datang sampai batas '
                                . KebijakanMengajar::batasAbsenSesi($tgl, (string) $a->jam_selesai)->format('H:i'),
                            $a->keterangan_tercatat,
                        ])]);
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

            // Keterangan diisi untuk SEMUA kategori — termasuk "masuk" dan "tidak
            // masuk". Dulu hanya baris inval/digantikan/tanpa-catatan yang punya
            // keterangan, sehingga justru 156 sesi tidak terlaksana (bahan audit
            // utama) tampil tanpa sebab apa pun padahal sebabnya tersimpan.
            $keterangan = match ($kategori) {
                'mengajar' => $rangkai([$diabsen, $ditutup, $a->keterangan_tercatat]),
                'izin'     => $rangkai([
                    $izinRujukan ? 'Izin: ' . $izinRujukan : 'Izin',
                    $a->keterangan_tercatat,
                ]),
                'tidak_terlaksana' => $rangkai([
                    $a->keterangan_tercatat
                        ?: 'Tidak ada absen sampai batas '
                            . KebijakanMengajar::batasAbsenSesi($tgl, (string) $a->jam_selesai)->format('H:i'),
                    $izinRujukan ? 'guru punya izin ' . $izinRujukan : null,
                ]),
                default => $rangkai([$a->keterangan_tercatat]),
            };

            $baris->push($dasar + ['guru_id' => (int) $a->tenaga_pendidik_id, 'milik_sendiri' => true,
                'kategori' => $kategori, 'jp' => $kategori === 'mengajar' ? (int) $a->jp_terlaksana : 0,
                'aktivitas' => $kategori === 'mengajar' ? $aktivitasUntuk((int) $a->tenaga_pendidik_id) : 0,
                'keterangan' => $keterangan]);
        }

        // ── Terjadwal tetapi tanpa catatan apa pun ──────────────────────────
        $jadwal = JadwalMengajar::with(['mataPelajaran:id,nama,tipe', 'kelasRel:id,nama,tingkat'])
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->when($reguler,
                fn ($q) => $q->whereHas('mataPelajaran', fn ($m) => $m->whereNotIn('tipe', $tipeList)),
                fn ($q) => $q->whereHas('mataPelajaran', fn ($m) => $m->whereIn('tipe', $tipeList)))
            ->when($tingkat, fn ($q) => $q->whereHas('kelasRel', fn ($k) => $k->whereIn('tingkat', $tingkat)))
            ->whereHas('tenagaPendidik', fn ($q) => $q->where('is_aktif', true))
            ->get();

        $libur = [];
        $akhir = $selesai->copy()->min($now->copy()->endOfDay());
        for ($d = $mulai->copy()->startOfDay(); $d->lte($akhir); $d->addDay()) {
            $tgl = $d->toDateString();
            $libur[$tgl] ??= (bool) $this->sesi->hariLibur($tgl);
            if ($libur[$tgl]) continue;
            $hari = TimezoneHelper::namaHariDB($d);
            // Libur pembelajaran hanya mengenai sebagian sesi, jadi diperiksa
            // per jadwal — bukan per tanggal seperti libur penuh di atas.
            $diliburkan = app(LiburMengajarService::class)->petaPembelajaran($tgl);

            foreach ($jadwal as $j) {
                if (strtolower($j->hari) !== $hari) continue;
                if (isset($diliburkan[$j->id])) continue;
                if ($j->created_at && $j->created_at->toDateString() > $tgl) continue;
                if (isset($tercatat[$j->id . '|' . $tgl])) continue;
                // Belum lewat batas → masih mungkin diisi, belum dihitung.
                if ($now->lte(KebijakanMengajar::batasAbsenSesi($tgl, (string) $j->jam_selesai))) continue;

                $baris->push([
                    'absensi_id' => null, 'tanggal' => $tgl,
                    'mapel'      => $j->mataPelajaran?->nama ?? '-',
                    'tingkat'    => $j->kelasRel?->tingkat,
                    'jam'        => substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5),
                    'tipe'       => $j->mataPelajaran?->tipe, 'kelas' => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                    'santri_hadir' => 0, 'santri_total' => 0,
                    'guru_id'    => (int) $j->tenaga_pendidik_id, 'milik_sendiri' => true,
                    'kategori'   => 'tanpa_catatan', 'jp' => 0, 'aktivitas' => 0,
                    'keterangan' => 'Terjadwal, tidak ada catatan sama sekali (batas '
                        . KebijakanMengajar::batasAbsenSesi($tgl, (string) $j->jam_selesai)->format('H:i') . ')',
                ]);
            }
        }

        return $baris->map(fn ($b) => $b + ['guru' => $guruNama[$b['guru_id']] ?? '—']);
    }
}
