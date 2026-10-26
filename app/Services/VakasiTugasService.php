<?php

namespace App\Services;

use App\Models\PenugasanTambahan;
use App\Models\RiwayatVakasiTugas;
use App\Models\TugasTambahan;
use Illuminate\Support\Facades\DB;

/**
 * VAKASI TUGAS TAMBAHAN — satu pintu untuk menghitung, menetapkan, dan
 * menelusuri nominalnya.
 *
 * ══ MASALAH YANG DIBERESKAN ══
 * Nominal vakasi tugas tambahan dulu dihitung di tempat berbeda dengan aturan
 * yang berbeda: rantai `??` di PayrollCalculationService, `getNominalVakasi()`
 * di dua model, dan label `sumber_vakasi` yang urutannya tidak sama dengan
 * keduanya. Akibatnya nominal bisa bergeser sendiri dan tidak ada yang bisa
 * menjelaskan mengapa.
 *
 * Diukur di produksi 26 Okt 2026:
 *  - 11 tugas nominalnya MENGIKUTI setting vakasi, jadi mengedit setting
 *    mengubah gaji mereka secara surut (setting #4 diubah 26 Sep, #9 29 Sep);
 *  - kedua setting tugas_tambahan ber-`is_aktif = false` TAPI tetap dipakai,
 *    memberi Rp 620.000 pada 11 tugas — admin yang menonaktifkannya berharap
 *    vakasinya berhenti, dan itu tidak terjadi;
 *  - 21 tugas tanpa keputusan vakasi (nominal 0 karena lubang fallback, bukan
 *    karena diputuskan), 20 di antaranya sudah punya penerima disetujui;
 *  - 114 penugasan sudah dilaporkan tetapi periodenya belum berslip, jadi
 *    nominalnya masih bisa berubah kapan saja.
 *
 * ══ ATURAN SEKARANG ══
 *  1. URUTAN SUMBER (satu-satunya, dipakai payroll maupun layar):
 *     override penerima → setting penerima → override tugas → setting tugas → 0.
 *  2. Setting hanya dipakai bila `is_aktif`. Setting nonaktif berarti berhenti
 *     dipakai — itulah arti menonaktifkannya.
 *  3. Nominal yang DITETAPKAN manual akan dikunci: ia berhenti mengikuti
 *     setting, sehingga mengedit setting tidak lagi menggeser gaji orang.
 *  4. Nominal TIDAK BOLEH diubah bila penerimanya sudah melaporkan tugas pada
 *     periode yang slipnya sudah terbit. Gaji yang sudah keluar tidak berubah
 *     diam-diam; perbaikannya lewat koreksi penggajian.
 *  5. Setiap perubahan tercatat di `riwayat_vakasi_tugas`: dari berapa ke
 *     berapa, sumbernya, oleh siapa, kapan, dengan alasan WAJIB.
 */
class VakasiTugasService
{
    /** Label sumber nominal, dari yang paling kuat. */
    public const SUMBER = [
        'override_penerima' => 'Ditetapkan untuk penerima ini',
        'setting_penerima'  => 'Setting vakasi penerima',
        'override_tugas'    => 'Ditetapkan untuk tugas ini',
        'setting_tugas'     => 'Setting vakasi tugas',
        'belum_ditetapkan'  => 'Belum ditetapkan',
    ];

    // ══════════════════════════════════════════════════════════════════════
    // MENGHITUNG
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Nominal efektif + asal-usulnya untuk SATU penerima.
     *
     * @return array{nominal:float, sumber:string, sumber_label:string,
     *               dikunci:bool, bisa_bergeser:bool, peringatan:?string}
     */
    public function untukPenerima(PenugasanTambahan $p): array
    {
        $t = $p->tugasTambahan;

        if ($p->vakasi_override !== null) {
            return $this->hasil((float) $p->vakasi_override, 'override_penerima',
                (bool) $p->vakasi_dikunci, false);
        }
        if ($s = $this->settingTerpakai($p->settingVakasi)) {
            return $this->hasil((float) $s->nominal, 'setting_penerima',
                (bool) $p->vakasi_dikunci, true);
        }
        if ($t && $t->vakasi_override !== null) {
            return $this->hasil((float) $t->vakasi_override, 'override_tugas',
                (bool) $t->vakasi_dikunci, false);
        }
        if ($t && ($s = $this->settingTerpakai($t->settingVakasi))) {
            return $this->hasil((float) $s->nominal, 'setting_tugas',
                (bool) $t->vakasi_dikunci, true);
        }

        return $this->hasil(0.0, 'belum_ditetapkan', false, true);
    }

    /** Nominal efektif + asal-usulnya untuk SATU tugas (tingkat tugas saja). */
    public function untukTugas(TugasTambahan $t): array
    {
        if ($t->vakasi_override !== null) {
            return $this->hasil((float) $t->vakasi_override, 'override_tugas',
                (bool) $t->vakasi_dikunci, false);
        }
        if ($s = $this->settingTerpakai($t->settingVakasi)) {
            return $this->hasil((float) $s->nominal, 'setting_tugas',
                (bool) $t->vakasi_dikunci, true);
        }

        return $this->hasil(0.0, 'belum_ditetapkan', false, true);
    }

    /**
     * Setting hanya dipakai bila AKTIF.
     *
     * Dulu relasinya dibaca apa adanya (`belongsTo` tanpa saringan), sehingga
     * setting yang sudah dinonaktifkan tetap membayar. Setelah nominal yang
     * ada DIBEKUKAN ke override (lihat bekukanYangMengikutiSetting), aturan ini
     * tidak lagi mengubah gaji siapa pun — ia hanya mencegah kejadian serupa.
     */
    private function settingTerpakai($setting)
    {
        return $setting && $setting->is_aktif ? $setting : null;
    }

    private function hasil(float $nominal, string $sumber, bool $dikunci, bool $ikutSetting): array
    {
        return [
            'nominal'       => $nominal,
            'sumber'        => $sumber,
            'sumber_label'  => self::SUMBER[$sumber] ?? $sumber,
            'dikunci'       => $dikunci,
            // true = nominalnya masih bisa bergeser bila setting diubah/dimatikan
            'bisa_bergeser' => $ikutSetting && !$dikunci,
            'peringatan'    => match (true) {
                $sumber === 'belum_ditetapkan' =>
                    'Vakasi belum pernah ditetapkan. Nominal 0 ini bukan keputusan — '
                    . 'begitu ada setting vakasi dipasang, nominalnya akan berubah sendiri.',
                $ikutSetting && !$dikunci =>
                    'Nominal ini mengikuti setting vakasi, jadi akan berubah bila settingnya '
                    . 'diedit atau dinonaktifkan. Tetapkan & kunci agar tidak bergeser.',
                default => null,
            },
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // MENETAPKAN
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Apakah vakasi penerima ini masih boleh diubah?
     *
     * @return string|null null = boleh; selain itu alasan penolakan
     */
    public function alasanTidakBolehUbah(PenugasanTambahan $p): ?string
    {
        if (!$p->dilaporkan_pada) {
            return null;
        }
        $tgl = \Carbon\Carbon::parse($p->dilaporkan_pada)->toDateString();
        if (app(KoreksiPembelajaranService::class)->periodeSudahBerslip($tgl)) {
            return 'Tugas ini sudah dilaporkan pada ' . $tgl
                . ' dan periode penggajiannya sudah berslip, jadi vakasinya tidak bisa diubah '
                . 'dari sini. Bereskan lewat koreksi penggajian bila perlu.';
        }

        return null;
    }

    /** Penerima mana saja pada satu tugas yang nominalnya sudah terkunci slip. */
    public function penerimaTerkunci(TugasTambahan $t): array
    {
        return $t->penugasan->filter(fn ($p) => $this->alasanTidakBolehUbah($p) !== null)
            ->map(fn ($p) => $p->tenagaPendidik?->user?->name ?? ('penugasan #' . $p->id))
            ->values()->all();
    }

    /**
     * Tetapkan nominal vakasi untuk SATU TUGAS (berlaku bagi semua penerimanya
     * yang tidak punya nominal sendiri).
     *
     * @param  float|null  $nominal  null = kembalikan ke mengikuti setting
     * @throws \DomainException
     */
    public function tetapkanTugas(
        TugasTambahan $t, ?float $nominal, string $alasan, ?int $userId, bool $kunci = true
    ): array {
        $this->pastikanAlasan($alasan);
        $this->pastikanNominalWaras($nominal);

        // Gaji yang sudah terbit tidak boleh berubah diam-diam.
        $terkunci = $this->penerimaTerkunci($t->loadMissing('penugasan.tenagaPendidik.user'));
        if ($terkunci) {
            throw new \DomainException(
                'Vakasi tugas ini tidak bisa diubah: ' . count($terkunci) . ' penerima sudah '
                . 'dilaporkan pada periode yang slipnya terbit (' . implode(', ', array_slice($terkunci, 0, 3))
                . (count($terkunci) > 3 ? ', dan ' . (count($terkunci) - 3) . ' lainnya' : '')
                . '). Ubah per penerima yang belum berslip, atau lewat koreksi penggajian.'
            );
        }

        $sebelum = $this->untukTugas($t);

        DB::transaction(function () use ($t, $nominal, $alasan, $userId, $kunci, $sebelum) {
            $t->update([
                'vakasi_override'        => $nominal,
                'vakasi_dikunci'         => $nominal !== null && $kunci,
                'vakasi_ditetapkan_oleh' => $userId,
                'vakasi_ditetapkan_pada' => now(),
            ]);

            $sesudah = $this->untukTugas($t->fresh());
            RiwayatVakasiTugas::create([
                'tugas_tambahan_id' => $t->id,
                'nominal_lama'      => $sebelum['nominal'],
                'nominal_baru'      => $sesudah['nominal'],
                'sumber_lama'       => $sebelum['sumber'],
                'sumber_baru'       => $sesudah['sumber'],
                'dikunci'           => $sesudah['dikunci'],
                'alasan'            => $alasan,
                'diubah_oleh'       => $userId,
            ]);
        });

        return $this->untukTugas($t->fresh());
    }

    /** Tetapkan nominal vakasi untuk SATU PENERIMA. */
    public function tetapkanPenerima(
        PenugasanTambahan $p, ?float $nominal, string $alasan, ?int $userId, bool $kunci = true
    ): array {
        $this->pastikanAlasan($alasan);
        $this->pastikanNominalWaras($nominal);

        if ($pesan = $this->alasanTidakBolehUbah($p)) {
            throw new \DomainException($pesan);
        }

        $sebelum = $this->untukPenerima($p);

        DB::transaction(function () use ($p, $nominal, $alasan, $userId, $kunci, $sebelum) {
            $p->update([
                'vakasi_override'        => $nominal,
                'vakasi_dikunci'         => $nominal !== null && $kunci,
                'vakasi_ditetapkan_oleh' => $userId,
                'vakasi_ditetapkan_pada' => now(),
            ]);

            $sesudah = $this->untukPenerima($p->fresh('tugasTambahan'));
            RiwayatVakasiTugas::create([
                'tugas_tambahan_id' => $p->tugas_tambahan_id,
                'penugasan_id'      => $p->id,
                'nominal_lama'      => $sebelum['nominal'],
                'nominal_baru'      => $sesudah['nominal'],
                'sumber_lama'       => $sebelum['sumber'],
                'sumber_baru'       => $sesudah['sumber'],
                'dikunci'           => $sesudah['dikunci'],
                'alasan'            => $alasan,
                'diubah_oleh'       => $userId,
            ]);
        });

        return $this->untukPenerima($p->fresh('tugasTambahan'));
    }

    /**
     * SEKALI JALAN — bekukan nominal tugas yang masih mengikuti setting.
     *
     * Menyalin nominal efektif SEKARANG ke `vakasi_override` lalu menguncinya.
     * Angkanya TIDAK berubah, jadi tidak ada gaji yang bergeser; yang hilang
     * hanyalah kemampuan setting untuk menggesernya kelak. Ini harus dijalankan
     * SEBELUM aturan "setting nonaktif tidak dipakai" mulai berlaku, kalau
     * tidak 11 tugas yang kini dibayar dari setting nonaktif akan jatuh ke 0.
     *
     * @param  bool  $simulasi  true = hanya laporkan, jangan tulis
     * @return array{diproses:int, baris:array}
     */
    public function bekukanYangMengikutiSetting(?int $userId, bool $simulasi = false): array
    {
        $baris = [];

        $tugas = TugasTambahan::with(['settingVakasi', 'penugasan'])
            ->whereNull('vakasi_override')->whereNotNull('setting_vakasi_id')->get();

        foreach ($tugas as $t) {
            $s = $t->settingVakasi;
            if (!$s) continue;

            // Nominal yang SEDANG dipakai hari ini — termasuk bila settingnya
            // sudah nonaktif, karena itulah yang nyata dibayar sekarang.
            $nominal = (float) $s->nominal;

            $baris[] = [
                'tugas_id' => $t->id,
                'judul'    => $t->judul,
                'setting'  => $s->nama . ' (' . ($s->is_aktif ? 'aktif' : 'NONAKTIF') . ')',
                'nominal'  => $nominal,
                'penerima' => $t->penugasan->where('disetujui', true)->count(),
            ];

            if ($simulasi) continue;

            DB::transaction(function () use ($t, $nominal, $userId, $s) {
                $t->update([
                    'vakasi_override'        => $nominal,
                    'vakasi_dikunci'         => true,
                    'vakasi_ditetapkan_oleh' => $userId,
                    'vakasi_ditetapkan_pada' => now(),
                ]);
                RiwayatVakasiTugas::create([
                    'tugas_tambahan_id' => $t->id,
                    'nominal_lama'      => $nominal,
                    'nominal_baru'      => $nominal,
                    'sumber_lama'       => 'setting_tugas',
                    'sumber_baru'       => 'override_tugas',
                    'dikunci'           => true,
                    'alasan'            => 'Pembekuan otomatis: nominal disalin dari setting "'
                        . $s->nama . '" agar tidak lagi berubah bila settingnya diedit'
                        . ($s->is_aktif ? '' : ' (setting sudah nonaktif)') . '.',
                    'diubah_oleh'       => $userId,
                ]);
            });
        }

        return ['diproses' => count($baris), 'baris' => $baris];
    }

    // ══════════════════════════════════════════════════════════════════════

    /**
     * SATU PERIODE JADI TANPA VAKASI — keputusan menyeluruh superadmin.
     *
     * Dipakai saat lembaga memutuskan tugas tambahan pada satu periode gaji
     * tidak diberi vakasi, padahal sebagian sudah bernominal.
     *
     * ══ MENGAPA TIDAK LEWAT alasanTidakBolehUbah() ══
     * Penjaga itu menolak perubahan bila penerimanya sudah melaporkan pada
     * periode yang `penggajian`-nya ADA — tanpa membedakan slip yang masih
     * DRAFT dari slip yang sudah difinalisasi atau dibayar. Untuk keputusan
     * tingkat periode itu terlalu kasar: selama slipnya masih draft, belum
     * difinalisasi, dan belum dibayar, tidak ada uang yang sudah keluar,
     * sehingga tidak ada yang berubah di belakang siapa pun.
     *
     * Karena itu di sini penjaganya DIGANTI, bukan dilonggarkan: SELURUH slip
     * periode itu wajib draft + belum difinalisasi + belum dibayar, dan
     * periodenya tidak boleh terkunci. Begitu satu slip saja sudah final atau
     * dibayar, metode ini menolak seluruhnya — tidak ada perubahan separuh.
     *
     * DUA LINGKUP, karena "vakasi periode ini" punya dua arti yang keduanya benar:
     *   A. tugas yang rentangnya bersinggungan dengan jendela periode (yang
     *      terlihat di daftar Tugas Tambahan saat disaring per periode), dan
     *   B. penugasan yang UANGNYA dibebankan ke periode ini walau tugasnya di
     *      luar jendela - payroll membebankan `mandiri` menurut dilaporkan_pada
     *      dan `absen_kegiatan` menurut tanggal_kegiatan. Tanpa lingkup B,
     *      Rp 160.000 tetap terbayar di Oktober 2026 (terukur 26 Okt).
     *
     * Dinolkan di DUA tingkat karena urutan sumbernya penerima-menang-atas-tugas:
     * tanpa menolkan di tingkat penerima, override atau setting milik penerima
     * akan tetap menang atas nol di tingkat tugas.
     *
     * CATATAN: ini TIDAK mengubah slip yang sudah tercetak — baris slip adalah
     * snapshot. Slip periode itu harus di-generate ulang agar angkanya ikut.
     *
     * @param  bool  $simulasi  true = hanya laporkan, jangan tulis apa pun
     * @return array{tugas:int, penugasan:int, nominal_dicabut:float, baris:array, perlu_generate_ulang:bool}
     */
    public function nolkanPeriode(
        \App\Models\PeriodePenggajian $periode,
        string $alasan,
        ?int $userId,
        bool $simulasi = false
    ): array {
        $this->pastikanAlasan($alasan);

        if (!$periode->tanggal_mulai || !$periode->tanggal_selesai) {
            throw new \DomainException('Periode ini tidak punya rentang tanggal yang jelas.');
        }
        if ($periode->dikunci_pada !== null) {
            throw new \DomainException('Periode ' . $periode->nama_bulan . ' sudah dikunci, '
                . 'vakasinya tidak bisa diubah lagi.');
        }

        // Penjaga pengganti: tidak boleh ada slip yang sudah final / dibayar.
        $slip = \App\Models\Penggajian::where('periode_penggajian_id', $periode->id)->get();
        $matang = $slip->filter(fn ($g) => $g->status === 'dibayar'
            || $g->dibayar_pada !== null || $g->difinalisasi_oleh !== null);
        if ($matang->isNotEmpty()) {
            throw new \DomainException(
                'Tidak bisa dinolkan: ' . $matang->count() . ' dari ' . $slip->count()
                . ' slip periode ' . $periode->nama_bulan . ' sudah difinalisasi atau dibayar. '
                . 'Uang yang sudah keluar tidak boleh berubah dari sini, tempuh koreksi penggajian.'
            );
        }

        $dari   = $periode->tanggal_mulai->toDateString();
        $sampai = $periode->tanggal_selesai->toDateString();

        // ── Lingkup A: tugas yang rentangnya bersinggungan dengan jendela,
        // aturan yang sama dengan saringan periode di layar Tugas Tambahan dan
        // dengan penilaian kinerja, supaya yang dinolkan persis yang terlihat.
        $tugas = TugasTambahan::with([
                'penugasan.tenagaPendidik.user', 'penugasan.settingVakasi', 'settingVakasi',
            ])
            ->where('tanggal_mulai', '<=', $sampai)
            ->where(fn ($w) => $w->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $dari))
            ->get();

        $baris = [];
        $nTugas = 0; $nPenugasan = 0; $dicabut = 0.0;

        foreach ($tugas as $t) {
            $vTugas = $this->untukTugas($t);

            // a. Tingkat penerima — wajib, karena penerima menang atas tugas.
            foreach ($t->penugasan as $pg) {
                $v = $this->untukPenerima($pg);
                if ((float) $v['nominal'] <= 0) continue;

                $baris[] = [
                    'tingkat'      => 'penerima',
                    'tugas'        => $t->judul,
                    'nama'         => $pg->tenagaPendidik?->user?->name ?? ('penugasan #' . $pg->id),
                    'nominal'      => (float) $v['nominal'],
                    'sumber'       => $v['sumber'],
                    'dilaporkan'   => $pg->dilaporkan_pada?->toDateString(),
                    'periode_uang' => $pg->dilaporkan_pada
                        ? (\App\Models\PeriodePenggajian::untukTanggal(
                            $pg->dilaporkan_pada->toDateString())?->nama_bulan ?? '(tanpa periode)')
                        : '(belum dilaporkan)',
                ];
                $dicabut += (float) $v['nominal'];
                $nPenugasan++;

                if (!$simulasi) {
                    $sebelum = $v;
                    DB::transaction(function () use ($pg, $alasan, $userId, $sebelum, $t) {
                        $pg->update([
                            'vakasi_override'        => 0,
                            'vakasi_dikunci'         => true,
                            'vakasi_ditetapkan_oleh' => $userId,
                            'vakasi_ditetapkan_pada' => now(),
                        ]);
                        $sesudah = $this->untukPenerima($pg->fresh('tugasTambahan'));
                        RiwayatVakasiTugas::create([
                            'tugas_tambahan_id' => $t->id,
                            'penugasan_id'      => $pg->id,
                            'nominal_lama'      => $sebelum['nominal'],
                            'nominal_baru'      => $sesudah['nominal'],
                            'sumber_lama'       => $sebelum['sumber'],
                            'sumber_baru'       => $sesudah['sumber'],
                            'dikunci'           => true,
                            'alasan'            => $alasan,
                            'diubah_oleh'       => $userId,
                        ]);
                    });
                }
            }

            // b. Tingkat tugas — agar penerima yang ditambahkan KELAK juga nol.
            if ((float) $vTugas['nominal'] > 0) {
                $baris[] = [
                    'tingkat'      => 'tugas',
                    'tugas'        => $t->judul,
                    'nama'         => '(seluruh penerima berikutnya)',
                    'nominal'      => (float) $vTugas['nominal'],
                    'sumber'       => $vTugas['sumber'],
                    'dilaporkan'   => null,
                    'periode_uang' => '-',
                ];
                $nTugas++;

                if (!$simulasi) {
                    $sebelum = $vTugas;
                    DB::transaction(function () use ($t, $alasan, $userId, $sebelum) {
                        $t->update([
                            'vakasi_override'        => 0,
                            'vakasi_dikunci'         => true,
                            'vakasi_ditetapkan_oleh' => $userId,
                            'vakasi_ditetapkan_pada' => now(),
                        ]);
                        $sesudah = $this->untukTugas($t->fresh());
                        RiwayatVakasiTugas::create([
                            'tugas_tambahan_id' => $t->id,
                            'nominal_lama'      => $sebelum['nominal'],
                            'nominal_baru'      => $sesudah['nominal'],
                            'sumber_lama'       => $sebelum['sumber'],
                            'sumber_baru'       => $sesudah['sumber'],
                            'dikunci'           => true,
                            'alasan'            => $alasan,
                            'diubah_oleh'       => $userId,
                        ]);
                    });
                }
            }
        }

        // ── Lingkup B: penugasan yang UANGNYA dibebankan ke periode ini ──────
        // Tugasnya boleh di luar jendela: payroll membebankan vakasi `mandiri`
        // menurut `dilaporkan_pada`, dan `absen_kegiatan` menurut
        // `tanggal_kegiatan` — bukan menurut rentang tugasnya. Terukur 26 Okt
        // 2026: 6 penugasan dari 3 tugas yang berjalan awal September dilaporkan
        // 26–27 Sep, sehingga Rp 160.000 tetap terbayar di periode Oktober
        // walaupun seluruh tugas dalam jendela sudah dinolkan.
        //
        // Di sini HANYA tingkat penerima yang dinolkan, tidak tingkat tugas:
        // tugas itu milik periode lain dan boleh punya penerima lain yang
        // uangnya jatuh di periode lain pula.
        $sudah = collect();
        foreach ($tugas as $t) {
            $sudah = $sudah->merge($t->penugasan->pluck('id'));
        }

        $mandiri = PenugasanTambahan::with(['tugasTambahan.settingVakasi', 'settingVakasi', 'tenagaPendidik.user'])
            ->whereNotNull('dilaporkan_pada')
            ->whereDate('dilaporkan_pada', '>=', $dari)
            ->whereDate('dilaporkan_pada', '<=', $sampai)
            ->whereNotIn('id', $sudah->all())
            ->get();

        $kegiatanIds = \App\Models\AbsensiKegiatan::where('status', 'selesai')
            ->whereDate('tanggal_kegiatan', '>=', $dari)
            ->whereDate('tanggal_kegiatan', '<=', $sampai)
            ->pluck('penugasan_id')->filter()->unique();

        $kegiatan = $kegiatanIds->isEmpty()
            ? collect()
            : PenugasanTambahan::with(['tugasTambahan.settingVakasi', 'settingVakasi', 'tenagaPendidik.user'])
                ->whereIn('id', $kegiatanIds->all())
                ->whereNotIn('id', $sudah->merge($mandiri->pluck('id'))->all())
                ->get();

        foreach ($mandiri->merge($kegiatan) as $pg) {
            $v = $this->untukPenerima($pg);
            if ((float) $v['nominal'] <= 0) continue;

            $baris[] = [
                'tingkat'      => 'penerima (luar jendela tugas)',
                'tugas'        => $pg->tugasTambahan?->judul ?? '-',
                'nama'         => $pg->tenagaPendidik?->user?->name ?? ('penugasan #' . $pg->id),
                'nominal'      => (float) $v['nominal'],
                'sumber'       => $v['sumber'],
                'dilaporkan'   => $pg->dilaporkan_pada?->toDateString(),
                'periode_uang' => $periode->nama_bulan,
            ];
            $dicabut += (float) $v['nominal'];
            $nPenugasan++;

            if (!$simulasi) {
                $sebelum = $v;
                DB::transaction(function () use ($pg, $alasan, $userId, $sebelum) {
                    $pg->update([
                        'vakasi_override'        => 0,
                        'vakasi_dikunci'         => true,
                        'vakasi_ditetapkan_oleh' => $userId,
                        'vakasi_ditetapkan_pada' => now(),
                    ]);
                    $sesudah = $this->untukPenerima($pg->fresh('tugasTambahan'));
                    RiwayatVakasiTugas::create([
                        'tugas_tambahan_id' => $pg->tugas_tambahan_id,
                        'penugasan_id'      => $pg->id,
                        'nominal_lama'      => $sebelum['nominal'],
                        'nominal_baru'      => $sesudah['nominal'],
                        'sumber_lama'       => $sebelum['sumber'],
                        'sumber_baru'       => $sesudah['sumber'],
                        'dikunci'           => true,
                        'alasan'            => $alasan,
                        'diubah_oleh'       => $userId,
                    ]);
                });
            }
        }

        return [
            'tugas'           => $nTugas,
            'penugasan'       => $nPenugasan,
            'nominal_dicabut' => $dicabut,
            'baris'           => $baris,
            // Slip adalah snapshot: tanpa generate ulang, angka di slip tetap lama.
            'perlu_generate_ulang' => $slip->isNotEmpty(),
        ];
    }

    private function pastikanAlasan(string $alasan): void
    {
        if (mb_strlen(trim($alasan)) < 5) {
            throw new \DomainException('Alasan perubahan vakasi wajib diisi (minimal 5 huruf) — '
                . 'inilah yang menjawab "kenapa vakasi ini berubah" di kemudian hari.');
        }
    }

    private function pastikanNominalWaras(?float $nominal): void
    {
        if ($nominal === null) return;
        if ($nominal < 0) {
            throw new \DomainException('Nominal vakasi tidak boleh minus.');
        }
        if ($nominal > 100_000_000) {
            throw new \DomainException('Nominal vakasi terlalu besar (maksimal Rp 100.000.000). '
                . 'Periksa jumlah nolnya.');
        }
    }
}
