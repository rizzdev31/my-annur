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
