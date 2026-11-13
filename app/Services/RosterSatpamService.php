<?php

namespace App\Services;

use App\Models\JadwalShift;
use App\Models\SettingJamKerja;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Roster shift satpam — tiga orang menjaga 24 jam, berputar tiap dua pekan.
 *
 * ATURANNYA, dan kenapa bentuknya begini:
 *
 *  • Hari biasa (tiga orang jaga): pagi / siang / malam, masing-masing 8 jam.
 *    Mulainya 15 menit lebih awal dari jam bulat — itu margin serah-terima,
 *    bukan kelebihan jam: petugas berikutnya datang sebelum yang lama pulang.
 *
 *  • Hari ada yang libur (dua orang jaga): 24 jam dibagi dua, 06:00–18:00 dan
 *    18:00–06:00. Yang perannya lebih AWAL mengambil bagian siang. Contoh dari
 *    pemilik proses: Jazuli (pagi) libur Senin → Henggar (siang) 06:00–18:00,
 *    Irfan (malam) 18:00–06:00.
 *
 *  • Hari libur melekat pada ORANG, bukan pada shift — Jazuli tetap libur
 *    Senin walau pekan depan ia memegang shift malam. Ini beda dari roster
 *    lama, yang menanam hari libur di dalam shift-nya.
 *
 * Konsekuensi penting dari "libur melekat pada orang": karena ketiga hari
 * liburnya selalu himpunan yang sama (Senin/Rabu/Jumat) apa pun rotasinya,
 * pola sepekan seseorang hanya bergantung pada PERANNYA dan HARI LIBURNYA
 * sendiri. Jadi seluruh roster cuma punya 3×3 = 9 pola tetap yang dipakai
 * berulang selamanya — tidak perlu tabel baru, cukup `jadwal_per_hari` yang
 * sudah ada, ditempel ke tanggal lewat `jadwal_shift`.
 *
 * Shift malam dihitung dari HARI MULAINYA. "Irfan libur Rabu" berarti tidak
 * ada shift yang DIMULAI Rabu; shift Selasa 21:45 → Rabu 06:00 tetap jalan,
 * dan memang harus, kalau tidak Rabu dini hari kosong. Konvensi ini mengikuti
 * roster lama, bukan hal baru.
 */
class RosterSatpamService
{
    /**
     * Urutan peran. Indeksnya dipakai dua kali: untuk memutar rotasi, dan
     * untuk memutuskan siapa mengambil 06:00–18:00 saat hanya berdua.
     */
    public const PERAN = ['pagi', 'siang', 'malam'];

    /** Jam shift saat bertiga. 15 menit awal = serah-terima. */
    public const JAM = [
        'pagi'  => ['05:45', '14:00'],
        'siang' => ['13:45', '22:00'],
        'malam' => ['21:45', '06:00'],
    ];

    /** Jam saat berdua: 24 jam dibagi dua sama rata. */
    public const JAM_12_AWAL  = ['06:00', '18:00'];
    public const JAM_12_AKHIR = ['18:00', '06:00'];

    public const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];

    /** Panjang satu blok rotasi. */
    public const PEKAN_PER_BLOK = 2;

    /** Toleransi terlambat satpam (menit). */
    public const TOLERANSI = 15;

    /**
     * Awal rotasi. Blok ke-n = JANGKAR + n×2 pekan, jadi peran siapa pun pada
     * tanggal apa pun bisa dihitung tanpa membaca riwayat.
     */
    public const JANGKAR = '2026-11-16';

    /**
     * Urutan peran awal pada blok pertama: indeks 0 = pagi, 1 = siang,
     * 2 = malam. Hanya URUTAN yang ditanam di sini — hari liburnya dibaca
     * dari `hari_libur` masing-masing orang, supaya tidak ada dua sumber
     * yang bisa berbeda diam-diam (pelajaran dari `hari_libur_paten`).
     *
     * Ganti personel = ubah daftar ini lalu jalankan ulang perintahnya.
     */
    public const SEED = [43, 42, 44];   // Jazuli, Henggar, Irfan

    /**
     * Susunan yang, bila dipakai sebagai blok 0, menghasilkan penugasan nyata
     * blok ke-$blok. Dipakai agar perintah harian bisa mulai dari blok yang
     * sedang berjalan, bukan mengulang dari jangkar.
     */
    public function susunanAwal(int $blok): array
    {
        $n = count(self::PERAN);
        $urut = [];

        for ($j = 0; $j < $n; $j++) {
            $tpId = self::SEED[(($j - $blok) % $n + $n) % $n];
            $urut[] = ['tp_id' => $tpId, 'libur' => $this->hariLibur($tpId)];
        }

        return $urut;
    }

    /**
     * Hari libur pribadi satpam, dari `hari_libur`.
     *
     * Wajib tepat SATU hari. Kosong berarti ia tak pernah libur dan rekannya
     * tak pernah dapat giliran 12 jam; lebih dari satu berarti pos kosong di
     * hari kedua. Dua-duanya lebih baik berhenti di sini daripada terbit jadi
     * roster yang bolong.
     */
    private function hariLibur(int $tpId): string
    {
        $guru = TenagaPendidik::with('user:id,name')->find($tpId);
        if (! $guru) {
            throw new DomainException("Satpam dengan id {$tpId} tidak ditemukan — perbarui RosterSatpamService::SEED.");
        }

        $nama  = $guru->user?->name ?? ('Guru #' . $tpId);
        $libur = array_values(array_filter(array_map(
            fn ($h) => mb_strtolower(trim((string) $h)),
            (array) ($guru->hari_libur ?? [])
        )));

        if (count($libur) !== 1) {
            throw new DomainException(
                $nama . ' harus punya TEPAT SATU hari libur di data pegawainya, sekarang '
                . (count($libur) ? '[' . implode(', ', $libur) . ']' : 'kosong')
                . '. Perbaiki dulu di Data Tenaga Pendidik.'
            );
        }
        if (! in_array($libur[0], self::HARI, true)) {
            throw new DomainException($nama . ' punya hari libur tak dikenal: ' . $libur[0]);
        }

        return $libur[0];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // POLA
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Pola 7 hari untuk SATU orang pada satu blok.
     *
     * @param  array  $anggota  [['tp_id'=>int,'peran'=>string,'libur'=>string], ...]
     * @param  int    $saya     indeks orang yang polanya dihitung
     * @return array            siap dipakai sebagai `jadwal_per_hari`
     */
    public function polaPekan(array $anggota, int $saya): array
    {
        $peranSaya = $anggota[$saya]['peran'];
        $liburSaya = $anggota[$saya]['libur'];

        $pola = [];
        foreach (self::HARI as $hari) {
            if ($hari === $liburSaya) {
                $pola[$hari] = $this->sel(null, null, false);
                continue;
            }

            // Siapa yang libur hari ini (selain saya)?
            $yangLibur = null;
            foreach ($anggota as $i => $a) {
                if ($i !== $saya && $a['libur'] === $hari) { $yangLibur = $i; break; }
            }

            if ($yangLibur === null) {
                [$masuk, $pulang] = self::JAM[$peranSaya];
                $pola[$hari] = $this->sel($masuk, $pulang, true);
                continue;
            }

            // Berdua. Yang perannya lebih awal ambil bagian siang.
            $kawan = null;
            foreach ($anggota as $i => $a) {
                if ($i !== $saya && $i !== $yangLibur) { $kawan = $a; break; }
            }
            $sayaLebihAwal = array_search($peranSaya, self::PERAN, true)
                           < array_search($kawan['peran'], self::PERAN, true);

            [$masuk, $pulang] = $sayaLebihAwal ? self::JAM_12_AWAL : self::JAM_12_AKHIR;
            $pola[$hari] = $this->sel($masuk, $pulang, true);
        }

        return $pola;
    }

    private function sel(?string $masuk, ?string $pulang, bool $aktif): array
    {
        return [
            'jam_masuk'  => $masuk ? $masuk . ':00' : null,
            'jam_pulang' => $pulang ? $pulang . ':00' : null,
            'toleransi'  => self::TOLERANSI,
            'aktif'      => $aktif,
        ];
    }

    /**
     * Susunan anggota pada blok ke-$blok, dihitung dari susunan awal.
     *
     * Rotasi MAJU: pagi → siang → malam → pagi. Orang ke-i pada blok ke-b
     * memegang peran ke-(i + b) mod 3.
     *
     * @param  array $awal  [['tp_id'=>int,'libur'=>string], ...] urut peran awal
     */
    public function susunanBlok(array $awal, int $blok): array
    {
        $n = count(self::PERAN);

        return array_values(array_map(
            fn ($a, $i) => $a + ['peran' => self::PERAN[($i + $blok) % $n]],
            $awal,
            array_keys($awal)
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PEMERIKSAAN — dijalankan sebelum menulis, dan dipakai layar pemantauan
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Apakah 24 jam × 7 hari benar-benar terjaga, dan apakah jamnya adil?
     *
     * Celah sekecil apa pun dalam pekan itu dilaporkan. Ini pagar yang nyata:
     * salah satu angka saja keliru dan pos kosong berjam-jam tanpa ada yang
     * sadar sampai terjadi sesuatu.
     *
     * @return array{celah:array,menit:array,total_menit:int}
     */
    public function periksa(array $anggota): array
    {
        $MENIT_PEKAN = 7 * 24 * 60;
        $terjaga = array_fill(0, $MENIT_PEKAN, 0);
        $menitOrang = [];

        foreach ($anggota as $i => $a) {
            $pola = $this->polaPekan($anggota, $i);
            $total = 0;

            foreach (self::HARI as $h => $hari) {
                $sel = $pola[$hari];
                if (! $sel['aktif']) continue;

                $mulai = $this->keMenit($sel['jam_masuk']);
                $akhir = $this->keMenit($sel['jam_pulang']);
                $durasi = $akhir > $mulai ? $akhir - $mulai : (24 * 60) - $mulai + $akhir;
                $total += $durasi;

                $dari = $h * 24 * 60 + $mulai;
                for ($m = 0; $m < $durasi; $m++) {
                    // Pekan berputar: Ahad malam menutupi Senin dini hari.
                    $terjaga[($dari + $m) % $MENIT_PEKAN]++;
                }
            }

            $menitOrang[$a['tp_id']] = $total;
        }

        // Rangkum menit kosong jadi rentang, bukan daftar menit.
        $celah = [];
        $awal = null;
        for ($m = 0; $m <= $MENIT_PEKAN; $m++) {
            $kosong = $m < $MENIT_PEKAN && $terjaga[$m] === 0;
            if ($kosong && $awal === null) $awal = $m;
            if (! $kosong && $awal !== null) {
                $celah[] = ['dari' => $this->labelMenit($awal), 'sampai' => $this->labelMenit($m), 'menit' => $m - $awal];
                $awal = null;
            }
        }

        return ['celah' => $celah, 'menit' => $menitOrang, 'total_menit' => array_sum($menitOrang)];
    }

    private function keMenit(string $jam): int
    {
        [$j, $m] = array_map('intval', explode(':', substr($jam, 0, 5)));
        return $j * 60 + $m;
    }

    private function labelMenit(int $m): string
    {
        $hari = self::HARI[intdiv($m, 1440) % 7];
        $sisa = $m % 1440;

        return ucfirst($hari) . ' ' . sprintf('%02d:%02d', intdiv($sisa, 60), $sisa % 60);
    }

    /**
     * Papan roster untuk layar pemantauan: satu blok, siap ditampilkan.
     *
     * Dihitung ulang dari jangkar, BUKAN dibaca dari `jadwal_shift` — jadi
     * layar menunjukkan apa yang SEHARUSNYA berlaku. Bila nanti ada selisih
     * dengan yang tertulis, itu justru yang ingin terlihat.
     */
    public function papan(?Carbon $tanggal = null): array
    {
        $tanggal = ($tanggal ?: Carbon::today())->copy()->startOfDay();
        $jangkar = Carbon::parse(self::JANGKAR)->startOfDay();

        $blok = $tanggal->lt($jangkar)
            ? 0
            : intdiv((int) $jangkar->diffInWeeks($tanggal), self::PEKAN_PER_BLOK);

        $dari    = $jangkar->copy()->addWeeks($blok * self::PEKAN_PER_BLOK);
        $anggota = $this->susunanBlok($this->susunanAwal($blok), 0);
        $cek     = $this->periksa($anggota);

        $baris = [];
        foreach ($anggota as $i => $a) {
            $pola = $this->polaPekan($anggota, $i);
            $hari = [];
            foreach (self::HARI as $h) {
                $sel = $pola[$h];
                $hari[$h] = $sel['aktif']
                    ? ['jam' => substr($sel['jam_masuk'], 0, 5) . '–' . substr($sel['jam_pulang'], 0, 5),
                       'dua_belas' => substr($sel['jam_masuk'], 0, 5) === self::JAM_12_AWAL[0]
                                   || substr($sel['jam_masuk'], 0, 5) === self::JAM_12_AKHIR[0]]
                    : null;
            }

            $guru = TenagaPendidik::with('user:id,name')->find($a['tp_id']);
            $baris[] = [
                'tp_id' => $a['tp_id'],
                'nama'  => $guru?->user?->name ?? ('Guru #' . $a['tp_id']),
                'peran' => $a['peran'],
                'libur' => $a['libur'],
                'hari'  => $hari,
                'jam'   => round(($cek['menit'][$a['tp_id']] ?? 0) / 60, 1),
            ];
        }

        return [
            'blok_ke'   => $blok + 1,
            'dari'      => $dari->toDateString(),
            'sampai'    => $dari->copy()->addWeeks(self::PEKAN_PER_BLOK)->subDay()->toDateString(),
            'hari'      => self::HARI,
            'baris'     => $baris,
            'celah'     => $cek['celah'],
            'jam_rata'  => count(array_unique(array_values($cek['menit']))) === 1,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PENULISAN
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Terbitkan roster untuk $jumlahBlok blok, mulai $mulai (harus Senin).
     *
     * Idempoten: setting per (orang × peran) dibuat sekali lalu dipakai ulang,
     * dan baris `jadwal_shift` pada rentang yang sama ditimpa — jadi menjalankan
     * ulang tidak menggandakan apa pun.
     *
     * TIDAK pernah menyentuh tanggal sebelum $mulai: gaji periode lampau sudah
     * dihitung dari roster lama.
     *
     * @param  array $awal  [['tp_id'=>int,'libur'=>string], ...] urut peran awal
     */
    public function terbitkan(array $awal, Carbon $mulai, int $jumlahBlok, bool $simulasi = false): array
    {
        if (! $mulai->isMonday()) {
            throw new DomainException('Roster harus mulai hari Senin, bukan ' . $mulai->locale('id')->isoFormat('dddd') . '.');
        }
        if (count($awal) !== count(self::PERAN)) {
            throw new DomainException('Roster ini dirancang untuk tepat ' . count(self::PERAN) . ' orang.');
        }

        // Pagar: pola harus menjaga 24 jam penuh SEBELUM apa pun ditulis.
        foreach (range(0, count(self::PERAN) - 1) as $b) {
            $cek = $this->periksa($this->susunanBlok($awal, $b));
            if ($cek['celah']) {
                throw new DomainException(
                    'Pola blok ke-' . ($b + 1) . ' meninggalkan pos kosong: '
                    . collect($cek['celah'])->map(fn ($c) => $c['dari'] . '–' . $c['sampai'])->implode(', ')
                );
            }
        }

        $hasil = ['blok' => [], 'setting_dibuat' => 0, 'shift_ditulis' => 0];

        // Simulasi TIDAK dibungkus transaksi. Versi pertama membatalkannya
        // dengan melempar dari dalam DB::transaction — dan itu membuat
        // $hasil tidak pernah sempat dikembalikan.
        $jalankan = function () use ($awal, $mulai, $jumlahBlok, $simulasi, &$hasil) {
            for ($b = 0; $b < $jumlahBlok; $b++) {
                $dari    = $mulai->copy()->addWeeks($b * self::PEKAN_PER_BLOK);
                $sampai  = $dari->copy()->addWeeks(self::PEKAN_PER_BLOK)->subDay();
                $anggota = $this->susunanBlok($awal, $b);
                $baris   = [];

                foreach ($anggota as $i => $a) {
                    $guru = TenagaPendidik::with('user:id,name')->find($a['tp_id']);
                    if (! $guru) continue;

                    $nama = $guru->user?->name ?? ('Guru #' . $a['tp_id']);
                    $pola = $this->polaPekan($anggota, $i);

                    if (! $simulasi) {
                        $setting = SettingJamKerja::updateOrCreate(
                            ['nama' => 'Shift Satpam — ' . $nama . ' (' . $a['peran'] . ')'],
                            [
                                'jadwal_per_hari'         => $pola,
                                'gunakan_jadwal_per_hari' => true,
                                'jam_masuk'               => self::JAM[$a['peran']][0] . ':00',
                                'jam_pulang'              => self::JAM[$a['peran']][1] . ':00',
                                'toleransi_terlambat'     => self::TOLERANSI,
                                'hari_kerja'              => [],
                                'total_jam_kerja_sehari'  => 480,
                                'is_template'             => false,
                                'is_default'              => false,
                                'is_aktif'                => true,
                                // `is_shift_khusus` membuat GenerateJamKerjaService
                                // MELEWATI satpam (guruShiftKhusus membaca flag ini
                                // dari jam kerja AKTIF), jadi "Generate Jam Kerja"
                                // tidak akan menimpa roster.
                                'is_shift_khusus'         => true,
                                // `tenaga_pendidik_id` SENGAJA dibiarkan kosong.
                                // GenerateJamKerjaService memakai
                                // updateOrCreate(['tenaga_pendidik_id'=>..,'is_template'=>false])
                                // — kalau diisi, satu kali generate bisa menimpa
                                // isi roster ini tanpa gejala. Kepemilikan cukup
                                // lewat `jadwal_shift`.
                            ]
                        );
                        if ($setting->wasRecentlyCreated) $hasil['setting_dibuat']++;

                        // Ganti bersih: baris lama yang MULAI di rentang ini
                        // dibuang supaya menjalankan ulang tidak menumpuk.
                        JadwalShift::where('tenaga_pendidik_id', $a['tp_id'])
                            ->whereDate('tanggal_mulai', '>=', $dari->toDateString())
                            ->whereDate('tanggal_mulai', '<=', $sampai->toDateString())
                            ->delete();

                        // Baris lama yang mulai SEBELUM blok ini tapi berakhir
                        // di dalamnya dipendekkan, bukan dihapus — masa lalunya
                        // tetap utuh untuk gaji yang sudah dihitung, sementara
                        // tumpang tindihnya hilang. Tanpa ini, dua baris berlaku
                        // di hari yang sama dan yang menang hanya ditentukan
                        // urutan `latest('tanggal_mulai')` — kebetulan, bukan aturan.
                        JadwalShift::where('tenaga_pendidik_id', $a['tp_id'])
                            ->whereDate('tanggal_mulai', '<', $dari->toDateString())
                            ->whereDate('tanggal_selesai', '>=', $dari->toDateString())
                            ->update(['tanggal_selesai' => $dari->copy()->subDay()->toDateString()]);

                        JadwalShift::create([
                            'tenaga_pendidik_id'   => $a['tp_id'],
                            'setting_jam_kerja_id' => $setting->id,
                            'tanggal_mulai'        => $dari->toDateString(),
                            'tanggal_selesai'      => $sampai->toDateString(),
                            'keterangan'           => 'Roster satpam blok ' . ($b + 1) . ' — ' . $a['peran'],
                            'dibuat_oleh'          => auth()->id(),
                        ]);
                        $hasil['shift_ditulis']++;
                    }

                    $baris[] = ['nama' => $nama, 'peran' => $a['peran'], 'libur' => $a['libur']];
                }

                $hasil['blok'][] = [
                    'ke'      => $b + 1,
                    'dari'    => $dari->toDateString(),
                    'sampai'  => $sampai->toDateString(),
                    'anggota' => $baris,
                ];
            }
        };

        $simulasi ? $jalankan() : DB::transaction($jalankan);

        return $hasil;
    }
}
