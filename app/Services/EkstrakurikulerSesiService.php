<?php

namespace App\Services;

use App\Models\Ekstrakurikuler;
use App\Models\TenagaPendidik;
use Carbon\Carbon;

/**
 * EkstrakurikulerSesiService — satu sumber aturan "boleh tidaknya pembina
 * membuka pertemuan ekskul", agar controller tidak menulis ulang aturannya.
 *
 * Dua penjaga:
 *  1. WAKTU  — pertemuan hanya boleh dibuka pada hari & jam ekskul itu
 *              (toleransi TOLERANSI_MENIT sebelum/sesudah), tidak boleh untuk
 *              tanggal di masa depan, dan mundur paling jauh `batas_isi_hari`.
 *  2. LOKASI — pembina harus berada di area yang diizinkan, divalidasi oleh
 *              LokasiAbsensiService (mesin yang sama dengan check-in harian):
 *              lokasi global pesantren + lokasi per-guru yang di-assign admin.
 *              Ekskul di luar pesantren → matikan `wajib_lokasi` pada ekskulnya,
 *              atau assign lokasi khusus ke pembinanya.
 */
class EkstrakurikulerSesiService
{
    /** Toleransi buka pertemuan, sebelum jam mulai & sesudah jam selesai. */
    public const TOLERANSI_MENIT = 30;

    /** Batas mundur bawaan bila ekskul belum diberi `batas_isi_hari`. */
    public const BATAS_ISI_HARI_DEFAULT = 1;

    public function __construct(private readonly LokasiAbsensiService $lokasi = new LokasiAbsensiService()) {}

    /**
     * @throws \DomainException bila tanggal/jam tidak diizinkan.
     */
    public function pastikanBolehMulai(Ekstrakurikuler $e, string $tanggal): void
    {
        $now  = TimezoneHelper::now();
        $tgl  = Carbon::parse($tanggal, TimezoneHelper::TZ)->startOfDay();
        $hari = $now->copy()->startOfDay();

        if ($tgl->gt($hari)) {
            throw new \DomainException('Pertemuan tidak bisa dibuka untuk tanggal yang belum tiba.');
        }

        $batas = $e->batas_isi_hari ?? self::BATAS_ISI_HARI_DEFAULT;
        $mundur = (int) $tgl->diffInDays($hari);
        if ($mundur > $batas) {
            throw new \DomainException($batas === 0
                ? 'Pertemuan hanya bisa diisi pada hari pelaksanaannya.'
                : "Pertemuan hanya bisa diisi maksimal {$batas} hari setelah pelaksanaan.");
        }

        // Hari pelaksanaan (bila ekskul punya jadwal hari tetap).
        if ($e->hari && TimezoneHelper::namaHariDB($tgl) !== strtolower($e->hari)) {
            throw new \DomainException("Jadwal {$e->nama} hari " . ucfirst($e->hari)
                . '. Tanggal yang dipilih bukan hari tersebut.');
        }

        // Jendela jam hanya ditegakkan saat mengisi di hari-H — pengisian susulan
        // (dalam batas hari) memang terjadi di luar jam ekskul.
        if ($mundur === 0 && $e->jam_mulai && $e->jam_selesai) {
            [$buka, $tutup] = $this->jendela($e, $tgl);
            if ($now->lt($buka) || $now->gt($tutup)) {
                throw new \DomainException('Pertemuan hanya bisa dibuka pukul '
                    . $buka->format('H:i') . '–' . $tutup->format('H:i') . '.');
            }
        }
    }

    /**
     * Validasi lokasi pembina. Mengembalikan data bukti untuk disimpan pada
     * pertemuan. Ekskul dengan `wajib_lokasi` = false dilewati (tetap dicatat
     * bila pembina mengirim koordinat).
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

    /** Jendela buka pertemuan pada satu tanggal. */
    public function jendela(Ekstrakurikuler $e, Carbon $tgl): array
    {
        $buka  = Carbon::parse($tgl->toDateString() . ' ' . $e->jam_mulai, TimezoneHelper::TZ)
            ->subMinutes(self::TOLERANSI_MENIT);
        $tutup = Carbon::parse($tgl->toDateString() . ' ' . $e->jam_selesai, TimezoneHelper::TZ)
            ->addMinutes(self::TOLERANSI_MENIT);

        return [$buka, $tutup];
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
