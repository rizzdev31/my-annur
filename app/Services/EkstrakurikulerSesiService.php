<?php

namespace App\Services;

use App\Models\Ekstrakurikuler;
use App\Models\EkstrakurikulerPertemuan;
use App\Models\TenagaPendidik;
use Carbon\Carbon;

/**
 * EkstrakurikulerSesiService — satu sumber aturan "boleh tidaknya pembina
 * membuka pertemuan ekskul", agar controller tidak menulis ulang aturannya.
 *
 * Kebijakan 23 Sep 2026: ekskul TIDAK terikat hari & jam. Tiap bulan pembina
 * punya jatah pertemuan (`pertemuan_per_bulan`, bawaan 4×) dan bebas memilih
 * kapan mengisinya. Penjaganya:
 *
 *  1. JATAH  — maksimal N pertemuan dalam satu bulan kalender, satu pertemuan
 *              per tanggal, tidak boleh untuk tanggal yang belum tiba, dan
 *              tidak boleh mundur ke bulan sebelumnya (opsional diperketat
 *              lewat `batas_isi_hari`).
 *  2. LOKASI — penanda sah: pembina harus berada di area yang diizinkan,
 *              divalidasi LokasiAbsensiService (mesin yang sama dengan
 *              check-in harian: lokasi global + lokasi per-guru).
 *              Ekskul di luar pesantren → matikan `wajib_lokasi`.
 */
class EkstrakurikulerSesiService
{
    /** Jatah pertemuan sebulan bila ekskul belum disetel. */
    public const KUOTA_BULANAN_DEFAULT = 4;

    public function __construct(private readonly LokasiAbsensiService $lokasi = new LokasiAbsensiService()) {}

    public function kuotaBulanan(Ekstrakurikuler $e): int
    {
        return (int) ($e->pertemuan_per_bulan ?: self::KUOTA_BULANAN_DEFAULT);
    }

    /** Pertemuan yang sudah dibuat pada bulan kalender tanggal tsb. */
    public function terpakaiBulan(Ekstrakurikuler $e, string $tanggal): int
    {
        $tgl = Carbon::parse($tanggal, TimezoneHelper::TZ);

        return EkstrakurikulerPertemuan::where('ekstrakurikuler_id', $e->id)
            ->whereBetween('tanggal', [
                $tgl->copy()->startOfMonth()->toDateString(),
                $tgl->copy()->endOfMonth()->toDateString(),
            ])->count();
    }

    public function sisaBulan(Ekstrakurikuler $e, string $tanggal): int
    {
        return max(0, $this->kuotaBulanan($e) - $this->terpakaiBulan($e, $tanggal));
    }

    /**
     * @throws \DomainException bila jatah habis / tanggal tidak diizinkan.
     */
    public function pastikanBolehMulai(Ekstrakurikuler $e, string $tanggal): void
    {
        $now  = TimezoneHelper::now();
        $tgl  = Carbon::parse($tanggal, TimezoneHelper::TZ)->startOfDay();
        $hari = $now->copy()->startOfDay();

        if ($tgl->gt($hari)) {
            throw new \DomainException('Pertemuan tidak bisa dibuka untuk tanggal yang belum tiba.');
        }

        // Jatah dihitung per bulan kalender → tanggal harus di bulan berjalan.
        if (!$tgl->isSameMonth($hari)) {
            throw new \DomainException('Pertemuan hanya bisa diisi untuk bulan berjalan ('
                . $hari->locale('id')->isoFormat('MMMM YYYY') . ').');
        }

        // Opsional: admin memperketat batas mundur.
        if ($e->batas_isi_hari !== null && (int) $tgl->diffInDays($hari) > (int) $e->batas_isi_hari) {
            throw new \DomainException((int) $e->batas_isi_hari === 0
                ? 'Pertemuan hanya bisa diisi pada hari pelaksanaannya.'
                : "Pertemuan hanya bisa diisi maksimal {$e->batas_isi_hari} hari setelah pelaksanaan.");
        }

        if (EkstrakurikulerPertemuan::where('ekstrakurikuler_id', $e->id)
            ->whereDate('tanggal', $tgl->toDateString())->exists()) {
            throw new \DomainException('Sudah ada pertemuan pada tanggal ini. Lanjutkan pertemuan tersebut.');
        }

        $kuota = $this->kuotaBulanan($e);
        if ($this->terpakaiBulan($e, $tgl->toDateString()) >= $kuota) {
            throw new \DomainException("Jatah {$kuota} pertemuan bulan ini sudah terpakai semua.");
        }
    }

    /**
     * Validasi lokasi pembina — penanda sah pengisian. Mengembalikan data bukti
     * untuk disimpan pada pertemuan. Ekskul dengan `wajib_lokasi` = false
     * dilewati (titik tetap dicatat bila pembina mengirimnya).
     *
     * @throws \DomainException bila di luar area yang diizinkan.
     */
    public function validasiLokasi(Ekstrakurikuler $e, TenagaPendidik $tp, array $payload): array
    {
        $hasil = $this->lokasi->validasi($tp->id, [
            'latitude'   => $payload['latitude']   ?? null,
            'longitude'  => $payload['longitude']  ?? null,
            'wifi_ssid'  => $payload['wifi_ssid']  ?? null,
            'wifi_bssid' => $payload['wifi_bssid'] ?? null,
            'status'     => 'hadir',
            'tanggal'    => $payload['tanggal'] ?? TimezoneHelper::now()->toDateString(),
        ]);

        if (!$e->wajibLokasi()) {
            return $this->bukti($payload, array_merge($hasil, [
                'tipe_validasi' => $hasil['valid'] ? $hasil['tipe_validasi'] : 'tidak_diperiksa',
            ]));
        }

        if (!$hasil['valid']) {
            $tanpaTitik = ($payload['latitude'] ?? null) === null && ($payload['wifi_bssid'] ?? null) === null;
            throw new \DomainException($tanpaTitik
                ? 'Aktifkan izin lokasi (GPS) untuk membuka pertemuan ekstrakurikuler.'
                : ($hasil['pesan'] ?? 'Anda berada di luar area yang diizinkan.'));
        }

        return $this->bukti($payload, $hasil);
    }

    private function bukti(array $payload, array $hasil): array
    {
        return [
            'lat'               => $payload['latitude']  ?? null,
            'lng'               => $payload['longitude'] ?? null,
            'jarak_meter'       => $hasil['jarak_meter'] ?? null,
            'validasi_lokasi'   => $hasil['tipe_validasi'] ?? 'tidak_diperiksa',
            'setting_lokasi_id' => $hasil['setting_lokasi_id'] ?? null,
            'nama_wifi'         => $hasil['nama_wifi'] ?? null,
        ];
    }
}
