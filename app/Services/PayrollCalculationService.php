<?php

namespace App\Services;

use App\Models\TenagaPendidik;
use App\Models\PeriodePenggajian;
use App\Models\Penggajian;
use App\Models\DetailPenggajian;
use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\LemburPeserta;
use App\Models\PenugasanTambahan;
use App\Models\AbsensiKegiatan;
use App\Models\AbsensiKegiatanPeserta;
use App\Models\SettingVakasi;
use App\Models\SettingPotongan;
use App\Models\HariLibur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollCalculationService
{
    /**
     * Hitung gaji 1 tenaga pendidik untuk 1 periode.
     * Full exception handling — setiap komponen dibungkus try-catch
     * agar 1 komponen error tidak membatalkan keseluruhan penggajian.
     */
    public function hitung(
        TenagaPendidik $guru,
        PeriodePenggajian $periode,
        bool $dryRun = false
    ): array {
        $errors = [];  // Kumpulkan semua error per komponen

        $tanggalMulai   = Carbon::parse($periode->tanggal_mulai);
        $tanggalSelesai = Carbon::parse($periode->tanggal_selesai);

        // ── 1. Gaji Pokok ─────────────────────────────────────────────────────
        $gajiPokok           = 0;
        $detailGajiPokokList = collect();
        try {
            $gajiPokok           = $guru->getGajiPokokAktif();
            $detailGajiPokokList = $guru->getDetailGajiPokokPerJabatan();
        } catch (\Throwable $e) {
            $errors[] = "Gaji pokok: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] gaji_pokok error: " . $e->getMessage());
        }

        // ── 2. Absensi Harian ─────────────────────────────────────────────────
        $rekapAbsen  = $this->defaultRekapAbsen();
        $vakasiAbsen = ['total' => 0, 'detail' => []];
        try {
            $rekapAbsen  = $this->hitungRekapAbsensiHarian($guru, $tanggalMulai, $tanggalSelesai);
            $vakasiAbsen = $this->hitungVakasiAbsenHarian($guru, $rekapAbsen);
        } catch (\Throwable $e) {
            $errors[] = "Absensi harian: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] absen_harian error: " . $e->getMessage());
        }

        // ── 3. Vakasi Mengajar ────────────────────────────────────────────────
        $rekapMengajar  = ['total_jp' => 0, 'ids' => []];
        $vakasiMengajar = ['total' => 0, 'detail' => []];
        try {
            $rekapMengajar  = $this->hitungRekapMengajar($guru, $tanggalMulai, $tanggalSelesai);
            $vakasiMengajar = $this->hitungVakasiMengajar($guru, $rekapMengajar);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi mengajar: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_mengajar error: " . $e->getMessage());
        }

        // ── 4. Tugas Jabatan → TANPA VAKASI (dialihkan ke penilaian KINERJA) ──
        // Kebijakan baru: penyelesaian tugas jabatan adalah faktor kelayakan
        // jabatan yang dinilai di kinerja, BUKAN dibayar sebagai vakasi.
        $vakasiTugasJabatan = ['total' => 0, 'detail' => []];

        // ── 5. Vakasi Tugas Tambahan ──────────────────────────────────────────
        $vakasiTugasTambahan = ['total' => 0, 'detail' => []];
        try {
            $vakasiTugasTambahan = $this->hitungVakasiTugasTambahan($guru, $tanggalMulai, $tanggalSelesai);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi tugas tambahan: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_tugas_tambahan error: " . $e->getMessage());
        }

        // ── 6. Vakasi Peserta Kegiatan ────────────────────────────────────────
        // Guru yang diabsen di kegiatan mendapat vakasi yang sama dengan pengabsen
        $vakasiPesertaKegiatan = ['total' => 0, 'detail' => []];
        try {
            $vakasiPesertaKegiatan = $this->hitungVakasiPesertaKegiatan($guru, $tanggalMulai, $tanggalSelesai);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi peserta kegiatan: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_peserta_kegiatan error: " . $e->getMessage());
        }

        // ── 6b. Vakasi Lembur ─────────────────────────────────────────────────
        // Lembur SAH (otomatis/manual) pada periode → flat per event dari setting.
        $vakasiLembur = ['total' => 0, 'detail' => []];
        try {
            $vakasiLembur = $this->hitungVakasiLembur($guru, $tanggalMulai, $tanggalSelesai);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi lembur: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_lembur error: " . $e->getMessage());
        }

        // ── 6c. Vakasi Piket ──────────────────────────────────────────────────
        // Flat per hari penugasan piket yang laporan hariannya sudah diisi.
        $vakasiPiket = ['total' => 0, 'detail' => []];
        try {
            $vakasiPiket = $this->hitungVakasiPiket($guru, $tanggalMulai, $tanggalSelesai, $periode->id);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi piket: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_piket error: " . $e->getMessage());
        }

        // Flat per pertemuan ekstrakurikuler yang absensinya sudah diselesaikan pembina.
        $vakasiEkskul = ['total' => 0, 'detail' => []];
        try {
            $vakasiEkskul = $this->hitungVakasiEkstrakurikuler($guru, $tanggalMulai, $tanggalSelesai);
        } catch (\Throwable $e) {
            $errors[] = "Vakasi ekstrakurikuler: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] vakasi_ekstrakurikuler error: " . $e->getMessage());
        }

        // ── 7. Potongan ───────────────────────────────────────────────────────
        $potongan = ['keterlambatan' => 0, 'alfa' => 0, 'tetap' => 0, 'lainnya' => 0, 'detail' => []];
        try {
            $potongan = $this->hitungPotongan($guru, $gajiPokok, $rekapAbsen, $rekapMengajar);
        } catch (\Throwable $e) {
            $errors[] = "Potongan: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] potongan error: " . $e->getMessage());
        }

        // ── 7b. Penyesuaian Liburan (manual) ──────────────────────────────────
        // Potongan liburan diinput MANUAL oleh admin per guru (lihat
        // PenggajianController::penyesuaianLiburan). Saat generate ulang, nilai
        // manual ini DIPERTAHANKAN agar tidak hilang. Default 0 jika belum diisi.
        $existing = Penggajian::where('tenaga_pendidik_id', $guru->id)
            ->where('periode_penggajian_id', $periode->id)
            ->first(['potongan_liburan', 'keterangan_liburan']);
        $potonganLiburan   = (float) ($existing->potongan_liburan ?? 0);
        $keteranganLiburan = $existing->keterangan_liburan ?? null;

        // ── 7c. Punishment Kinerja (potongan) ─────────────────────────────────
        // Admin dapat menjatuhkan potongan via fitur Punishment Kinerja; nilainya
        // ikut memotong gaji pada periode bulan/tahun yang sama.
        $punishmentPotongan = 0;
        try {
            $punishmentPotongan = (float) \App\Models\PunishmentKinerja::where('tenaga_pendidik_id', $guru->id)
                ->where('bulan', $periode->bulan)->where('tahun', $periode->tahun)
                ->where('jenis', 'potongan')->sum('nominal');
        } catch (\Throwable $e) {
            $errors[] = "Punishment kinerja: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] punishment error: " . $e->getMessage());
        }
        $potonganLainnyaTotal = $potongan['lainnya'] + $punishmentPotongan;

        // ── 7c-2. Potongan gaji per-guru (MURNI, terpisah absensi/mengajar) ───
        // Nominal tetap per guru (voucher/simpanan/lazismu/pinjaman). Dijumlah &
        // ditampilkan di slip per item (bila tampil_di_slip).
        $potonganManualGuru = 0;
        $detailPotonganGuru = [];
        try {
            $rows = \App\Models\PotonganGuru::where('tenaga_pendidik_id', $guru->id)
                ->where('is_aktif', true)
                ->whereHas('jenis', fn ($q) => $q->where('is_aktif', true))
                ->with('jenis')->get();
            foreach ($rows as $r) {
                $n = (float) $r->nominal;
                if ($n <= 0) continue;
                $potonganManualGuru += $n;
                if ($r->jenis?->tampil_di_slip) {
                    $detailPotonganGuru[] = [
                        'tipe'             => 'potongan_guru',
                        'keterangan'       => $r->jenis->nama,
                        'jumlah_satuan'    => 1,
                        'satuan'           => 'bulan',
                        'nilai_per_satuan' => $n,
                        'subtotal'         => -$n,
                        'referensi_ids'    => [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            $errors[] = "Potongan per-guru: " . $e->getMessage();
            Log::warning("Payroll [{$guru->id}] potongan per-guru error: " . $e->getMessage());
        }

        // ── 7d. Totalisasi ────────────────────────────────────────────────────
        $totalPendapatan = $gajiPokok
            + $vakasiAbsen['total']
            + $vakasiMengajar['total']
            + $vakasiTugasJabatan['total']
            + $vakasiTugasTambahan['total']
            + $vakasiPesertaKegiatan['total'] // FIX Bug 1: peserta kegiatan masuk ke total
            + $vakasiLembur['total']
            + $vakasiPiket['total']
            + $vakasiEkskul['total'];

        $totalPotongan = $potongan['keterlambatan']
            + $potongan['alfa']
            + $potongan['tetap']
            + $potonganLainnyaTotal      // potongan lain + punishment
            + $potonganLiburan           // penyesuaian liburan manual
            + $potonganManualGuru;       // potongan gaji per-guru (murni)

        $gajiBersih = max(0, $totalPendapatan - $totalPotongan);

        // ── 8. Build detail breakdown ─────────────────────────────────────────
        $detailGajiPokok = $detailGajiPokokList->map(fn($d) => [
            'tipe'             => 'gaji_pokok',
            'keterangan'       => "Gaji pokok: {$d['nama_jabatan']}",
            'jumlah_satuan'    => 1,
            'satuan'           => 'bulan',
            'nilai_per_satuan' => $d['nominal'],
            'subtotal'         => $d['nominal'],
            'referensi_ids'    => [$d['jabatan_id']],
        ])->toArray();

        $hasil = [
            'tenaga_pendidik_id'    => $guru->id,
            'periode_penggajian_id' => $periode->id,
            'jabatan_id'            => $guru->jabatan_id,

            // Komponen pendapatan
            'gaji_pokok'                => $gajiPokok,
            'vakasi_absen_harian'       => $vakasiAbsen['total'],
            'vakasi_mengajar'           => $vakasiMengajar['total'],
            'vakasi_tugas_jabatan'      => $vakasiTugasJabatan['total'],
            'vakasi_tugas_tambahan'     => $vakasiTugasTambahan['total'],
            'vakasi_peserta_kegiatan'   => $vakasiPesertaKegiatan['total'], // FIX Bug 1: kolom baru
            'vakasi_lembur'             => $vakasiLembur['total'],
            'vakasi_piket'              => $vakasiPiket['total'],
            'tunjangan_lainnya'         => 0,

            // Potongan
            'potongan_keterlambatan' => $potongan['keterlambatan'],
            'potongan_alfa'          => $potongan['alfa'],
            'potongan_tetap'         => $potongan['tetap'],
            'potongan_lainnya'       => $potonganLainnyaTotal, // potongan lain + punishment kinerja
            'potongan_liburan'       => $potonganLiburan,      // dipertahankan dari input manual
            'keterangan_liburan'     => $keteranganLiburan,

            // Total
            'total_pendapatan' => $totalPendapatan,
            'total_potongan'   => $totalPotongan,
            'gaji_bersih'      => $gajiBersih,

            // Statistik absensi
            'total_hari_kerja'  => $rekapAbsen['total_hari_kerja'],
            'total_hadir'       => $rekapAbsen['hadir'],
            'total_izin'        => $rekapAbsen['izin'],
            'total_sakit'       => $rekapAbsen['sakit'],
            'total_alfa'        => $rekapAbsen['alfa'],
            'total_terlambat'   => $rekapAbsen['terlambat'],
            'total_jp_mengajar' => $rekapMengajar['total_jp'],

            // Detail untuk audit trail & slip gaji
            '_detail' => [
                ...$detailGajiPokok,
                ...$vakasiAbsen['detail'],
                ...$vakasiMengajar['detail'],
                ...$vakasiTugasJabatan['detail'],
                ...$vakasiTugasTambahan['detail'],
                ...$vakasiPesertaKegiatan['detail'], // FIX Bug 1: detail peserta kegiatan
                ...$vakasiLembur['detail'],
                ...$vakasiPiket['detail'],
                ...$vakasiEkskul['detail'],
                ...$potongan['detail'],
                ...$detailPotonganGuru,   // potongan gaji per-guru (murni)
                // Detail penyesuaian liburan (jika ada) — transparan di slip
                ...($potonganLiburan > 0 ? [[
                    'tipe'             => 'penyesuaian_liburan',
                    'keterangan'       => $keteranganLiburan ?: 'Penyesuaian liburan',
                    'jumlah_satuan'    => 1,
                    'satuan'           => 'periode',
                    'nilai_per_satuan' => $potonganLiburan,
                    'subtotal'         => -$potonganLiburan,
                    'referensi_ids'    => [],
                ]] : []),
                // Detail punishment kinerja (potongan) — transparan di slip
                ...($punishmentPotongan > 0 ? [[
                    'tipe'             => 'potongan_lain',
                    'keterangan'       => 'Potongan kinerja (punishment)',
                    'jumlah_satuan'    => 1,
                    'satuan'           => 'periode',
                    'nilai_per_satuan' => $punishmentPotongan,
                    'subtotal'         => -$punishmentPotongan,
                    'referensi_ids'    => [],
                ]] : []),
            ],

            // Error komponen (untuk log dan UI)
            '_errors' => $errors,
        ];

        if (!$dryRun) {
            $this->simpanKeDatabase($hasil);
        }

        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function simpanKeDatabase(array $hasil): void
    {
        DB::transaction(function () use ($hasil) {
            $errors = $hasil['_errors'] ?? [];
            $detail = $hasil['_detail'] ?? [];

            // Bersihkan field internal sebelum save
            $data = collect($hasil)
                ->except(['_detail', '_errors'])
                ->toArray();

            // Lock row agar tidak bisa double-generate di concurrent request
            $penggajian = Penggajian::where('tenaga_pendidik_id', $data['tenaga_pendidik_id'])
                ->where('periode_penggajian_id', $data['periode_penggajian_id'])
                ->lockForUpdate()
                ->first();

            $payload = array_merge($data, [
                'status'  => 'draft',
                'catatan' => !empty($errors)
                    ? 'Peringatan komponen: ' . implode('; ', $errors)
                    : null,
            ]);

            if ($penggajian) {
                $penggajian->update($payload);
            } else {
                $penggajian = Penggajian::create($payload);
            }

            // FIX: gunakan nama relasi yang benar = detailPenggajian()
            $penggajian->detailPenggajian()->delete();

            foreach ($detail as $d) {
                $penggajian->detailPenggajian()->create([
                    // Normalisasi tipe agar selalu cocok dengan ENUM kolom (cegah truncation).
                    'tipe'             => $this->normalizeTipe($d['tipe'] ?? 'lainnya'),
                    'keterangan'       => $d['keterangan']       ?? '',
                    'jumlah_satuan'    => $d['jumlah_satuan']    ?? 1,
                    'satuan'           => $d['satuan']           ?? '',
                    'nilai_per_satuan' => $d['nilai_per_satuan'] ?? 0,
                    'subtotal'         => $d['subtotal']         ?? 0,
                    'referensi_ids'    => $d['referensi_ids']    ?? [],
                ]);
            }

            // Tandai hari piket sebagai "dibayar" oleh periode ini (period-aware,
            // dalam transaksi → roll back bila gagal; tak terjadi saat dryRun).
            $piketIds = collect($detail)->where('tipe', 'vakasi_piket')
                ->pluck('referensi_ids')->flatten()->filter()->unique()->values();
            if ($piketIds->isNotEmpty()) {
                \App\Models\PiketJadwal::whereIn('id', $piketIds)->update([
                    'vakasi_dibayar'     => true,
                    'dibayar_periode_id' => $data['periode_penggajian_id'],
                ]);
            }
        });
    }

    /**
     * Vakasi Ekstrakurikuler — flat per PERTEMUAN yang absensinya diisi & diselesaikan
     * oleh pembina dalam periode. Nominal = snapshot pertemuan (override ekskul / SettingVakasi).
     */
    private function hitungVakasiEkstrakurikuler(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        $pertemuan = \App\Models\EkstrakurikulerPertemuan::with('ekstrakurikuler:id,nama')
            ->where('pembina_id', $guru->id)
            ->where('status', 'selesai')->where('vakasi_diberikan', true)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->orderBy('tanggal')->get();

        $total = 0; $details = [];
        foreach ($pertemuan as $p) {
            $nominal = (float) $p->nominal_vakasi;
            $total += $nominal;
            $details[] = [
                'tipe'             => 'vakasi_ekstrakurikuler',
                'keterangan'       => 'Vakasi Ekskul ' . ($p->ekstrakurikuler?->nama ?? '—') . ' — ' . $p->tanggal->toDateString(),
                'jumlah_satuan'    => 1,
                'satuan'           => 'pertemuan',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $nominal,
                'referensi_ids'    => [$p->id],
            ];
        }
        return ['total' => $total, 'detail' => $details];
    }

    // ─── Rekap Absensi Harian ─────────────────────────────────────────────────

    private function hitungRekapAbsensiHarian(
        TenagaPendidik $guru,
        Carbon $mulai,
        Carbon $selesai
    ): array {
        $absensi = AbsensiHarian::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get();

        // Hari kerja = kalender − HariLibur (nasional/pesantren/darurat) − libur
        // mingguan − libur individu guru mukim. Libur individu hanya MENGURANGI hari
        // kerja (tidak menambah potongan) → tidak memotong gaji.
        $liburSet = HariLibur::tanggalSetDalamRentang($mulai->toDateString(), $selesai->toDateString());
        $liburIndividuSet = \App\Models\LiburTendik::tanggalSetUntuk($guru->id, $mulai->toDateString(), $selesai->toDateString());
        $jamKerja = $guru->jamKerjaAktif();
        $totalHariKerja = 0;
        $c   = $mulai->copy()->startOfDay();
        $end = $selesai->copy()->startOfDay();
        while ($c->lte($end)) {
            $tglStr = $c->toDateString();
            $liburMingguan = $jamKerja ? $jamKerja->isHariLibur(TimezoneHelper::namaHariDB($c)) : false;
            if (!isset($liburSet[$tglStr]) && !$liburMingguan && !isset($liburIndividuSet[$tglStr])) $totalHariKerja++;
            $c->addDay();
        }

        // dinas_luar = bertugas resmi di luar → dihitung HADIR (dapat vakasi harian),
        // bukan izin. Guru sedang bekerja, hanya lokasinya berbeda.
        return [
            'total_hari_kerja' => $totalHariKerja,
            'hadir'            => $absensi->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])->count(),
            'terlambat'        => $absensi->where('status', 'terlambat')->count(),
            'dinas_luar'       => $absensi->where('status', 'dinas_luar')->count(),
            'izin'             => $absensi->whereIn('status', ['izin', 'izin_sakit'])->count(),
            'sakit'            => $absensi->where('status', 'sakit')->count(),
            'alfa'             => $absensi->where('status', 'alfa')->count(),
            'menit_terlambat'  => $absensi->sum('menit_terlambat') ?? 0,
        ];
    }

    private function defaultRekapAbsen(): array
    {
        return [
            'total_hari_kerja' => 0, 'hadir' => 0,
            'terlambat' => 0, 'dinas_luar' => 0, 'izin' => 0, 'sakit' => 0,
            'alfa' => 0, 'menit_terlambat' => 0,
        ];
    }

    // ─── Vakasi Absensi Harian ────────────────────────────────────────────────

    private function hitungVakasiAbsenHarian(TenagaPendidik $guru, array $rekapAbsen): array
    {
        if ($rekapAbsen['hadir'] === 0) {
            return ['total' => 0, 'detail' => []];
        }

        // Cek per individu dulu, lalu per jabatan, lalu semua
        $vakasiSetting = $this->getVakasiUntukGuru('absen_harian', $guru);

        if (!$vakasiSetting) {
            return ['total' => 0, 'detail' => []];
        }

        $subtotal = $rekapAbsen['hadir'] * $vakasiSetting->nominal;

        return [
            'total'  => $subtotal,
            'detail' => [[
                'tipe'             => 'vakasi_absen',
                'keterangan'       => "Hadir {$rekapAbsen['hadir']} hari × " . $this->rupiah($vakasiSetting->nominal),
                'jumlah_satuan'    => $rekapAbsen['hadir'],
                'satuan'           => 'hari',
                'nilai_per_satuan' => $vakasiSetting->nominal,
                'subtotal'         => $subtotal,
                'referensi_ids'    => [],
            ]],
        ];
    }

    // ─── Vakasi Piket ───────────────────────────────────────────────────────────

    /**
     * Vakasi guru piket: flat per hari penugasan yang laporan hariannya sudah diisi.
     * Nominal dari SettingVakasi tipe 'piket' (aktif). Bukan tugas tambahan.
     *
     * Period-aware (anti-dobel): hanya hitung hari yang BELUM dibayar periode lain
     * (dibayar_periode_id NULL) ATAU memang milik periode ini (regenerate aman).
     * Penandaan "dibayar" TIDAK dilakukan di sini — agar dryRun/preview tak
     * memakan hari; penandaan dilakukan saat simpanKeDatabase() (lihat sana).
     */
    private function hitungVakasiPiket(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai, ?int $periodeId = null): array
    {
        $nominal = (float) (\App\Models\SettingVakasi::where('tipe_aktivitas', 'piket')
            ->where('is_aktif', true)->value('nominal') ?? 0);

        $jadwal = \App\Models\PiketJadwal::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->copy()->startOfDay(), $selesai->copy()->endOfDay()])
            ->whereNotNull('catatan_harian')->where('catatan_harian', '!=', '')
            ->where(fn($q) => $q->whereNull('dibayar_periode_id')
                ->orWhere('dibayar_periode_id', $periodeId))
            ->orderBy('tanggal')->get();

        $total = 0; $details = [];
        foreach ($jadwal as $j) {
            $total += $nominal;
            $details[] = [
                'tipe'             => 'vakasi_piket',
                'keterangan'       => 'Vakasi Piket ' . $j->tanggal->toDateString()
                    . ($nominal <= 0 ? ' (nominal belum diset)' : ''),
                'jumlah_satuan'    => 1,
                'satuan'           => 'hari',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $nominal,
                'referensi_ids'    => [$j->id],
            ];
        }

        return ['total' => $total, 'detail' => $details];
    }

    // ─── Rekap Mengajar ───────────────────────────────────────────────────────

    private function hitungRekapMengajar(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        // Status yang BERHAK atas vakasi JP (jp_terlaksana sudah diisi penuh di backend):
        //   - terlaksana / hadir : guru benar-benar mengajar
        //   - libur              : hari libur, JP tetap dibayar (kebijakan pesantren)
        //   - izin               : guru izin resmi + konfirmasi tugas, JP tetap dibayar
        // Status 'tidak_terlaksana' DIKECUALIKAN — jp_terlaksana = 0, tidak dibayar.
        $absensi = AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->whereIn('status', ['hadir', 'terlaksana', 'libur', 'izin'])
            ->get();

        // Breakdown JP per kategori (untuk transparansi di slip)
        $jpAktual = (int) $absensi
            ->whereIn('status', ['hadir', 'terlaksana'])
            ->sum('jp_terlaksana');
        $jpLibur  = (int) $absensi->where('status', 'libur')->sum('jp_terlaksana');
        $jpIzin   = (int) $absensi->where('status', 'izin')->sum('jp_terlaksana');

        // JP MENGAJAR PENGGANTI: sesi guru lain yang digantikan oleh guru ini.
        // Dibayar ke pengganti (digantikan_oleh) setelah ia absen (jp_terlaksana > 0).
        // Guru asli tidak dibayar karena baris ber-status 'pengganti' (di luar query atas).
        $pengganti = AbsensiMengajar::where('digantikan_oleh', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->where('status', 'pengganti')
            ->where('jp_terlaksana', '>', 0)
            ->get();
        $jpPengganti = (int) $pengganti->sum('jp_terlaksana');

        // SESI TIDAK MENGAJAR (untuk POTONGAN, flat per sesi):
        // baris di mana guru ini adalah pengajar asli tapi TIDAK mengajar —
        //   - status 'pengganti'       : sesinya diampu guru pengganti (guru asli dipotong)
        //   - status 'tidak_terlaksana': sesi tidak berjalan & tanpa pengganti
        // 'libur' & 'izin' (cuti sah) TIDAK dipotong.
        $sesiDipotong = (int) AbsensiMengajar::where('tenaga_pendidik_id', $guru->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->whereIn('status', ['pengganti', 'tidak_terlaksana'])
            ->count();

        return [
            // total_jp yang DIBAYAR vakasi = HANYA JP mengajar pengganti.
            // Mengajar jadwal sendiri tidak lagi dibayar vakasi (sudah masuk gaji pokok).
            'total_jp'      => $jpPengganti,
            'jp_aktual'     => $jpAktual,   // dipertahankan untuk transparansi/laporan
            'jp_libur'      => $jpLibur,
            'jp_izin'       => $jpIzin,
            'jp_pengganti'  => $jpPengganti,
            'sesi_dipotong' => $sesiDipotong,
            'ids'           => $pengganti->pluck('id')->toArray(),
        ];
    }

    // ─── Vakasi Mengajar ──────────────────────────────────────────────────────

    /**
     * Vakasi mengajar — KEBIJAKAN BARU:
     *   - Mengajar jadwal sendiri TIDAK dibayar vakasi (sudah termasuk gaji pokok).
     *   - HANYA guru PENGGANTI yang dibayar (per JP yang benar-benar diampu),
     *     memakai tarif SettingVakasi 'absen_mengajar'.
     * Potongan bagi guru yang digantikan ditangani terpisah di hitungPotongan().
     */
    private function hitungVakasiMengajar(TenagaPendidik $guru, array $rekapMengajar): array
    {
        $jpPengganti = (int) ($rekapMengajar['jp_pengganti'] ?? 0);
        if ($jpPengganti <= 0) {
            return ['total' => 0, 'detail' => []];
        }

        $vakasiSetting = $this->getVakasiUntukGuru('absen_mengajar', $guru);
        if (!$vakasiSetting) {
            return ['total' => 0, 'detail' => []];
        }

        $nominal  = $vakasiSetting->nominal;
        $subtotal = $jpPengganti * $nominal;

        return [
            'total'  => $subtotal,
            'detail' => [[
                'tipe'             => 'vakasi_mengajar',
                'keterangan'       => "Mengajar pengganti {$jpPengganti} JP × " . $this->rupiah($nominal),
                'jumlah_satuan'    => $jpPengganti,
                'satuan'           => 'JP',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $subtotal,
                'referensi_ids'    => $rekapMengajar['ids'] ?? [],
            ]],
        ];
    }

    // ─── Vakasi Peserta Kegiatan ──────────────────────────────────────────────
    // Guru yang diabsen di kegiatan mendapat vakasi yang sama dengan pengabsen

    private function hitungVakasiPesertaKegiatan(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        $peserta = AbsensiKegiatanPeserta::with('kegiatan')
            ->where('tenaga_pendidik_id', $guru->id)
            ->where('vakasi_diberikan', true)
            ->where('nominal_vakasi', '>', 0)
            ->whereHas('kegiatan', fn($q) =>
                $q->whereDate('tanggal_kegiatan', '>=', $mulai->toDateString())
                  ->whereDate('tanggal_kegiatan', '<=', $selesai->toDateString())
                  ->where('status', 'selesai')
            )
            ->get();

        if ($peserta->isEmpty()) return ['total' => 0, 'detail' => []];

        $total   = 0;
        $details = [];

        foreach ($peserta as $p) {
            $nominal = (float) $p->nominal_vakasi;
            $total  += $nominal;
            $details[] = [
                'tipe'             => 'vakasi_peserta_kegiatan',
                'keterangan'       => 'Hadir kegiatan: ' . ($p->kegiatan?->nama_kegiatan ?? '—'),
                'jumlah_satuan'    => 1,
                'satuan'           => 'kegiatan',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $nominal,
                'referensi_ids'    => [$p->id],
            ];
        }

        return ['total' => $total, 'detail' => $details];
    }

    // ─── Vakasi Lembur ────────────────────────────────────────────────────────
    // Hanya peserta lembur berstatus 'sah' (otomatis upload GPS / manual admin)
    // pada periode. Flat per event dari snapshot nominal_vakasi.

    private function hitungVakasiLembur(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        $peserta = LemburPeserta::with('lembur')
            ->where('tenaga_pendidik_id', $guru->id)
            ->where('status', 'sah')
            ->whereHas('lembur', fn($q) =>
                $q->whereDate('tanggal', '>=', $mulai->toDateString())
                  ->whereDate('tanggal', '<=', $selesai->toDateString())
                  ->whereNotIn('status', ['ditolak', 'dibatalkan'])
            )
            ->get();

        if ($peserta->isEmpty()) return ['total' => 0, 'detail' => []];

        $total   = 0;
        $details = [];

        foreach ($peserta as $p) {
            $nominal = (float) ($p->nominal_vakasi ?? 0);
            $total  += $nominal;

            $tgl    = $p->lembur?->tanggal?->format('d/m/Y') ?? '—';
            $judul  = $p->lembur?->judul ?? 'Lembur';
            $manual = $p->metode_pengesahan === 'manual_admin' ? ' (disahkan admin)' : '';

            $details[] = [
                'tipe'             => 'vakasi_lembur',
                'keterangan'       => "Lembur {$tgl}: {$judul}{$manual}",
                'jumlah_satuan'    => 1,
                'satuan'           => 'lembur',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $nominal,
                'referensi_ids'    => [$p->id],
            ];
        }

        return ['total' => $total, 'detail' => $details];
    }

    // ─── Vakasi Tugas Tambahan ────────────────────────────────────────────────

    private function hitungVakasiTugasTambahan(TenagaPendidik $guru, Carbon $mulai, Carbon $selesai): array
    {
        // FIX Bug 3: filter berdasarkan dilaporkan_pada (tanggal selesai dikerjakan),
        // BUKAN tanggal range tugas. Ini mencegah double-counting saat tugas merentang
        // lebih dari satu periode penggajian.
        // BUGFIX: gunakan copy() agar tidak memutasi Carbon object $mulai/$selesai yang dipakai
        // bersama oleh metode-metode hitung lainnya (hitungVakasiPesertaKegiatan, dll.).
        // Semua penugasan guru ini (kegiatan/mandiri dihitung berbeda).
        $penugasan = PenugasanTambahan::with(['tugasTambahan.settingVakasi', 'settingVakasi'])
            ->where('tenaga_pendidik_id', $guru->id)
            ->get();

        $total   = 0;
        $details = [];

        foreach ($penugasan as $p) {
            // Ditolak admin → tidak dibayar.
            if ($p->disetujui === false) {
                continue;
            }

            $tipe = $p->tugasTambahan?->tipe_pengerjaan ?? 'mandiri';
            $judul = $p->tugasTambahan->judul ?? 'Tugas tambahan';

            // Prioritas nominal: override per penerima → setting per penerima → override tugas → setting tugas
            $nominal = (float) (
                $p->vakasi_override
                ?? $p->settingVakasi?->nominal
                ?? $p->tugasTambahan?->vakasi_override
                ?? $p->tugasTambahan?->settingVakasi?->nominal
                ?? 0
            );

            if ($tipe === 'absen_kegiatan') {
                // Vakasi = JUMLAH kegiatan yang guru ini SELESAIKAN dalam periode × nominal.
                // (satu tugas rentang boleh punya banyak kegiatan; tiap kegiatan = 1 vakasi)
                $kegiatanIds = AbsensiKegiatan::where('penugasan_id', $p->id)
                    ->where('pengabsen_id', $guru->id)
                    ->where('status', 'selesai')
                    ->whereDate('tanggal_kegiatan', '>=', $mulai->toDateString())
                    ->whereDate('tanggal_kegiatan', '<=', $selesai->toDateString())
                    ->pluck('id')->all();
                $jumlah = count($kegiatanIds);

                if ($jumlah === 0) {
                    continue; // belum ada kegiatan selesai pada periode ini
                }

                $subtotal = $jumlah * $nominal;
                $total   += $subtotal;
                $details[] = [
                    'tipe'             => 'vakasi_tugas_tambahan',
                    'keterangan'       => "Tugas Absen Kegiatan: {$judul} ({$jumlah} kegiatan)"
                        . ($nominal <= 0 ? ' (tanpa vakasi)' : ''),
                    'jumlah_satuan'    => $jumlah,
                    'satuan'           => 'kegiatan',
                    'nilai_per_satuan' => $nominal,
                    'subtotal'         => $subtotal,
                    'referensi_ids'    => $kegiatanIds,
                ];
                continue;
            }

            // MANDIRI: flat 1× saat penugasan disetujui & selesai, difilter dilaporkan_pada dalam periode
            // (agar tak double-count bila tugas merentang beberapa periode gaji).
            if ($p->disetujui !== true || $p->status_pengerjaan !== 'selesai' || $p->dilaporkan_pada === null) {
                continue;
            }
            $dl = \Carbon\Carbon::parse($p->dilaporkan_pada);
            if ($dl->lt($mulai->copy()->startOfDay()) || $dl->gt($selesai->copy()->endOfDay())) {
                continue;
            }

            $total += $nominal;
            $details[] = [
                'tipe'             => 'vakasi_tugas_tambahan',
                'keterangan'       => "Tugas Mandiri: {$judul}"
                    . ($nominal <= 0 ? ' (tanpa vakasi)' : ''),
                'jumlah_satuan'    => 1,
                'satuan'           => 'tugas',
                'nilai_per_satuan' => $nominal,
                'subtotal'         => $nominal,
                'referensi_ids'    => [$p->id],
            ];
        }

        return ['total' => $total, 'detail' => $details];
    }

    // ─── Potongan ─────────────────────────────────────────────────────────────

    private function hitungPotongan(TenagaPendidik $guru, float $gajiPokok, array $rekapAbsen, array $rekapMengajar = []): array
    {
        $potongKeterlambatan = 0;
        $potongAlfa          = 0;
        $potongTetap         = 0;
        $potongLainnya       = 0;
        $details             = [];

        try {
            // Hitung SEMUA potongan aktif (tidak difilter tampil_di_slip).
            // Setiap potongan yang mengurangi gaji WAJIB tercatat di slip demi
            // transparansi — tampil_di_slip tidak boleh menyembunyikan potongan
            // dari perhitungan.
            $settings = SettingPotongan::aktif()->get();

            foreach ($settings as $s) {
                // Cek apakah berlaku untuk guru ini
                if (!$s->berlakuUntukGuru($guru)) continue;

                switch ($s->tipe_pemicu) {

                    // ── Potongan Keterlambatan (per kejadian) ─────────────
                    case 'per_keterlambatan':
                        if ($rekapAbsen['terlambat'] <= 0) break;
                        $nominal = $s->hitungNominal($gajiPokok, $rekapAbsen['terlambat']);
                        if ($nominal <= 0) break;
                        $potongKeterlambatan += $nominal;
                        $details[] = [
                            'tipe'             => 'potongan_keterlambatan',
                            'keterangan'       => "{$s->nama} — terlambat {$rekapAbsen['terlambat']}×",
                            'jumlah_satuan'    => $rekapAbsen['terlambat'],
                            'satuan'           => 'kali',
                            'nilai_per_satuan' => $s->hitungNominal($gajiPokok, 1),
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Keterlambatan (per menit) ────────────────
                    case 'per_menit_keterlambatan':
                        $totalMenit = $rekapAbsen['menit_terlambat'] ?? 0;
                        if ($totalMenit <= 0) break;
                        // hitungNominal($gajiPokok, $totalMenit) → nominal/menit × total menit
                        $nominal = $s->hitungNominal($gajiPokok, (int) $totalMenit);
                        if ($nominal <= 0) break;
                        $potongKeterlambatan += $nominal;
                        $details[] = [
                            'tipe'             => 'potongan_keterlambatan',
                            'keterangan'       => "{$s->nama} — {$totalMenit} menit terlambat × " . $this->rupiah($s->hitungNominal($gajiPokok, 1)),
                            'jumlah_satuan'    => $totalMenit,
                            'satuan'           => 'menit',
                            'nilai_per_satuan' => $s->hitungNominal($gajiPokok, 1),
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Alfa ─────────────────────────────────────
                    case 'per_alfa':
                        if ($rekapAbsen['alfa'] <= 0) break;
                        $nominal = $s->hitungNominal($gajiPokok, $rekapAbsen['alfa']);
                        if ($nominal <= 0) break;
                        $potongAlfa += $nominal;
                        $details[] = [
                            'tipe'             => 'potongan_alfa',
                            'keterangan'       => "{$s->nama} — alfa {$rekapAbsen['alfa']} hari",
                            'jumlah_satuan'    => $rekapAbsen['alfa'],
                            'satuan'           => 'hari',
                            'nilai_per_satuan' => $s->hitungNominal($gajiPokok, 1),
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Tidak Mengajar (flat × sesi digantikan / tidak terlaksana) ──
                    case 'per_sesi_tidak_mengajar':
                        $sesi = (int) ($rekapMengajar['sesi_dipotong'] ?? 0);
                        if ($sesi <= 0) break;
                        $nominal = $s->hitungNominal($gajiPokok, $sesi);
                        if ($nominal <= 0) break;
                        $potongLainnya += $nominal;
                        $details[] = [
                            'tipe'             => 'potongan_tidak_mengajar',
                            'keterangan'       => "{$s->nama} — {$sesi} sesi tidak mengajar",
                            'jumlah_satuan'    => $sesi,
                            'satuan'           => 'sesi',
                            'nilai_per_satuan' => $s->hitungNominal($gajiPokok, 1),
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Flat Per Bulan (wajib/simpanan/pinjaman) ─
                    case 'per_bulan':
                        $nominal = $s->hitungNominal($gajiPokok);
                        if ($nominal <= 0) break;

                        // Kelompokkan ke bucket yang tepat
                        if ($s->kategori === 'absensi') {
                            $potongKeterlambatan += $nominal;
                        } elseif (in_array($s->kategori, ['wajib', 'simpanan', 'pinjaman'])) {
                            $potongTetap += $nominal;
                        } else {
                            $potongLainnya += $nominal;
                        }

                        $details[] = [
                            'tipe'             => 'potongan_' . $s->kategori,
                            'keterangan'       => $s->nama,
                            'jumlah_satuan'    => 1,
                            'satuan'           => 'bulan',
                            'nilai_per_satuan' => $nominal,
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Persen Gaji ──────────────────────────────
                    case 'persen_gaji':
                        $nominal = $s->hitungNominal($gajiPokok);
                        if ($nominal <= 0) break;

                        if (in_array($s->kategori, ['wajib', 'simpanan', 'pinjaman'])) {
                            $potongTetap += $nominal;
                        } else {
                            $potongLainnya += $nominal;
                        }

                        $details[] = [
                            'tipe'             => 'potongan_' . $s->kategori,
                            'keterangan'       => "{$s->nama} ({$s->nominal}% dari gaji pokok)",
                            'jumlah_satuan'    => 1,
                            'satuan'           => '%',
                            'nilai_per_satuan' => $s->nominal,
                            'subtotal'         => -$nominal,
                            'referensi_ids'    => [$s->id],
                        ];
                        break;

                    // ── Potongan Manual — skip saat auto-generate ─────────
                    case 'manual':
                        // Tidak dihitung otomatis — harus di-input per guru via override
                        break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("hitungPotongan error [{$guru->id}]: " . $e->getMessage());
        }

        return [
            'keterlambatan' => $potongKeterlambatan,
            'alfa'          => $potongAlfa,
            'tetap'         => $potongTetap,
            'lainnya'       => $potongLainnya,
            'detail'        => $details,
        ];
    }

    // ─── Helper: Get Vakasi Fleksibel (individu → jabatan → semua) ───────────

    /**
     * Ambil setting vakasi yang berlaku untuk guru ini.
     * Cek urutan: per individu → per jabatan → semua.
     */
    /**
     * Tarif (nominal) per JP mengajar yang berlaku untuk seorang guru.
     * Mengembalikan 0 bila tidak ada SettingVakasi 'absen_mengajar' yang cocok.
     * Dipakai oleh laporan absensi mengajar agar tarif selaras dgn penggajian.
     */
    public function tarifPerJpMengajar(TenagaPendidik $guru): float
    {
        $setting = $this->getVakasiUntukGuru('absen_mengajar', $guru);
        return $setting ? (float) $setting->nominal : 0.0;
    }

    private function getVakasiUntukGuru(string $tipeAktivitas, TenagaPendidik $guru): ?SettingVakasi
    {
        $jabatanIds = $guru->jabatanGuru()
            ->whereNull('berlaku_selesai')
            ->pluck('jabatan_id')
            ->toArray();

        // Fallback ke jabatan_id lama
        if (empty($jabatanIds) && $guru->jabatan_id) {
            $jabatanIds = [$guru->jabatan_id];
        }

        return SettingVakasi::aktif()
            ->where('tipe_aktivitas', $tipeAktivitas)
            ->where('berlaku_mulai', '<=', now())
            ->where(fn($q) => $q->whereNull('berlaku_selesai')->orWhere('berlaku_selesai', '>=', now()))
            ->where(fn($q) =>
                $q->where('berlaku_untuk_semua', true)
                  ->orWhereIn('lingkup', ['semua'])
                  ->orWhere(fn($q2) =>
                      $q2->whereJsonContains('tenaga_pendidik_ids', $guru->id)
                  )
                  ->orWhere(fn($q3) => collect($jabatanIds)->reduce(
                      fn($carry, $jid) => $carry->orWhereJsonContains('jabatan_ids', $jid),
                      $q3
                  ))
            )
            ->orderByRaw("
                CASE
                    WHEN JSON_CONTAINS(tenaga_pendidik_ids, ?) THEN 1
                    WHEN lingkup = 'per_jabatan' THEN 2
                    ELSE 3
                END
            ", [json_encode($guru->id)])
            ->latest('berlaku_mulai')
            ->first();
    }

    private function rupiah(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /**
     * Normalisasi nilai 'tipe' ke salah satu nilai ENUM yang valid di
     * kolom detail_penggajian.tipe. Mencegah "Data truncated" saat ada
     * potongan dengan kategori bebas (mis. potongan_wajib, potongan_keterlambatan).
     *
     * ENUM valid: gaji_pokok, vakasi_absen, vakasi_mengajar, vakasi_tugas_jabatan,
     * vakasi_tugas_tambahan, vakasi_peserta_kegiatan, tunjangan,
     * potongan_terlambat, potongan_alfa, potongan_bpjs, potongan_lain,
     * penyesuaian_liburan, lainnya.
     */
    private function normalizeTipe(string $tipe): string
    {
        static $valid = [
            'gaji_pokok', 'vakasi_absen', 'vakasi_mengajar',
            'vakasi_tugas_jabatan', 'vakasi_tugas_tambahan', 'vakasi_peserta_kegiatan',
            'vakasi_lembur',
            'tunjangan',
            'potongan_terlambat', 'potongan_alfa', 'potongan_bpjs', 'potongan_lain',
            'penyesuaian_liburan', 'lainnya',
        ];

        if (in_array($tipe, $valid, true)) {
            return $tipe;
        }

        // Pemetaan tipe potongan yang tidak persis sama dengan ENUM
        if (str_contains($tipe, 'terlambat') || str_contains($tipe, 'keterlambatan')) {
            return 'potongan_terlambat';
        }
        if (str_contains($tipe, 'alfa'))  return 'potongan_alfa';
        if (str_contains($tipe, 'bpjs'))  return 'potongan_bpjs';
        if (str_starts_with($tipe, 'potongan')) return 'potongan_lain';

        return 'lainnya';
    }
}