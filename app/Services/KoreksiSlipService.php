<?php

namespace App\Services;

use App\Models\Penggajian;
use App\Models\PeriodePenggajian;
use App\Models\TenagaPendidik;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU PINTU untuk dua hal yang gampang saling merusak:
 *
 *  1. Menuliskan koreksi manual ke slip (tombol Override).
 *  2. Men-generate ulang slip TANPA melenyapkan koreksi itu.
 *
 * Kenapa harus satu pintu: `PayrollCalculationService::hitung()` menulis slip
 * dari nol — termasuk `catatan => null` — sedangkan `ada_koreksi_manual` tidak
 * ikut disentuhnya. Pada 31 Okt 2026 satu klik Generate menimpa 50 slip dalam
 * 4 detik dan potongan "Simpan Pinjam Angsuran Ke-2" Rp 250.000 hilang, tapi
 * tandanya tetap 1 sehingga slip masih mengaku berkoreksi padahal uangnya sudah
 * lenyap. Siapa pun yang memanggil hitung() untuk slip yang sudah ada WAJIB
 * lewat sini.
 */
class KoreksiSlipService
{
    public function __construct(
        private readonly PayrollCalculationService $payroll,
    ) {}

    /**
     * Koreksi manual satu periode, berindeks tenaga_pendidik_id.
     * Nominal nol tidak dianggap koreksi: tanda yang tertinggal dari koreksi
     * yang sudah dibatalkan tidak boleh menghidupkan apa pun.
     */
    public function bacaKoreksi(PeriodePenggajian $periode): array
    {
        return Penggajian::where('periode_penggajian_id', $periode->id)
            ->where('ada_koreksi_manual', true)
            ->get(['tenaga_pendidik_id', 'tunjangan_lainnya', 'potongan_lainnya', 'catatan'])
            ->filter(fn ($p) => (float) $p->tunjangan_lainnya != 0.0
                || (float) $p->potongan_lainnya != 0.0)
            ->mapWithKeys(fn ($p) => [$p->tenaga_pendidik_id => [
                'tunjangan' => (float) $p->tunjangan_lainnya,
                'potongan'  => (float) $p->potongan_lainnya,
                'catatan'   => $p->catatan,
            ]])
            ->all();
    }

    /** Koreksi satu slip saja, atau null bila tidak ada/nol. */
    public function bacaKoreksiSlip(?Penggajian $slip): ?array
    {
        if (! $slip || ! $slip->ada_koreksi_manual) return null;

        $tunjangan = (float) $slip->tunjangan_lainnya;
        $potongan  = (float) $slip->potongan_lainnya;
        if ($tunjangan == 0.0 && $potongan == 0.0) return null;

        return ['tunjangan' => $tunjangan, 'potongan' => $potongan, 'catatan' => $slip->catatan];
    }

    /**
     * Tuliskan koreksi manual ke slip, lengkap dengan BARIS rinciannya.
     *
     * Barisnya wajib ada karena slip harus berjumlah pas: sebelum ini potongan
     * override tidak punya baris, jadi slip Faizah memperlihatkan rincian
     * Rp 1.995.000 sementara yang dibayar Rp 1.745.000 — guru tidak punya cara
     * tahu Rp 250.000 itu potongan apa.
     *
     * Semantik lama dipertahankan: nominal override MENGGANTI isi ember
     * tunjangan/potongan lainnya (termasuk punishment kinerja yang dihitung
     * sistem), bukan menambah. Baris punishment karena itu ikut dihapus supaya
     * jumlah baris tetap sama dengan total.
     */
    public function terapkanKoreksi(
        Penggajian $slip,
        float $tunjangan,
        float $potongan,
        ?string $catatan,
    ): void {
        DB::transaction(function () use ($slip, $tunjangan, $potongan, $catatan) {
            $totalPendapatan = (float) $slip->total_pendapatan
                - (float) $slip->tunjangan_lainnya + $tunjangan;
            $totalPotongan = (float) $slip->total_potongan
                - (float) $slip->potongan_lainnya + $potongan;

            $slip->update([
                'tunjangan_lainnya'  => $tunjangan,
                'potongan_lainnya'   => $potongan,
                'total_pendapatan'   => $totalPendapatan,
                'total_potongan'     => $totalPotongan,
                'gaji_bersih'        => max(0, $totalPendapatan - $totalPotongan),
                'catatan'            => $catatan,
                // Nominal nol = koreksinya dibatalkan, tandanya ikut dicabut
                // supaya lencana "koreksi" di layar tidak berbohong.
                'ada_koreksi_manual' => $tunjangan != 0.0 || $potongan != 0.0,
            ]);

            $label = $catatan ?: 'Koreksi manual';

            $slip->detailPenggajian()->whereIn('tipe', ['tunjangan', 'potongan_lain'])->delete();

            if ($tunjangan > 0) {
                $slip->detailPenggajian()->create([
                    'tipe'             => 'tunjangan',
                    'keterangan'       => $label,
                    'jumlah_satuan'    => 1,
                    'satuan'           => 'periode',
                    'nilai_per_satuan' => $tunjangan,
                    'subtotal'         => $tunjangan,
                    'referensi_ids'    => [],
                ]);
            }

            if ($potongan > 0) {
                $slip->detailPenggajian()->create([
                    'tipe'             => 'potongan_lain',
                    'keterangan'       => $label,
                    'jumlah_satuan'    => 1,
                    'satuan'           => 'periode',
                    'nilai_per_satuan' => $potongan,
                    'subtotal'         => -$potongan,
                    'referensi_ids'    => [],
                ]);
            }
        });
    }

    /**
     * Hitung ulang satu slip yang SUDAH ADA, lalu pasang kembali koreksi
     * manualnya. Tidak pernah membuat slip baru: menerbitkan slip adalah
     * tindakan sadar admin lewat tombol Generate, bukan efek samping
     * penyuntingan gaji pokok.
     *
     * @return array{status:string, pesan:string, lama:float, baru:float}
     */
    public function regenerasi(TenagaPendidik $guru, PeriodePenggajian $periode): array
    {
        $slip = Penggajian::where('periode_penggajian_id', $periode->id)
            ->where('tenaga_pendidik_id', $guru->id)->first();

        if (! $slip) {
            return ['status' => 'tanpa_slip', 'lama' => 0.0, 'baru' => 0.0,
                'pesan' => "{$periode->nama_bulan}: slipnya belum pernah digenerate"];
        }

        if ($periode->status === 'dibayar' || $slip->difinalisasi_oleh || $slip->dibayar_pada) {
            return ['status' => 'terkunci', 'lama' => (float) $slip->gaji_bersih,
                'baru' => (float) $slip->gaji_bersih,
                'pesan' => "{$periode->nama_bulan}: slip sudah final/dibayar, tidak disentuh"];
        }

        $lama    = (float) $slip->gaji_bersih;
        $koreksi = $this->bacaKoreksiSlip($slip);

        $this->payroll->hitung($guru, $periode, dryRun: false);

        if ($koreksi) {
            $this->terapkanKoreksi($slip->fresh(), $koreksi['tunjangan'],
                $koreksi['potongan'], $koreksi['catatan']);
        }

        $baru = (float) $slip->fresh()->gaji_bersih;

        return [
            'status' => 'digenerate',
            'lama'   => $lama,
            'baru'   => $baru,
            'pesan'  => sprintf('%s: Rp %s → Rp %s%s', $periode->nama_bulan,
                number_format($lama, 0, ',', '.'), number_format($baru, 0, ',', '.'),
                $koreksi ? ' (koreksi manual dipertahankan)' : ''),
        ];
    }

    /**
     * Periode yang terdampak sebuah tanggal berlaku: yang berakhir pada atau
     * sesudah tanggal itu. Periode yang sudah lewat tidak terdampak — gaji yang
     * sudah dihitung dengan nominal lama memang begitu adanya.
     */
    public function periodeTerdampak(string $berlakuMulai): Collection
    {
        return PeriodePenggajian::whereDate('tanggal_selesai', '>=', $berlakuMulai)
            ->orderBy('tanggal_mulai')->get();
    }
}
