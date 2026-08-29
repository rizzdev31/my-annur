<?php

namespace App\Services;

use App\Models\Lembur;
use App\Models\LemburPeserta;
use App\Models\SettingVakasi;
use App\Models\TenagaPendidik;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * LemburService — seluruh aturan bisnis lembur dalam satu tempat,
 * dipakai bersama oleh controller web (admin) & API (guru).
 *
 * Aturan terkunci:
 *  - Maks 1 lembur aktif/sah per guru per tanggal.
 *  - Tarif flat dari SettingVakasi 'lembur' (tanpa override).
 *  - jam_selesai = jam_mulai + durasi (opsi A); durasi >= min_durasi setting.
 *  - Geofence KETAT: upload di luar radius ditolak.
 *  - Upload hanya dalam jendela [jam_selesai, jam_selesai + grace].
 *  - Lewat grace → admin batalkan / approve manual.
 */
class LemburService
{
    public function __construct(private LokasiAbsensiService $lokasi) {}

    // ══════════════════════════════════════════════════════════════════════════
    // PENGAJUAN & PEMBUATAN
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * User mengajukan lembur (mandiri). Jam & tarif diisi admin saat approve.
     */
    public function ajukan(TenagaPendidik $guru, array $data, int $userId): Lembur
    {
        $tanggal = $data['tanggal'];
        $durasi  = (int) ($data['durasi_menit'] ?? 0);
        if (empty($data['jam_mulai']) || $durasi <= 0) {
            throw new RuntimeException('Jam mulai & durasi lembur wajib diisi.');
        }
        $this->pastikanBelumAdaLemburHariItu($guru->id, $tanggal);

        return DB::transaction(function () use ($guru, $data, $userId, $tanggal, $durasi) {
            // Jam ditentukan oleh user; tarif menyusul saat admin approve.
            $lembur = new Lembur([
                'jabatan_id'   => $guru->jabatan_id,
                'judul'        => $data['judul'],
                'deskripsi'    => $data['deskripsi'] ?? null,
                'tanggal'      => $tanggal,
                'jam_mulai'    => $data['jam_mulai'],
                'durasi_menit' => $durasi,
                'sumber_input' => 'mandiri',
                'status'       => 'diajukan',
                'diajukan_oleh'=> $userId,
                'dibuat_oleh'  => $userId,
            ]);
            $lembur->jam_selesai = $lembur->hitungJamSelesai();
            $lembur->save();

            $lembur->peserta()->create([
                'tenaga_pendidik_id' => $guru->id,
                'status'             => 'menunggu_bukti',
            ]);

            return $lembur;
        });
    }

    /**
     * Admin membuat lembur kolektif: langsung disetujui + assign banyak guru.
     * Guru tinggal upload bukti.
     */
    public function buatKolektif(array $data, array $guruIds, int $adminId): Lembur
    {
        $setting = $this->ambilSetting($data['setting_vakasi_id']);
        $this->validasiDurasi((int) $data['durasi_menit'], $setting);

        // Pastikan tiap guru belum punya lembur lain di tanggal tsb
        foreach ($guruIds as $gid) {
            $this->pastikanBelumAdaLemburHariItu((int) $gid, $data['tanggal']);
        }

        return DB::transaction(function () use ($data, $guruIds, $adminId, $setting) {
            $lembur = new Lembur([
                'jabatan_id'   => $data['jabatan_id'],
                'judul'        => $data['judul'],
                'deskripsi'    => $data['deskripsi'] ?? null,
                'tanggal'      => $data['tanggal'],
                'jam_mulai'    => $data['jam_mulai'],
                'durasi_menit' => (int) $data['durasi_menit'],
                'sumber_input' => 'kolektif',
                'status'       => 'disetujui',
                'disetujui_oleh' => $adminId,
                'disetujui_pada' => now(),
                'dibuat_oleh'  => $adminId,
            ]);
            $this->terapkanSetting($lembur, $setting);
            $lembur->jam_selesai = $lembur->hitungJamSelesai();
            $lembur->save();

            foreach (array_unique($guruIds) as $gid) {
                $lembur->peserta()->create([
                    'tenaga_pendidik_id' => (int) $gid,
                    'status'             => 'menunggu_bukti',
                    'nominal_vakasi'     => $setting->nominal,
                ]);
            }

            return $lembur;
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // APPROVAL ADMIN (untuk pengajuan mandiri)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Admin menyetujui pengajuan: tentukan jam_mulai + durasi + SettingVakasi.
     */
    public function setujui(Lembur $lembur, array $data, int $adminId): Lembur
    {
        if ($lembur->status !== 'diajukan') {
            throw new RuntimeException('Hanya pengajuan berstatus "diajukan" yang bisa disetujui.');
        }
        // Jam & durasi sudah diisi user saat mengajukan — admin hanya pilih tarif.
        if (empty($lembur->jam_mulai) || (int) $lembur->durasi_menit <= 0) {
            throw new RuntimeException('Data jam/durasi lembur tidak lengkap pada pengajuan.');
        }
        $setting = $this->ambilSetting($data['setting_vakasi_id']);
        $this->validasiDurasi((int) $lembur->durasi_menit, $setting);

        return DB::transaction(function () use ($lembur, $data, $adminId, $setting) {
            $lembur->fill([
                'status'         => 'disetujui',
                'disetujui_oleh' => $adminId,
                'disetujui_pada' => now(),
                'catatan'        => $data['catatan'] ?? $lembur->catatan,
            ]);
            $this->terapkanSetting($lembur, $setting);
            $lembur->jam_selesai = $lembur->hitungJamSelesai(); // re-sync dgn jam user
            $lembur->save();

            // Snapshot nominal ke peserta
            $lembur->peserta()->update(['nominal_vakasi' => $setting->nominal]);

            return $lembur;
        });
    }

    public function tolak(Lembur $lembur, ?string $catatan, int $adminId): void
    {
        $lembur->update([
            'status'         => 'ditolak',
            'disetujui_oleh' => $adminId,
            'disetujui_pada' => now(),
            'catatan'        => $catatan,
        ]);
        $lembur->peserta()->where('status', 'menunggu_bukti')->update(['status' => 'ditolak']);
    }

    public function batalkan(Lembur $lembur, ?string $catatan = null): void
    {
        $lembur->update(['status' => 'dibatalkan', 'catatan' => $catatan ?? $lembur->catatan]);
        $lembur->peserta()->whereIn('status', ['menunggu_bukti'])->update(['status' => 'dibatalkan']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPLOAD BUKTI (user) — GPS ketat + jendela waktu
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param array $payload { foto_path, deskripsi, latitude, longitude, wifi_ssid?, wifi_bssid? }
     */
    public function uploadBukti(LemburPeserta $peserta, array $payload): LemburPeserta
    {
        $lembur = $peserta->lembur;

        if ($lembur->status !== 'disetujui') {
            throw new RuntimeException('Lembur belum disetujui admin.');
        }
        if ($peserta->status !== 'menunggu_bukti') {
            throw new RuntimeException('Bukti sudah dikirim atau peserta tidak aktif.');
        }
        // Jendela waktu: hanya setelah jam selesai, sebelum lewat grace
        $now = TimezoneHelper::now();
        if ($now->lt($lembur->waktuSelesai())) {
            throw new RuntimeException('Belum waktunya upload. Bukti dibuka setelah jam lembur selesai.');
        }
        if ($now->gt($lembur->batasAkhirUpload())) {
            throw new RuntimeException('Waktu upload sudah lewat. Hubungi admin untuk pengesahan manual.');
        }

        // Geofence KETAT — reuse validasi lokasi absensi
        $hasil = $this->validasiLokasi($peserta->tenaga_pendidik_id, $payload, $lembur->tanggal->toDateString());
        if (!$hasil['valid']) {
            throw new RuntimeException($hasil['pesan'] ?? 'Lokasi di luar area absensi. Upload ditolak.');
        }

        // Cegah dobel sah di tanggal yang sama
        $this->pastikanBelumAdaSahHariItu($peserta->tenaga_pendidik_id, $lembur->tanggal->toDateString(), $lembur->id);

        $peserta->update([
            'status'            => 'sah',
            'deskripsi'         => $payload['deskripsi'] ?? null,
            'foto_bukti'        => $payload['foto_path'],
            'diunggah_pada'     => now(),
            'latitude'          => $payload['latitude']  ?? null,
            'longitude'         => $payload['longitude'] ?? null,
            'validasi_lokasi'   => $hasil['tipe_validasi'],
            'jarak_meter'       => $hasil['jarak_meter'],
            'setting_lokasi_id' => $hasil['setting_lokasi_id'],
            'nama_wifi'         => $hasil['nama_wifi'],
            'bssid_wifi'        => $hasil['bssid_wifi'],
            'nominal_vakasi'    => $lembur->nominal_vakasi ?? 0,
            'metode_pengesahan' => 'otomatis',
        ]);

        return $peserta->refresh();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EXCEPTION HANDLING — user lupa bukti (lewat grace)
    // ══════════════════════════════════════════════════════════════════════════

    /** Admin mengesahkan manual (tanpa GPS user) — wajib alasan. */
    public function sahkanManual(LemburPeserta $peserta, string $alasan, int $adminId): LemburPeserta
    {
        $lembur = $peserta->lembur;
        if ($lembur->status !== 'disetujui') {
            throw new RuntimeException('Lembur tidak dalam status disetujui.');
        }
        if ($peserta->status === 'sah') {
            throw new RuntimeException('Peserta sudah sah.');
        }
        $this->pastikanBelumAdaSahHariItu($peserta->tenaga_pendidik_id, $lembur->tanggal->toDateString(), $lembur->id);

        $peserta->update([
            'status'            => 'sah',
            'validasi_lokasi'   => 'bypass_admin',
            'nominal_vakasi'    => $lembur->nominal_vakasi ?? 0,
            'metode_pengesahan' => 'manual_admin',
            'disahkan_oleh'     => $adminId,
            'disahkan_pada'     => now(),
            'alasan_pengesahan' => $alasan,
        ]);

        return $peserta->refresh();
    }

    public function batalkanPeserta(LemburPeserta $peserta, ?string $alasan = null): void
    {
        $peserta->update([
            'status'            => 'dibatalkan',
            'alasan_pengesahan' => $alasan,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function ambilSetting(int $id): SettingVakasi
    {
        $setting = SettingVakasi::where('id', $id)
            ->where('tipe_aktivitas', 'lembur')
            ->where('is_aktif', true)
            ->first();
        if (!$setting) {
            throw new RuntimeException('Setting vakasi lembur tidak valid / tidak aktif.');
        }
        return $setting;
    }

    private function terapkanSetting(Lembur $lembur, SettingVakasi $setting): void
    {
        $lembur->setting_vakasi_id  = $setting->id;
        $lembur->nominal_vakasi     = $setting->nominal;
        $lembur->min_durasi_menit   = $setting->min_durasi_menit;
        $lembur->batas_grace_menit  = $setting->batas_grace_menit ?? 60;
    }

    private function validasiDurasi(int $durasi, SettingVakasi $setting): void
    {
        $min = (int) ($setting->min_durasi_menit ?? 0);
        if ($durasi <= 0) {
            throw new RuntimeException('Durasi lembur tidak valid.');
        }
        if ($min > 0 && $durasi < $min) {
            throw new RuntimeException("Durasi minimal lembur untuk tarif ini adalah {$min} menit.");
        }
    }

    private function pastikanBelumAdaLemburHariItu(int $guruId, string $tanggal): void
    {
        $ada = LemburPeserta::byGuru($guruId)
            ->whereIn('status', ['menunggu_bukti', 'sah'])
            ->whereHas('lembur', fn($q) =>
                $q->whereDate('tanggal', $tanggal)
                  ->whereNotIn('status', ['ditolak', 'dibatalkan'])
            )->exists();
        if ($ada) {
            throw new RuntimeException('Guru sudah memiliki lembur pada tanggal tersebut (maks 1/hari).');
        }
    }

    private function pastikanBelumAdaSahHariItu(int $guruId, string $tanggal, int $exceptLemburId): void
    {
        $ada = LemburPeserta::byGuru($guruId)
            ->where('status', 'sah')
            ->whereHas('lembur', fn($q) =>
                $q->whereDate('tanggal', $tanggal)->where('id', '!=', $exceptLemburId)
            )->exists();
        if ($ada) {
            throw new RuntimeException('Sudah ada lembur SAH pada tanggal ini (maks 1/hari).');
        }
    }

    /**
     * Geofence ketat untuk lembur: hanya valid_koordinat / valid_wifi yang diterima.
     * (bypass dinas_luar/izin tidak berlaku untuk lembur).
     */
    private function validasiLokasi(int $guruId, array $payload, string $tanggal): array
    {
        $hasil = $this->lokasi->validasi($guruId, [
            'latitude'   => $payload['latitude']   ?? null,
            'longitude'  => $payload['longitude']  ?? null,
            'wifi_ssid'  => $payload['wifi_ssid']  ?? null,
            'wifi_bssid' => $payload['wifi_bssid'] ?? null,
            'status'     => 'hadir',
            'tanggal'    => $tanggal,
        ]);

        $diterima = in_array($hasil['tipe_validasi'] ?? null, ['valid_koordinat', 'valid_wifi'], true);

        return [
            'valid'             => (bool) ($hasil['valid'] ?? false) && $diterima,
            'tipe_validasi'     => $diterima ? $hasil['tipe_validasi'] : 'invalid',
            'setting_lokasi_id' => $hasil['setting_lokasi_id'] ?? null,
            'jarak_meter'       => $hasil['jarak_meter'] ?? null,
            'nama_wifi'         => $hasil['nama_wifi'] ?? ($payload['wifi_ssid'] ?? null),
            'bssid_wifi'        => $hasil['bssid_wifi'] ?? ($payload['wifi_bssid'] ?? null),
            'pesan'             => $hasil['pesan'] ?? null,
        ];
    }
}
