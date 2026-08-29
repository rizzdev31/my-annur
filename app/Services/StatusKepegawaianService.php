<?php

namespace App\Services;

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
                'keterangan' => "Status {$guru->user->name}: {$statusLama} → {$statusBaru}. Alasan: " . ($data['alasan'] ?? '-'),
                'ip_address' => request()->ip(),
            ]);

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