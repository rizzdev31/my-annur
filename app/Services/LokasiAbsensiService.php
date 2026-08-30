<?php

namespace App\Services;

use App\Models\AbsensiHarian;
use App\Models\PengajuanIzin;
use App\Models\SettingLokasiAbsensi;
use App\Models\TenagaPendidik;
use Carbon\Carbon;

/**
 * LokasiAbsensiService
 *
 * Logika validasi absensi:
 *
 *  URUTAN PRIORITAS (berhenti di yang pertama valid):
 *  ┌─────────────────────────────────────────────────────────────────┐
 *  │ 0. Status izin aktif (disetujui) → valid_izin                  │
 *  │ 1. Status dinas_luar → valid_dinas_luar                        │
 *  │ 2. Lokasi per-user yang di-assign → valid_koordinat/valid_wifi  │
 *  │ 3. Lokasi global pesantren → valid_koordinat/valid_wifi         │
 *  │ 4. Tidak ada yang cocok → invalid                              │
 *  └─────────────────────────────────────────────────────────────────┘
 *
 * Multi-lokasi per user:
 *  - Satu user bisa di-assign ke BANYAK lokasi (pivot tenaga_pendidik_lokasi)
 *  - Dicek semua lokasi yang aktif dan berlaku hari ini
 *  - Jika ada SATU yang valid → absen diizinkan
 */
class LokasiAbsensiService
{
    /**
     * Validasi absensi lengkap — entry point utama dari Flutter API.
     *
     * @param int   $tenagaPendidikId
     * @param array $payload  { latitude, longitude, wifi_ssid, wifi_bssid, status, tanggal }
     */
    public function validasi(int $tenagaPendidikId, array $payload): array
    {
        $status    = $payload['status'] ?? 'hadir';
        $lat       = isset($payload['latitude'])  ? (float) $payload['latitude']  : null;
        $lng       = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $wifiSsid  = $payload['wifi_ssid']  ?? null;
        $wifiBssid = $payload['wifi_bssid'] ?? null;
        $tanggal   = $payload['tanggal']    ?? now()->toDateString();

        $guru = TenagaPendidik::find($tenagaPendidikId);
        if (!$guru) {
            return $this->hasilInvalid('Tenaga pendidik tidak ditemukan.', $wifiSsid, $wifiBssid);
        }

        // ── 0. Cek izin aktif ────────────────────────────────────────────────
        // Hanya bypass lokasi jika setting mengizinkan (izinkan_izin_aktif = true)
        $izinAktif = $this->cekIzinAktif($guru, $tanggal);
        if ($izinAktif) {
            $izinkanIzinAktif = SettingLokasiAbsensi::aktif()
                ->where('izinkan_izin_aktif', true)
                ->exists();

            if ($izinkanIzinAktif) {
                return [
                    'valid'             => true,
                    'tipe_validasi'     => 'valid_izin',
                    'setting_lokasi_id' => null,
                    'jarak_meter'       => null,
                    'nama_wifi'         => $wifiSsid,
                    'bssid_wifi'        => $wifiBssid,
                    'pesan'             => "Memiliki izin aktif: {$izinAktif}. Absen diizinkan dari mana saja.",
                ];
            }
            // izin ada tapi izinkan_izin_aktif = false → tetap wajib validasi lokasi
        }

        // ── 1. Dinas Luar ────────────────────────────────────────────────────
        if ($status === 'dinas_luar') {
            $diizinkan = SettingLokasiAbsensi::aktif()
                ->where('izinkan_dinas_luar', true)->exists();
            if ($diizinkan) {
                return [
                    'valid'             => true,
                    'tipe_validasi'     => 'valid_dinas_luar',
                    'setting_lokasi_id' => null,
                    'jarak_meter'       => null,
                    'nama_wifi'         => $wifiSsid,
                    'bssid_wifi'        => $wifiBssid,
                    'pesan'             => 'Status dinas luar. Absen diizinkan dari lokasi mana saja.',
                ];
            }
        }

        // ── 2. Lokasi per-user (prioritas lebih tinggi dari global) ──────────
        $lokasiUser = $guru->lokasiAbsensiAktif()->get();
        if ($lokasiUser->isNotEmpty()) {
            $hasil = $this->cocokanLokasiDariKoleksi(
                $lokasiUser, $lat, $lng, $wifiSsid, $wifiBssid
            );
            if ($hasil['valid']) return $hasil;
        }

        // ── 3. Lokasi global pesantren ───────────────────────────────────────
        $lokasiGlobal = SettingLokasiAbsensi::aktif()->global()->get();
        if ($lokasiGlobal->isNotEmpty()) {
            $hasil = $this->cocokanLokasiDariKoleksi(
                $lokasiGlobal, $lat, $lng, $wifiSsid, $wifiBssid
            );
            if ($hasil['valid']) return $hasil;

            // Tidak valid — kembalikan jarak ke titik global terdekat
            return $hasil;
        }

        // ── 4. Tidak ada konfigurasi lokasi sama sekali ──────────────────────
        return $this->hasilInvalid(
            'Belum ada setting lokasi aktif. Hubungi administrator.',
            $wifiSsid, $wifiBssid
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Cocokkan dari koleksi lokasi (per-user atau global).
     * Cek WiFi dulu, baru GPS.
     */
    private function cocokanLokasiDariKoleksi(
        \Illuminate\Support\Collection $lokasiList,
        ?float $lat, ?float $lng,
        ?string $wifiSsid, ?string $wifiBssid
    ): array {
        // 2a. WiFi check dulu (lebih reliable)
        foreach ($lokasiList->where('tipe', 'wifi') as $s) {
            $bssidMatch = $wifiBssid && $s->wifi_bssid
                && strtolower($wifiBssid) === strtolower($s->wifi_bssid);
            $ssidMatch  = $wifiSsid  && $s->wifi_ssid
                && strtolower($wifiSsid) === strtolower($s->wifi_ssid);

            if ($bssidMatch || $ssidMatch) {
                return [
                    'valid'             => true,
                    'tipe_validasi'     => 'valid_wifi',
                    'setting_lokasi_id' => $s->id,
                    'jarak_meter'       => null,
                    'nama_wifi'         => $wifiSsid,
                    'bssid_wifi'        => $wifiBssid,
                    'pesan'             => "Terhubung WiFi pesantren: {$s->nama}.",
                ];
            }
        }

        // 2b. GPS check
        if ($lat !== null && $lng !== null) {
            $jarakTerdekat = null;
            $namaTerdekat  = null;

            foreach ($lokasiList->where('tipe', 'koordinat') as $s) {
                if (!$s->latitude || !$s->longitude) continue;

                $jarak = $this->hitungJarak($lat, $lng, $s->latitude, $s->longitude);

                if ($jarak <= $s->radius_meter) {
                    return [
                        'valid'             => true,
                        'tipe_validasi'     => 'valid_koordinat',
                        'setting_lokasi_id' => $s->id,
                        'jarak_meter'       => round($jarak, 2),
                        'nama_wifi'         => $wifiSsid,
                        'bssid_wifi'        => $wifiBssid,
                        'pesan'             => "Dalam radius {$s->nama} ({$jarak}m / {$s->radius_meter}m).",
                    ];
                }

                if ($jarakTerdekat === null || $jarak < $jarakTerdekat) {
                    $jarakTerdekat = $jarak;
                    $namaTerdekat  = $s->nama;
                }
            }

            // GPS ada tapi di luar semua titik
            $pesanJarak = $jarakTerdekat !== null
                ? "Jarak ke {$namaTerdekat}: " . round($jarakTerdekat) . "m."
                : '';

            return [
                'valid'             => false,
                'tipe_validasi'     => 'invalid',
                'setting_lokasi_id' => null,
                'jarak_meter'       => $jarakTerdekat ? round($jarakTerdekat, 2) : null,
                'nama_wifi'         => $wifiSsid,
                'bssid_wifi'        => $wifiBssid,
                'pesan'             => "Di luar area yang diizinkan. {$pesanJarak}",
            ];
        }

        return $this->hasilInvalid(
            'GPS tidak aktif dan tidak terhubung WiFi pesantren.',
            $wifiSsid, $wifiBssid
        );
    }

    /**
     * Cek apakah guru memiliki izin yang disetujui dan aktif pada tanggal tersebut.
     * Jika ya, kembalikan nama jenis izin. Jika tidak, null.
     */
    private function cekIzinAktif(TenagaPendidik $guru, string $tanggal): ?string
    {
        // FIX: relasi di model PengajuanIzin adalah 'jenisPengajuan()', bukan 'settingJenisPengajuan'
        $izin = PengajuanIzin::where('tenaga_pendidik_id', $guru->id)
            ->where('status', 'disetujui')
            ->where('is_sementara', false)   // izin sementara tak membebaskan validasi lokasi check-in
            ->where('is_datang_terlambat', false)   // datang terlambat: check-in lokasi tetap normal
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where('tanggal_selesai', '>=', $tanggal)
            ->with('jenisPengajuan')
            ->first();

        return $izin
            ? ($izin->jenisPengajuan?->nama ?? 'Izin')
            : null;
    }

    private function hasilInvalid(string $pesan, ?string $wifi, ?string $bssid): array
    {
        return [
            'valid'             => false,
            'tipe_validasi'     => 'invalid',
            'setting_lokasi_id' => null,
            'jarak_meter'       => null,
            'nama_wifi'         => $wifi,
            'bssid_wifi'        => $bssid,
            'pesan'             => $pesan,
        ];
    }

    /**
     * Haversine formula — jarak 2 koordinat dalam meter.
     */
    public function hitungJarak(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Ambil semua setting aktif yang berlaku untuk guru tertentu.
     * Dipakai Flutter untuk validasi di sisi client (offline-ready).
     *
     * @param int $tenagaPendidikId
     */
    public function getSettingUntukGuru(int $tenagaPendidikId): array
    {
        // Lokasi global
        $global = SettingLokasiAbsensi::aktif()->global()->get();

        // Lokasi per-user yang aktif
        $guru = TenagaPendidik::find($tenagaPendidikId);
        $perUser = $guru ? $guru->lokasiAbsensiAktif()->get() : collect();

        // Gabungkan, hilangkan duplikat
        $semua = $global->merge($perUser)->unique('id');

        return $semua->map(fn($s) => [
            'id'                 => $s->id,
            'nama'               => $s->nama,
            'tipe'               => $s->tipe,
            'lingkup'            => $s->lingkup,
            'latitude'           => $s->latitude,
            'longitude'          => $s->longitude,
            'radius_meter'       => $s->radius_meter,
            'wifi_ssid'          => $s->wifi_ssid,
            'wifi_bssid'         => $s->wifi_bssid,
            'izinkan_dinas_luar' => $s->izinkan_dinas_luar,
            'izinkan_izin_aktif' => $s->izinkan_izin_aktif,
        ])->values()->toArray();
    }

    /**
     * Ambil semua lokasi aktif (untuk superadmin / global setting page).
     */
    public function getSettingAktif(): array
    {
        return SettingLokasiAbsensi::aktif()->get()->map(fn($s) => [
            'id'                 => $s->id,
            'nama'               => $s->nama,
            'tipe'               => $s->tipe,
            'lingkup'            => $s->lingkup,
            'latitude'           => $s->latitude,
            'longitude'          => $s->longitude,
            'radius_meter'       => $s->radius_meter,
            'wifi_ssid'          => $s->wifi_ssid,
            'wifi_bssid'         => $s->wifi_bssid,
            'izinkan_dinas_luar' => $s->izinkan_dinas_luar,
            'izinkan_izin_aktif' => $s->izinkan_izin_aktif,
        ])->toArray();
    }
}