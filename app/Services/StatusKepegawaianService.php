<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\Pengawas;
use App\Models\PiketJadwal;
use App\Models\PushSubscription;
use App\Models\TenagaPendidik;
use App\Models\RiwayatStatusKepegawaian;
use App\Models\LogAktivitas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StatusKepegawaianService
{
    /**
     * Ubah status kepegawaian dengan validasi & audit trail.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function ubahStatus(
        TenagaPendidik $guru,
        string $statusBaru,
        array $data
    ): RiwayatStatusKepegawaian {

        $statusLama = $guru->status_kepegawaian ?? 'aktif';

        // ── Validasi transisi status ─────────────────────────────────────────
        $this->validasiTransisi($statusLama, $statusBaru, $guru);

        return DB::transaction(function () use ($guru, $statusLama, $statusBaru, $data) {

            // Upload dokumen pendukung jika ada
            $dokumenPath = null;
            if (!empty($data['dokumen_file'])) {
                $dokumenPath = $data['dokumen_file']->store(
                    'dokumen-kepegawaian/' . $guru->id,
                    'public'
                );
            }

            // Tentukan is_aktif berdasarkan status baru
            $isAktif = ($statusBaru === 'aktif');

            // Update tenaga pendidik
            $guru->update([
                'is_aktif'            => $isAktif,
                'status_kepegawaian'  => $statusBaru,
                'tanggal_nonaktif'    => $isAktif ? null : ($data['tanggal_efektif'] ?? now()->toDateString()),
                'tanggal_keluar'      => RiwayatStatusKepegawaian::isPermanent($statusBaru)
                                            ? ($data['tanggal_efektif'] ?? now()->toDateString())
                                            : $guru->tanggal_keluar,
                'alasan_nonaktif'     => $isAktif ? null : ($data['alasan'] ?? null),
                'dinonaktifkan_oleh'  => Auth::id(),
            ]);

            // Update status user (untuk login)
            $guru->user->update([
                'status' => $isAktif ? 'aktif' : 'nonaktif',
            ]);

            // Memblokir login saja tidak cukup: guru yang sudah terpasang di PWA
            // membawa token Sanctum yang tetap sah sampai kedaluwarsa, sehingga
            // ia masih bisa membuka aplikasi dan menerima notifikasi meski sudah
            // keluar. Token & langganan push karena itu dicabut saat nonaktif.
            $dampak = ['token' => 0, 'push' => 0, 'jadwal' => 0];
            if (!$isAktif) {
                $dampak['token'] = $guru->user->tokens()->delete();
                $dampak['push']  = PushSubscription::where('user_id', $guru->user_id)->delete();
            }

            // Jadwal mengajar hanya dilepas untuk status PERMANEN. Pada cuti atau
            // nonaktif sementara jadwalnya sengaja dibiarkan utuh karena sesi yang
            // ditinggalkan ditangani mekanisme guru pengganti dan gurunya kembali.
            // Untuk yang keluar permanen, membiarkannya aktif membuat kelas yatim:
            // setiap hari tetap memunculkan sesi yang tidak mungkin terlaksana,
            // lalu tercatat tidak_terlaksana dan mengeskalasi ke pimpinan.
            if (RiwayatStatusKepegawaian::isPermanent($statusBaru)) {
                $dampak['jadwal'] = JadwalMengajar::where('tenaga_pendidik_id', $guru->id)
                    ->where('is_aktif', true)->update(['is_aktif' => false, 'updated_at' => now()]);
            }

            // Catat riwayat
            $riwayat = RiwayatStatusKepegawaian::create([
                'tenaga_pendidik_id' => $guru->id,
                'status_lama'        => $statusLama,
                'status_baru'        => $statusBaru,
                'tanggal_efektif'    => $data['tanggal_efektif'] ?? now()->toDateString(),
                'tanggal_kembali'    => $data['tanggal_kembali'] ?? null,
                'alasan'             => $data['alasan'] ?? null,
                'dokumen_pendukung'  => $dokumenPath,
                'dicatat_oleh'       => Auth::id(),
            ]);

            // Log aktivitas
            LogAktivitas::create([
                'user_id'    => Auth::id(),
                'aksi'       => 'ubah_status_kepegawaian',
                'model_type' => TenagaPendidik::class,
                'model_id'   => $guru->id,
                'data_lama'  => ['status_kepegawaian' => $statusLama, 'is_aktif' => !$isAktif],
                'data_baru'  => ['status_kepegawaian' => $statusBaru, 'is_aktif' => $isAktif],
                'keterangan' => "Status {$guru->user->name}: {$statusLama} → {$statusBaru}. Alasan: " . ($data['alasan'] ?? '-')
                    . ($dampak['jadwal'] ? " [{$dampak['jadwal']} jadwal mengajar dilepas]" : '')
                    . ($dampak['token']  ? " [{$dampak['token']} sesi PWA dicabut]" : ''),
                'ip_address' => request()->ip(),
            ]);

            // Dilekatkan agar pemanggil bisa memberi tahu admin apa saja yang ikut
            // berubah — perubahan ini tidak boleh terjadi diam-diam.
            $riwayat->dampak = $dampak;

            return $riwayat;
        });
    }

    /**
     * Aktifkan kembali guru yang sedang cuti/nonaktif sementara.
     *
     * @throws \InvalidArgumentException
     */
    public function aktifkanKembali(
        TenagaPendidik $guru,
        string $alasan = 'Kembali aktif'
    ): RiwayatStatusKepegawaian {

        $statusLama = $guru->status_kepegawaian ?? 'aktif';

        if ($statusLama === 'aktif') {
            throw new \InvalidArgumentException(
                "{$guru->user->name} sudah berstatus aktif."
            );
        }

        if (RiwayatStatusKepegawaian::isPermanent($statusLama)) {
            throw new \InvalidArgumentException(
                "Status '{$statusLama}' tidak bisa diaktifkan kembali. " .
                "Resign, pensiun, dan meninggal adalah status permanen."
            );
        }

        return $this->ubahStatus($guru, 'aktif', [
            'tanggal_efektif' => now()->toDateString(),
            'alasan'          => $alasan,
        ]);
    }

    /**
     * Proses resign — status permanen, tidak bisa aktif kembali.
     */
    public function prosesResign(TenagaPendidik $guru, array $data): RiwayatStatusKepegawaian
    {
        $data['tanggal_efektif'] ??= now()->toDateString();
        return $this->ubahStatus($guru, 'resign', $data);
    }

    /**
     * Cuti (sementara) — bisa kembali aktif.
     */
    public function prosesCuti(TenagaPendidik $guru, array $data): RiwayatStatusKepegawaian
    {
        if (empty($data['tanggal_kembali'])) {
            throw new \InvalidArgumentException('Tanggal kembali wajib diisi untuk cuti.');
        }
        return $this->ubahStatus($guru, 'cuti', $data);
    }

    /**
     * Apa saja yang masih melekat pada guru ini.
     *
     * Ditampilkan SEBELUM admin menekan konfirmasi. Menonaktifkan guru yang
     * masih memegang kelas, menjadi wali kelas, atau terjadwal piket akan
     * meninggalkan lubang di operasional pesantren — dan lubang itu paling
     * murah ditutup saat keputusannya diambil, bukan setelah ada yang mengeluh.
     */
    public function dampak(TenagaPendidik $guru): array
    {
        $jadwal = JadwalMengajar::with('kelasRel:id,nama', 'mataPelajaran:id,nama')
            ->where('tenaga_pendidik_id', $guru->id)->where('is_aktif', true)->get();

        return [
            'jadwal_jumlah' => $jadwal->count(),
            'jadwal_kelas'  => $jadwal->map(fn ($j) => $j->kelasRel?->nama)->filter()->unique()->values()->all(),
            'jadwal_mapel'  => $jadwal->map(fn ($j) => $j->mataPelajaran?->nama)->filter()->unique()->values()->all(),
            'wali_kelas'    => Kelas::where('wali_kelas_id', $guru->id)->where('is_aktif', true)->pluck('nama')->all(),
            'piket'         => PiketJadwal::where('tenaga_pendidik_id', $guru->id)->count(),
            'pengawas'      => Pengawas::where('tenaga_pendidik_id', $guru->id)->count(),
            'sesi_pwa'      => $guru->user?->tokens()->count() ?? 0,
        ];
    }

    /**
     * Mendapatkan riwayat status lengkap.
     */
    public function getRiwayat(TenagaPendidik $guru): \Illuminate\Support\Collection
    {
        return $guru->riwayatStatus()
            ->with('dicatatOleh')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'status_lama'     => RiwayatStatusKepegawaian::labelStatus($r->status_lama),
                'status_baru'     => RiwayatStatusKepegawaian::labelStatus($r->status_baru),
                'badge_lama'      => RiwayatStatusKepegawaian::badgeStatus($r->status_lama),
                'badge_baru'      => RiwayatStatusKepegawaian::badgeStatus($r->status_baru),
                'tanggal_efektif' => $r->tanggal_efektif->format('d M Y'),
                'tanggal_kembali' => $r->tanggal_kembali?->format('d M Y'),
                'alasan'          => $r->alasan,
                'dicatat_oleh'    => $r->dicatatOleh->name ?? '-',
                'created_at'      => $r->created_at->format('d M Y H:i'),
            ]);
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Validasi apakah transisi status diizinkan.
     */
    private function validasiTransisi(
        string $statusLama,
        string $statusBaru,
        TenagaPendidik $guru
    ): void {
        // Status permanen tidak bisa diubah lagi
        if (RiwayatStatusKepegawaian::isPermanent($statusLama) && $statusBaru !== $statusLama) {
            $labelLama = RiwayatStatusKepegawaian::labelStatus($statusLama);
            throw new \InvalidArgumentException(
                "Status '{$labelLama}' adalah status permanen dan tidak dapat diubah."
            );
        }

        // Tidak perlu ubah jika sama
        if ($statusLama === $statusBaru) {
            $label = RiwayatStatusKepegawaian::labelStatus($statusBaru);
            throw new \InvalidArgumentException(
                "{$guru->user->name} sudah berstatus '{$label}'."
            );
        }

        // Status valid
        $statusValid = ['aktif', 'cuti', 'cuti_sakit', 'nonaktif_sementara', 'resign', 'pensiun', 'meninggal'];
        if (!in_array($statusBaru, $statusValid)) {
            throw new \InvalidArgumentException("Status '{$statusBaru}' tidak valid.");
        }
    }
}