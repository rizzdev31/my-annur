<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\LiburPembelajaran;
use App\Models\PeriodePenggajian;
use App\Models\SettingVakasi;
use App\Models\TahunAjaran;
use App\Models\TenagaPendidik;
use App\Models\Ujian;
use App\Models\UjianSesi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UJIAN SEKOLAH — satu pintu untuk paket ujian, sesinya, dan penjaganya.
 *
 * Aturan pokok yang menjaga semuanya tetap sinkron:
 *
 *  1. SESI UJIAN = JADWAL SEHARI. Saat penjaga ditunjuk, dibuatkan baris
 *     `jadwal_mengajar` dengan guru = penjaga, mapel = mata ujian, dan masa
 *     berlaku hanya tanggal itu. Dengan begitu absensi, roster santri, laporan,
 *     papan piket, kinerja, dan INVAL ikut bekerja tanpa jalur baru.
 *
 *  2. PEMBELAJARAN REGULER DIMATIKAN lewat LiburPembelajaran — bukan di sini —
 *     dengan `isi_absensi_santri = false`. Tanpa itu santri akan punya DUA
 *     catatan kehadiran pada hari yang sama (auto-hadir dari libur + roster
 *     ujian) dan persentase bulanannya terhitung ganda.
 *
 *  3. JADWAL UJIAN TIDAK BOLEH IKUT DILIBURKAN. `LiburMengajarService` sudah
 *     menyaring `bukanUjian()`; jangan dilepas, karena sesi ujian justru
 *     PENGGANTI pembelajaran yang diliburkan.
 *
 *  4. VAKASI PER SESI, bukan per JP. Sesi ujian dikecualikan dari vakasi
 *     mengajar per-JP (PayrollCalculationService), dan bila di-inval, yang
 *     dibayar adalah penggantinya dengan nominal penjaga yang sama.
 */
class UjianService
{
    /** Tipe setting vakasi untuk penjaga ujian. */
    public const TIPE_VAKASI = 'jaga_ujian';

    // ══════════════════════════════════════════════════════════════════════
    // Paket
    // ══════════════════════════════════════════════════════════════════════

    public function buat(array $d, ?int $userId = null): Ujian
    {
        return DB::transaction(function () use ($d, $userId) {
            $ujian = Ujian::create([
                'nama'            => $d['nama'],
                'tanggal_mulai'   => $d['tanggal_mulai'],
                'tanggal_selesai' => $d['tanggal_selesai'] ?? null,
                'keterangan'      => $d['keterangan'] ?? null,
                'is_aktif'        => true,
                'dibuat_oleh'     => $userId,
            ]);

            return $ujian;
        });
    }

    /**
     * Matikan pembelajaran reguler untuk kelas-kelas yang berujian.
     *
     * Rosternya SENGAJA tidak diisi (`isi_absensi_santri = false`): kehadiran
     * hari itu dicatat oleh sesi ujian, sehingga tidak ada catatan ganda.
     */
    public function sinkronLiburPembelajaran(Ujian $ujian): ?LiburPembelajaran
    {
        $kelasIds = $ujian->sesi()->pluck('kelas_id')->unique()->values();
        if ($kelasIds->isEmpty()) return null;

        $libur = $ujian->liburPembelajaran;
        $atribut = [
            'nama'               => 'Ujian: ' . $ujian->nama,
            'tanggal'            => $ujian->tanggal_mulai->toDateString(),
            'tanggal_selesai'    => $ujian->tanggal_akhir->toDateString(),
            'cakupan'            => 'kelas',
            'jenis_kelas'        => null,
            'jam_mulai'          => null,
            'jam_selesai'        => null,
            'materi_jurnal'      => 'Ujian: ' . $ujian->nama,
            'hitung_jp'          => true,
            // Kehadiran santri dicatat lewat sesi ujian — jangan dobel.
            'isi_absensi_santri' => false,
            'status_santri'      => 'hadir',
            'keterangan'         => 'Dibuat otomatis oleh paket ujian.',
            'is_aktif'           => true,
            'dibuat_oleh'        => $ujian->dibuat_oleh,
        ];

        if ($libur && !$libur->is_dibatalkan) {
            $libur->update($atribut);
        } else {
            $libur = LiburPembelajaran::create($atribut);
            $ujian->update(['libur_pembelajaran_id' => $libur->id]);
        }

        $libur->kelas()->sync($kelasIds->all());
        LiburMengajarService::lupakanPeta();

        app(LiburMengajarService::class)->isiPembelajaran($libur->fresh());

        return $libur->fresh();
    }

    /** Batalkan paket: hapus jadwal & absensi bentukannya, lalu pulihkan pembelajaran. */
    public function batalkan(Ujian $ujian, ?string $alasan = null, ?int $userId = null): array
    {
        $hasil = ['sesi' => 0, 'jadwal' => 0, 'absensi' => 0, 'terbayar' => 0];

        DB::transaction(function () use ($ujian, $alasan, $userId, &$hasil) {
            foreach ($ujian->sesi as $sesi) {
                // Sesi yang vakasinya sudah dibayar tidak dibongkar — slipnya sudah terbit.
                if ($sesi->vakasi_dibayar) { $hasil['terbayar']++; continue; }

                $hasil['absensi'] += $this->hapusJadwalSesi($sesi);
                $hasil['sesi']++;
            }

            $ujian->update([
                'is_aktif'          => false,
                'dibatalkan_pada'   => now(),
                'alasan_pembatalan' => $alasan,
                'dibatalkan_oleh'   => $userId,
            ]);

            if ($ujian->liburPembelajaran && !$ujian->liburPembelajaran->is_dibatalkan) {
                app(LiburMengajarService::class)->batalkanPembelajaran(
                    $ujian->liburPembelajaran, 'Paket ujian dibatalkan', $userId
                );
            }
        });

        LiburMengajarService::lupakanPeta();
        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Sesi
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Tambah satu sesi ujian.
     *
     * @throws \DomainException bila tanggalnya di luar paket, periodenya terkunci,
     *         atau kelasnya sudah punya sesi pada jam yang beririsan.
     */
    public function tambahSesi(Ujian $ujian, array $d): UjianSesi
    {
        $tanggal = Carbon::parse($d['tanggal'])->toDateString();

        if (!in_array($tanggal, $ujian->tanggalCakupan(), true)) {
            throw new \DomainException('Tanggal sesi harus berada dalam rentang paket ujian.');
        }
        if ($this->periodeTerkunci($tanggal)) {
            throw new \DomainException('Periode penggajian tanggal itu sudah terkunci atau slipnya sudah terbit.');
        }
        if (\App\Models\HariLibur::isLibur($tanggal)) {
            throw new \DomainException('Tanggal itu hari libur penuh — sesi ujian tidak akan pernah berjalan.');
        }

        $jamMulai   = $this->jam($d['jam_mulai']);
        $jamSelesai = $this->jam($d['jam_selesai']);
        if ($jamSelesai <= $jamMulai) {
            throw new \DomainException('Jam selesai harus setelah jam mulai.');
        }

        if ($bentrok = $this->sesiKelasBentrok($ujian, (int) $d['kelas_id'], $tanggal, $jamMulai, $jamSelesai)) {
            throw new \DomainException("Kelas ini sudah punya sesi ujian {$bentrok->jamLabel()} pada tanggal itu.");
        }

        $menit = (int) round((strtotime("1970-01-01 {$jamSelesai}") - strtotime("1970-01-01 {$jamMulai}")) / 60);

        $sesi = UjianSesi::create([
            'ujian_id'          => $ujian->id,
            'tanggal'           => $tanggal,
            'jam_mulai'         => $jamMulai,
            'jam_selesai'       => $jamSelesai,
            'kelas_id'          => (int) $d['kelas_id'],
            'mata_pelajaran_id' => (int) $d['mata_pelajaran_id'],
            'ruangan'           => $d['ruangan'] ?? null,
            // JP hanya catatan; vakasi penjaga tetap per sesi.
            'jumlah_jp'         => max(1, (int) round($menit / 45)),
            'catatan'           => $d['catatan'] ?? null,
        ]);

        if (!empty($d['penjaga_id'])) {
            $this->tunjukPenjaga($sesi, (int) $d['penjaga_id']);
        }

        return $sesi->fresh();
    }

    /**
     * Susun banyak sesi sekaligus: setiap slot jam × setiap kelas × setiap tanggal.
     * Bentrok & tanggal terkunci dilewati dan dilaporkan, bukan menggagalkan semuanya.
     *
     * @param array $slot  [['tanggal'=>, 'jam_mulai'=>, 'jam_selesai'=>, 'mata_pelajaran_id'=>], ...]
     * @param int[] $kelasIds
     */
    public function generate(Ujian $ujian, array $slot, array $kelasIds): array
    {
        $hasil = ['dibuat' => 0, 'dilewati' => 0, 'alasan' => []];

        foreach ($slot as $s) {
            foreach ($kelasIds as $kelasId) {
                try {
                    $this->tambahSesi($ujian, $s + ['kelas_id' => $kelasId]);
                    $hasil['dibuat']++;
                } catch (\DomainException $e) {
                    $hasil['dilewati']++;
                    $nama = Kelas::find($kelasId)?->nama ?? "kelas #{$kelasId}";
                    $hasil['alasan'][] = "{$s['tanggal']} {$nama}: " . $e->getMessage();
                }
            }
        }

        return $hasil;
    }

    /** Hapus satu sesi beserta jadwal & absensi bentukannya. */
    public function hapusSesi(UjianSesi $sesi): void
    {
        if ($sesi->vakasi_dibayar) {
            throw new \DomainException('Sesi ini vakasinya sudah dibayar — tidak dapat dihapus.');
        }

        DB::transaction(function () use ($sesi) {
            $this->hapusJadwalSesi($sesi);
            $sesi->delete();
        });
        LiburMengajarService::lupakanPeta();
    }

    // ══════════════════════════════════════════════════════════════════════
    // Penjaga
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Tunjuk/ganti penjaga. Inilah yang membuat (atau memperbarui) jadwal sehari
     * sesi ini — tanpa penjaga, tidak ada jadwal, sehingga tidak ada yang ditagih
     * absensinya.
     *
     * @throws \DomainException bila penjaga berhalangan (lihat alasanTakBolehJaga).
     */
    public function tunjukPenjaga(UjianSesi $sesi, int $penjagaId): UjianSesi
    {
        if ($alasan = $this->alasanTakBolehJaga($sesi, $penjagaId)) {
            throw new \DomainException($alasan);
        }

        return DB::transaction(function () use ($sesi, $penjagaId) {
            $lamaJadwal = $sesi->jadwal;

            // Penjaga diganti sementara sesinya SUDAH diabsen → absensinya ikut
            // tidak sah, jadi dibersihkan agar tidak menempel pada orang yang salah.
            if ($lamaJadwal && (int) $sesi->penjaga_id !== $penjagaId) {
                AbsensiMengajar::where('jadwal_mengajar_id', $lamaJadwal->id)->delete();
            }

            $sesi->update([
                'penjaga_id'     => $penjagaId,
                // Snapshot nominal: tarif boleh berubah tanpa mengubah slip terbit.
                'nominal_vakasi' => $sesi->vakasi_dibayar
                    ? $sesi->nominal_vakasi
                    : $this->nominalVakasi($penjagaId),
            ]);

            $this->pastikanJadwal($sesi->fresh());
            LiburMengajarService::lupakanPeta();

            return $sesi->fresh();
        });
    }

    /** Lepas penjaga (sesi kembali "belum ada penjaga") beserta jadwalnya. */
    public function lepasPenjaga(UjianSesi $sesi): UjianSesi
    {
        if ($sesi->vakasi_dibayar) {
            throw new \DomainException('Sesi ini vakasinya sudah dibayar — penjaganya tidak dapat dilepas.');
        }

        DB::transaction(function () use ($sesi) {
            $this->hapusJadwalSesi($sesi);
            $sesi->update(['penjaga_id' => null, 'nominal_vakasi' => 0]);
        });
        LiburMengajarService::lupakanPeta();

        return $sesi->fresh();
    }

    /**
     * Mengapa tendik ini tidak boleh menjaga sesi tsb (null = boleh).
     *
     * Pemeriksaannya LINTAS DUA DUNIA: jadwal pembelajaran reguler yang masih
     * berjalan DAN sesi ujian lain. Pembelajaran yang sudah diliburkan tidak
     * dihitung bentrok — justru guru itulah kandidat terbaik pada hari ujian.
     */
    public function alasanTakBolehJaga(UjianSesi $sesi, int $tpId): ?string
    {
        $tp = TenagaPendidik::find($tpId);
        if (!$tp || !$tp->is_aktif) return 'Tenaga pendidik tidak aktif.';

        $tanggal = $sesi->tanggal->toDateString();

        // 1. Izin resmi yang disetujui pada tanggal itu.
        $izin = \App\Models\PengajuanIzin::where('tenaga_pendidik_id', $tpId)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->with('jenisPengajuan')->first();
        if ($izin) {
            return 'Sedang izin (' . ($izin->jenisPengajuan?->nama ?? 'izin') . ') pada tanggal itu.';
        }

        // 2. Sesi ujian lain yang beririsan jam.
        $ujianLain = UjianSesi::where('penjaga_id', $tpId)
            ->whereDate('tanggal', $tanggal)
            ->where('id', '!=', $sesi->id)
            ->where('jam_mulai', '<', $sesi->jam_selesai)
            ->where('jam_selesai', '>', $sesi->jam_mulai)
            ->with('kelas:id,nama')->first();
        if ($ujianLain) {
            return 'Sudah menjaga ' . ($ujianLain->kelas?->nama ?? 'kelas lain')
                . ' pada ' . $ujianLain->jamLabel() . '.';
        }

        // 3. Jadwal pembelajaran reguler yang MASIH BERJALAN pada jam itu.
        $diliburkan = app(LiburMengajarService::class)->petaPembelajaran($tanggal);
        $bentrok = JadwalMengajar::with(['mataPelajaran:id,nama', 'kelasRel:id,nama'])
            ->where('tenaga_pendidik_id', $tpId)
            ->where('hari', TimezoneHelper::namaHariDB($sesi->tanggal))
            ->where('is_aktif', true)
            ->berlakuPada($tanggal)
            ->bukanUjian()
            ->where('jam_mulai', '<', $sesi->jam_selesai)
            ->where('jam_selesai', '>', $sesi->jam_mulai)
            ->get()
            ->reject(fn ($j) => isset($diliburkan[$j->id]))
            ->first();
        if ($bentrok) {
            return 'Masih ada pembelajaran ' . ($bentrok->mataPelajaran?->nama ?? '')
                . ' ' . ($bentrok->kelasRel?->nama ?? '') . ' pada jam itu.';
        }

        return null;
    }

    /**
     * Kandidat penjaga untuk satu sesi — SEMUA tendik aktif, masing-masing dengan
     * alasan bila tidak bisa, supaya admin melihat gambaran utuh (bukan daftar
     * yang menyusut tanpa penjelasan) sekaligus beban jaga masing-masing.
     */
    public function calonPenjaga(UjianSesi $sesi): Collection
    {
        $tanggal = $sesi->tanggal->toDateString();

        $beban = UjianSesi::whereDate('tanggal', $tanggal)->whereNotNull('penjaga_id')
            ->selectRaw('penjaga_id, COUNT(*) n')->groupBy('penjaga_id')->pluck('n', 'penjaga_id');
        $bebanPaket = UjianSesi::where('ujian_id', $sesi->ujian_id)->whereNotNull('penjaga_id')
            ->selectRaw('penjaga_id, COUNT(*) n')->groupBy('penjaga_id')->pluck('n', 'penjaga_id');

        return TenagaPendidik::where('is_aktif', true)->with('user:id,name')->get()
            ->map(function ($tp) use ($sesi, $beban, $bebanPaket) {
                $alasan = $this->alasanTakBolehJaga($sesi, $tp->id);
                return [
                    'id'           => $tp->id,
                    'nama'         => $tp->user?->name ?? '—',
                    'boleh'        => $alasan === null,
                    'alasan'       => $alasan,
                    'jaga_hari_ini'=> (int) ($beban[$tp->id] ?? 0),
                    'jaga_paket'   => (int) ($bebanPaket[$tp->id] ?? 0),
                ];
            })
            ->sortBy([['boleh', 'desc'], ['jaga_paket', 'asc'], ['nama', 'asc']])
            ->values();
    }

    /**
     * Sebar penjaga otomatis & merata ke sesi yang belum berpenjaga.
     * Yang bebannya paling sedikit dapat lebih dulu; sesi yang tak punya kandidat
     * dilewati dan dilaporkan.
     */
    public function sebarPenjaga(Ujian $ujian): array
    {
        $hasil = ['ditunjuk' => 0, 'gagal' => 0, 'alasan' => []];

        foreach ($ujian->sesi()->belumBerpenjaga()->get() as $sesi) {
            $calon = $this->calonPenjaga($sesi)->firstWhere('boleh', true);
            if (!$calon) {
                $hasil['gagal']++;
                $hasil['alasan'][] = $sesi->tanggal->toDateString() . ' ' . $sesi->jamLabel()
                    . ' ' . ($sesi->kelas?->nama ?? '—') . ': tidak ada guru yang bebas.';
                continue;
            }
            try {
                $this->tunjukPenjaga($sesi, (int) $calon['id']);
                $hasil['ditunjuk']++;
            } catch (\DomainException $e) {
                $hasil['gagal']++;
                $hasil['alasan'][] = $sesi->tanggal->toDateString() . ': ' . $e->getMessage();
            }
        }

        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Inval penjaga (oleh admin)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Penjaga berhalangan → alihkan sesi ini ke guru lain.
     *
     * Memakai mekanisme inval yang sudah ada (`digantikan_oleh` pada absensi
     * mengajar), sehingga: penjaga asli menjadi netral di kinerja, penggantinya
     * yang dinilai, dan VAKASI ikut pindah ke pengganti dengan nominal penjaga
     * yang sama (lihat UjianSesi::penjagaAktual()).
     *
     * Berbeda dengan inval mengajar biasa, di sini TIDAK disyaratkan adanya izin
     * resmi: keputusan Bapak 8 Okt 2026 menyerahkan inval ujian sepenuhnya ke
     * admin — penjaga bisa berhalangan mendadak tanpa pernah mengajukan izin.
     * Alasannya wajib dicatat sebagai gantinya.
     *
     * @throws \DomainException
     */
    public function invalPenjaga(UjianSesi $sesi, int $penggantiId, ?string $alasan = null): AbsensiMengajar
    {
        if (!$sesi->penjaga_id) {
            throw new \DomainException('Sesi ini belum punya penjaga — tunjuk penjaga dulu, bukan inval.');
        }
        if ($penggantiId === (int) $sesi->penjaga_id) {
            throw new \DomainException('Pengganti harus guru lain, bukan penjaga yang sama.');
        }
        $jadwal = $sesi->jadwal;
        if (!$jadwal) {
            throw new \DomainException('Jadwal sesi ini belum terbentuk.');
        }
        if ($sesi->vakasi_dibayar) {
            throw new \DomainException('Vakasi sesi ini sudah dibayar — tidak dapat dialihkan lagi.');
        }
        // Penggantinya harus benar-benar bebas: aturannya sama dengan daftar
        // kandidat, supaya pilihan yang tampil tidak pernah ditolak di sini.
        if ($tolak = $this->alasanTakBolehJaga($sesi, $penggantiId)) {
            throw new \DomainException($tolak);
        }

        $tanggal = $sesi->tanggal->toDateString();
        $ada = $sesi->absensi();

        // Sesi yang sudah benar-benar dijaga tidak boleh dialihkan — vakasinya
        // sudah menjadi hak orang yang menjaganya.
        if ($ada && in_array($ada->status, ['terlaksana', 'hadir'], true)) {
            throw new \DomainException('Sesi ini sudah diabsen penjaganya — tidak dapat dialihkan.');
        }
        if ($ada && $ada->status === 'pengganti' && (int) $ada->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sebelumnya sudah menjaga sesi ini.');
        }

        return DB::transaction(function () use ($sesi, $jadwal, $penggantiId, $tanggal, $alasan) {
            return AbsensiMengajar::updateOrCreate(
                ['jadwal_mengajar_id' => $jadwal->id, 'tanggal' => $tanggal],
                [
                    'tenaga_pendidik_id' => $sesi->penjaga_id,   // jejak penjaga asli
                    'digantikan_oleh'    => $penggantiId,        // yang menjaga & dibayar
                    'status'             => 'pengganti',
                    'jp_terlaksana'      => 0,                   // belum dijaga → belum dibayar
                    // Tanpa syarat izin, alasan inilah satu-satunya jejak keputusan admin.
                    'keterangan'         => 'Inval penjaga ujian'
                        . ($alasan ? ' — ' . $alasan : ''),
                ]
            );
        });
    }

    /** Batalkan inval: sesi kembali menjadi tanggung jawab penjaga yang ditunjuk. */
    public function batalkanInval(UjianSesi $sesi): void
    {
        $am = $sesi->absensi();
        if (!$am || !$am->digantikan_oleh) {
            throw new \DomainException('Sesi ini tidak sedang di-inval.');
        }
        if ($am->status === 'pengganti' && (int) $am->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sudah menjaga sesi ini — pembatalan akan menghapus bukti jaganya.');
        }

        $am->delete();   // kembali ke keadaan "belum ada catatan"; roster ikut terhapus
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pembantu
    // ══════════════════════════════════════════════════════════════════════

    /** Buat/perbarui jadwal sehari milik sesi ini. */
    private function pastikanJadwal(UjianSesi $sesi): JadwalMengajar
    {
        $kelas = Kelas::find($sesi->kelas_id);
        $ta    = TahunAjaran::where('is_aktif', true)->first()
            ?? TahunAjaran::orderByDesc('id')->first();
        $tanggal = $sesi->tanggal->toDateString();

        $jadwal = JadwalMengajar::updateOrCreate(
            ['ujian_sesi_id' => $sesi->id],
            [
                'tahun_ajaran_id'    => $ta?->id,
                'tenaga_pendidik_id' => $sesi->penjaga_id,
                'mata_pelajaran_id'  => $sesi->mata_pelajaran_id,
                'kelas_id'           => $sesi->kelas_id,
                'kelas'              => $kelas?->nama ?? '—',
                'hari'               => TimezoneHelper::namaHariDB($sesi->tanggal),
                'jam_mulai'          => $sesi->jam_mulai,
                'jam_selesai'        => $sesi->jam_selesai,
                'jumlah_jp'          => $sesi->jumlah_jp,
                'ruangan'            => $sesi->ruangan,
                'is_aktif'           => true,
                // Hanya berlaku pada tanggalnya — inilah yang mencegah sesi ujian
                // muncul lagi setiap pekan di hari yang sama.
                'berlaku_mulai'      => $tanggal,
                'berlaku_selesai'    => $tanggal,
            ]
        );

        return $jadwal;
    }

    /** Hapus jadwal sesi + absensinya. Return jumlah absensi yang terhapus. */
    private function hapusJadwalSesi(UjianSesi $sesi): int
    {
        $jadwal = $sesi->jadwal;
        if (!$jadwal) return 0;

        $n = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->count();
        AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)->delete();  // roster ikut (cascade)
        $jadwal->delete();

        return $n;
    }

    /** Nominal vakasi jaga ujian yang berlaku untuk tendik tsb. */
    public function nominalVakasi(int $tpId): float
    {
        $tp = TenagaPendidik::find($tpId);
        if (!$tp) return 0.0;

        $setting = SettingVakasi::where('tipe_aktivitas', self::TIPE_VAKASI)
            ->where('is_aktif', true)->get()
            ->first(function ($v) use ($tp) {
                if ($v->berlaku_untuk_semua) return true;
                if ($v->lingkup === 'per_individu') {
                    return in_array($tp->id, (array) $v->tenaga_pendidik_ids, true);
                }
                if ($v->lingkup === 'per_jabatan') {
                    return in_array($tp->jabatan_id, (array) $v->jabatan_ids, true);
                }
                return false;
            });

        return (float) ($setting->nominal ?? 0);
    }

    private function sesiKelasBentrok(Ujian $ujian, int $kelasId, string $tanggal, string $jamMulai, string $jamSelesai): ?UjianSesi
    {
        return UjianSesi::where('kelas_id', $kelasId)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai)
            ->first();
    }

    private function periodeTerkunci(string $tanggal): bool
    {
        $periode = PeriodePenggajian::untukTanggal($tanggal);
        if (!$periode) return false;

        return $periode->dikunci_pada !== null || $periode->penggajian()->exists();
    }

    private function jam(string $jam): string
    {
        return strlen($jam) === 5 ? $jam . ':00' : $jam;
    }
}
