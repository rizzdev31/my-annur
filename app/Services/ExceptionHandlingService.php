<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\HariLibur;
use App\Models\KoreksiAbsensi;
use App\Models\LogAktivitas;
use App\Models\Penggajian;
use App\Models\TenagaPendidik;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * ExceptionHandlingService
 *
 * Semua "kejadian mendadak" yang butuh penanganan cepat di pesantren:
 * - Libur mendadak (mis: tiba-tiba ada pengajian besar)
 * - Koreksi absensi (lupa check-out, salah status)
 * - Override manual gaji (bonus/potongan khusus)
 * - Reset/recalculate penggajian setelah ada koreksi
 */
class ExceptionHandlingService
{
    public function __construct(
        private readonly PayrollCalculationService $payrollService
    ) {}

    // ════════════════════════════════════════════════════════════════════════
    // 1. HARI LIBUR MENDADAK
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Tambah hari libur mendadak dan update semua absensi yang terdampak.
     *
     * Flow:
     * 1. Simpan hari libur baru
     * 2. Cari semua absensi di tanggal itu yang statusnya 'alfa' (karena belum absen)
     * 3. Update status mereka jadi 'libur'
     * 4. Log perubahan
     */
    public function tambahHariLiburMendadak(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $hariLibur = HariLibur::create([
                'nama'          => $data['nama'],
                'tanggal'       => $data['tanggal'],
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'tipe'          => 'darurat',
                'keterangan'    => $data['keterangan'] ?? null,
                'pengaruh_gaji' => $data['pengaruh_gaji'] ?? true,
                'dibuat_oleh'   => Auth::id(),
            ]);

            $tanggalRange = $this->getRangeTanggal(
                $data['tanggal'],
                $data['tanggal_selesai'] ?? $data['tanggal']
            );

            $absensiTerdampak = AbsensiHarian::whereIn('tanggal', $tanggalRange)
                ->whereIn('status', ['alfa', 'hadir', 'terlambat'])
                ->get();

            $jumlahDiupdate = 0;

            foreach ($absensiTerdampak as $absen) {
                $statusLama = $absen->status;
                $absen->update([
                    'status'        => 'libur',
                    'keterangan'    => "Auto: {$hariLibur->nama}",
                    'is_koreksi'    => true,
                    'dikoreksi_oleh' => Auth::id(),
                ]);

                $this->log('libur_mendadak_auto_update', $absen, [
                    'data_lama' => ['status' => $statusLama],
                    'data_baru' => ['status' => 'libur'],
                    'keterangan' => "Libur darurat: {$hariLibur->nama}",
                ]);

                $jumlahDiupdate++;
            }

            // Buat record absensi 'libur' untuk guru yang belum ada record sama sekali
            $this->buatAbsensiLiburOtomatis($tanggalRange, $hariLibur->nama);

            return [
                'hari_libur'          => $hariLibur,
                'absensi_diupdate'    => $jumlahDiupdate,
                'tanggal_terdampak'   => $tanggalRange,
            ];
        });
    }

    /**
     * Hapus/batalkan hari libur darurat dan kembalikan status absensi.
     */
    public function batalkanHariLiburMendadak(HariLibur $hariLibur, string $alasan): array
    {
        if ($hariLibur->tipe !== 'darurat') {
            throw new \InvalidArgumentException('Hanya hari libur darurat yang bisa dibatalkan via fitur ini.');
        }

        return DB::transaction(function () use ($hariLibur, $alasan) {
            $tanggalRange = $this->getRangeTanggal(
                $hariLibur->tanggal,
                $hariLibur->tanggal_selesai ?? $hariLibur->tanggal
            );

            // Kembalikan absensi yang otomatis diubah jadi 'libur' oleh sistem
            $absensiDikembalikan = AbsensiHarian::whereIn('tanggal', $tanggalRange)
                ->where('status', 'libur')
                ->where('is_koreksi', true)
                ->where('dikoreksi_oleh', $hariLibur->dibuat_oleh)
                ->update([
                    'status'     => 'alfa',
                    'keterangan' => "Libur {$hariLibur->nama} dibatalkan. Alasan: {$alasan}",
                ]);

            $hariLibur->delete();

            $this->log('batalkan_libur_darurat', $hariLibur, [
                'keterangan' => $alasan,
            ]);

            return ['absensi_dikembalikan' => $absensiDikembalikan];
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2. KOREKSI ABSENSI
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Koreksi absensi harian — superadmin bisa ubah status, jam, dll.
     */
    public function koreksiAbsensiHarian(
        AbsensiHarian $absensi,
        array $data,
        string $alasan
    ): KoreksiAbsensi {
        return DB::transaction(function () use ($absensi, $data, $alasan) {
            $dataLama = $absensi->toArray();

            // Catat setiap field yang berubah
            $fieldDikoreksi = [];
            foreach ($data as $field => $nilaiB) {
                if (isset($absensi->$field) && (string)$absensi->$field !== (string)$nilaiB) {
                    $fieldDikoreksi[] = $field;
                }
            }

            $absensi->update(array_merge($data, [
                'is_koreksi'    => true,
                'dikoreksi_oleh' => Auth::id(),
            ]));

            $koreksi = KoreksiAbsensi::create([
                'tenaga_pendidik_id'  => $absensi->tenaga_pendidik_id,
                'tanggal'             => $absensi->tanggal,
                'tipe_absensi'        => 'absen_harian',
                'absensi_harian_id'   => $absensi->id,
                'field_dikoreksi'     => implode(', ', $fieldDikoreksi),
                'nilai_lama'          => json_encode($dataLama),
                'nilai_baru'          => json_encode($absensi->fresh()->toArray()),
                'alasan'              => $alasan,
                'status'              => 'disetujui',
                'dikoreksi_oleh'      => Auth::id(),
            ]);

            $this->log('koreksi_absensi_harian', $absensi, [
                'data_lama'  => $dataLama,
                'data_baru'  => $absensi->fresh()->toArray(),
                'keterangan' => $alasan,
            ]);

            return $koreksi;
        });
    }

    /**
     * Buat absensi baru jika guru lupa absen (manual insert oleh superadmin).
     */
    public function insertAbsensiManual(
        TenagaPendidik $guru,
        array $data,
        string $alasan
    ): AbsensiHarian {
        return DB::transaction(function () use ($guru, $data, $alasan) {
            $absensi = AbsensiHarian::updateOrCreate(
                [
                    'tenaga_pendidik_id' => $guru->id,
                    'tanggal'            => $data['tanggal'],
                ],
                array_merge($data, [
                    'is_koreksi'     => true,
                    'dikoreksi_oleh' => Auth::id(),
                    'keterangan'     => "Insert manual: {$alasan}",
                ])
            );

            KoreksiAbsensi::create([
                'tenaga_pendidik_id' => $guru->id,
                'tanggal'            => $data['tanggal'],
                'tipe_absensi'       => 'absen_harian',
                'absensi_harian_id'  => $absensi->id,
                'field_dikoreksi'    => 'insert_manual',
                'nilai_lama'         => null,
                'nilai_baru'         => json_encode($data),
                'alasan'             => $alasan,
                'status'             => 'disetujui',
                'dikoreksi_oleh'     => Auth::id(),
            ]);

            $this->log('insert_absensi_manual', $absensi, ['keterangan' => $alasan]);

            return $absensi;
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. OVERRIDE GAJI MANUAL
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Override komponen gaji secara manual (bonus, tunjangan khusus, potongan khusus).
     * Setelah override, penggajian di-flag 'ada_koreksi_manual'.
     */
    public function overrideKomponenGaji(
        Penggajian $penggajian,
        array $overrides,
        string $alasan
    ): Penggajian {
        if ($penggajian->status === 'dibayar') {
            throw new \InvalidArgumentException('Penggajian yang sudah dibayar tidak bisa diubah.');
        }

        return DB::transaction(function () use ($penggajian, $overrides, $alasan) {
            $dataLama = $penggajian->toArray();

            // Field yang boleh di-override manual
            $allowedFields = [
                'tunjangan_lainnya',
                'potongan_lainnya',
                'gaji_pokok',
            ];

            $updateData = array_intersect_key($overrides, array_flip($allowedFields));
            $updateData['ada_koreksi_manual'] = true;

            // Recalculate total setelah override
            $penggajian->fill($updateData);
            $penggajian->total_pendapatan =
                $penggajian->gaji_pokok
                + $penggajian->vakasi_absen_harian
                + $penggajian->vakasi_mengajar
                + $penggajian->vakasi_tugas_jabatan
                + $penggajian->vakasi_tugas_tambahan
                + $penggajian->tunjangan_lainnya;

            $penggajian->total_potongan =
                $penggajian->potongan_keterlambatan
                + $penggajian->potongan_alfa
                + $penggajian->potongan_tetap
                + $penggajian->potongan_lainnya;

            $penggajian->gaji_bersih =
                $penggajian->total_pendapatan - $penggajian->total_potongan;

            $penggajian->save();

            // Catat detail override
            $penggajian->detailPenggajian()->create([
                'tipe'        => 'tunjangan',
                'keterangan'  => "Override manual: {$alasan}",
                'subtotal'    => $overrides['tunjangan_lainnya'] ?? $overrides['potongan_lainnya'] ?? 0,
                'referensi_ids' => [],
            ]);

            $this->log('override_gaji_manual', $penggajian, [
                'data_lama'  => $dataLama,
                'data_baru'  => $penggajian->fresh()->toArray(),
                'keterangan' => $alasan,
            ]);

            return $penggajian->fresh(['detailPenggajian']);
        });
    }

    /**
     * Recalculate ulang penggajian setelah ada koreksi absensi.
     * Dipanggil ketika koreksi absensi dilakukan setelah generate gaji.
     */
    public function recalculatePenggajian(Penggajian $penggajian): Penggajian
    {
        if ($penggajian->status === 'dibayar') {
            throw new \InvalidArgumentException('Penggajian yang sudah dibayar tidak bisa di-recalculate.');
        }

        $guru    = $penggajian->tenagaPendidik;
        $periode = $penggajian->periodePenggajian;

        // Simpan tunjangan_lainnya & potongan_lainnya dari override manual sebelumnya
        $tunjanganManual  = $penggajian->tunjangan_lainnya;
        $potonganManual   = $penggajian->potongan_lainnya;
        $adaKoreksiManual = $penggajian->ada_koreksi_manual;

        // Hitung ulang dari awal
        $hasilBaru = $this->payrollService->hitung($guru, $periode, dryRun: true);

        // Terapkan override manual sebelumnya (tidak ikut di-reset)
        $hasilBaru['tunjangan_lainnya']   = $tunjanganManual;
        $hasilBaru['potongan_lainnya']    = $potonganManual;
        $hasilBaru['ada_koreksi_manual']  = $adaKoreksiManual;
        $hasilBaru['total_pendapatan']   += $tunjanganManual;
        $hasilBaru['total_potongan']     += $potonganManual;
        $hasilBaru['gaji_bersih']         =
            $hasilBaru['total_pendapatan'] - $hasilBaru['total_potongan'];

        $detail = $hasilBaru['_detail'];
        unset($hasilBaru['_detail']);

        return DB::transaction(function () use ($penggajian, $hasilBaru, $detail) {
            $penggajian->update($hasilBaru);
            $penggajian->detailPenggajian()->delete();

            foreach ($detail as $d) {
                $penggajian->detailPenggajian()->create([
                    'tipe'             => $d['tipe'],
                    'keterangan'       => $d['keterangan'],
                    'jumlah_satuan'    => $d['jumlah_satuan'] ?? null,
                    'satuan'           => $d['satuan'] ?? null,
                    'nilai_per_satuan' => $d['nilai_per_satuan'] ?? null,
                    'subtotal'         => $d['subtotal'],
                    'referensi_ids'    => $d['referensi_ids'] ?? [],
                ]);
            }

            $this->log('recalculate_penggajian', $penggajian, [
                'keterangan' => 'Recalculate setelah koreksi absensi',
            ]);

            return $penggajian->fresh(['detailPenggajian']);
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function getRangeTanggal(string $mulai, string $selesai): array
    {
        $dates  = [];
        $current = Carbon::parse($mulai);
        $end     = Carbon::parse($selesai);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    private function buatAbsensiLiburOtomatis(array $tanggalRange, string $namaLibur): void
    {
        $semuaGuru = TenagaPendidik::aktif()->pluck('id');

        foreach ($tanggalRange as $tanggal) {
            $sudahAdaAbsensi = AbsensiHarian::where('tanggal', $tanggal)
                ->pluck('tenaga_pendidik_id')
                ->toArray();

            $guruTanpaAbsen = $semuaGuru->diff($sudahAdaAbsensi);

            foreach ($guruTanpaAbsen as $guruId) {
                AbsensiHarian::create([
                    'tenaga_pendidik_id' => $guruId,
                    'tanggal'            => $tanggal,
                    'status'             => 'libur',
                    'keterangan'         => "Auto: {$namaLibur}",
                    'is_koreksi'         => true,
                    'dikoreksi_oleh'     => Auth::id(),
                ]);
            }
        }
    }

    private function log(string $aksi, $model, array $extra = []): void
    {
        LogAktivitas::create([
            'user_id'    => Auth::id(),
            'aksi'       => $aksi,
            'model_type' => get_class($model),
            'model_id'   => $model->id,
            'data_lama'  => $extra['data_lama'] ?? null,
            'data_baru'  => $extra['data_baru'] ?? null,
            'keterangan' => $extra['keterangan'] ?? null,
            'ip_address' => request()->ip(),
        ]);
    }
}