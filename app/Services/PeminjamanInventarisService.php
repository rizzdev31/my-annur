<?php

namespace App\Services;

use App\Models\Inventaris;
use App\Models\PeminjamanInventaris;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika peminjaman inventaris — anti double-booking & anti pengajuan kembar.
 *
 * Aturan slot:
 *   • Dua peminjaman BENTROK bila item sama, tanggal sama, dan rentang jamnya
 *     beririsan: (jam_mulai < lain.jam_selesai) DAN (jam_selesai > lain.jam_mulai).
 *   • Yang mengunci kapasitas = peminjaman berstatus 'disetujui'.
 *   • Σ jumlah peminjaman disetujui yang bentrok + jumlah diminta ≤ jumlah_total.
 *     (ruang/bangunan: jumlah_total=1 → otomatis hanya 1 pemakai per slot)
 *
 * Anti-duplikat: cegah pengajuan identik (guru + item + tanggal + jam sama) yang
 * masih hidup (diajukan/disetujui). Semua cek dibungkus transaksi + lockForUpdate
 * agar aman dari race condition (dua request bersamaan).
 */
class PeminjamanInventarisService
{
    /** Ajukan peminjaman. Auto-disetujui bila item.perlu_persetujuan = false. */
    public function ajukan(int $tpId, array $data): PeminjamanInventaris
    {
        $mulai  = $this->jam($data['jam_mulai']);
        $selesai = $this->jam($data['jam_selesai']);
        if ($selesai <= $mulai) {
            throw new \DomainException('Jam selesai harus lebih besar dari jam mulai.');
        }

        return DB::transaction(function () use ($tpId, $data, $mulai, $selesai) {
            /** @var Inventaris $inv */
            $inv = Inventaris::lockForUpdate()->find($data['inventaris_id']);
            if (!$inv || !$inv->is_aktif) {
                throw new \DomainException('Inventaris tidak ditemukan / tidak aktif.');
            }

            $tanggal = Carbon::parse($data['tanggal'])->toDateString();
            $jumlah  = max(1, (int) ($data['jumlah'] ?? 1));

            $this->cegahKembar($tpId, $inv->id, $tanggal, $mulai, $selesai);

            // Auto-disetujui hanya bila tak perlu persetujuan — tetap cek kapasitas.
            $autoSetuju = !$inv->perlu_persetujuan;
            if ($autoSetuju) {
                $this->pastikanMuat($inv, $tanggal, $mulai, $selesai, $jumlah, null);
            }

            return PeminjamanInventaris::create([
                'inventaris_id'      => $inv->id,
                'tenaga_pendidik_id' => $tpId,
                'jumlah'             => $jumlah,
                'keperluan'          => $data['keperluan'],
                'tanggal'            => $tanggal,
                'jam_mulai'          => $mulai,
                'jam_selesai'        => $selesai,
                'status'             => $autoSetuju ? 'disetujui' : 'diajukan',
                'disetujui_oleh'     => null,
                'diputuskan_pada'    => $autoSetuju ? now() : null,
            ]);
        });
    }

    /** Superadmin menyetujui — cek ulang kapasitas (anti bentrok saat approve). */
    public function setujui(int $peminjamanId, int $userId, ?string $catatan = null): PeminjamanInventaris
    {
        return DB::transaction(function () use ($peminjamanId, $userId, $catatan) {
            /** @var PeminjamanInventaris $p */
            $p = PeminjamanInventaris::lockForUpdate()->findOrFail($peminjamanId);
            if ($p->status !== 'diajukan') {
                throw new \DomainException('Pengajuan ini sudah diproses (status: ' . $p->status . ').');
            }
            $inv = Inventaris::lockForUpdate()->findOrFail($p->inventaris_id);

            $this->pastikanMuat(
                $inv,
                $p->tanggal->toDateString(),
                $this->jam($p->jam_mulai),
                $this->jam($p->jam_selesai),
                (int) $p->jumlah,
                $p->id
            );

            $p->update([
                'status'          => 'disetujui',
                'disetujui_oleh'  => $userId,
                'catatan_admin'   => $catatan,
                'diputuskan_pada' => now(),
            ]);
            return $p;
        });
    }

    public function tolak(int $peminjamanId, int $userId, string $alasan): PeminjamanInventaris
    {
        $p = PeminjamanInventaris::findOrFail($peminjamanId);
        if ($p->status !== 'diajukan') {
            throw new \DomainException('Pengajuan ini sudah diproses.');
        }
        $p->update([
            'status' => 'ditolak', 'disetujui_oleh' => $userId,
            'catatan_admin' => $alasan, 'diputuskan_pada' => now(),
        ]);
        return $p;
    }

    /** Batalkan oleh guru (hanya selagi diajukan) atau admin. */
    public function batal(int $peminjamanId): PeminjamanInventaris
    {
        $p = PeminjamanInventaris::findOrFail($peminjamanId);
        if (!in_array($p->status, ['diajukan', 'disetujui'], true)) {
            throw new \DomainException('Tidak bisa dibatalkan pada status ' . $p->status . '.');
        }
        $p->update(['status' => 'dibatalkan']);
        return $p;
    }

    public function selesai(int $peminjamanId): PeminjamanInventaris
    {
        $p = PeminjamanInventaris::findOrFail($peminjamanId);
        if ($p->status !== 'disetujui') {
            throw new \DomainException('Hanya peminjaman disetujui yang bisa diselesaikan.');
        }
        $p->update(['status' => 'selesai']);
        return $p;
    }

    /**
     * Tandai 'selesai' otomatis untuk peminjaman 'disetujui' yang jam pemakaiannya
     * sudah lewat (tanggal kemarin, atau hari ini & jam_selesai ≤ sekarang).
     * Dijadwalkan berkala. Return jumlah baris diperbarui.
     */
    public function autoSelesai(): int
    {
        $now    = TimezoneHelper::now();
        $today  = $now->toDateString();
        $jamNow = $now->format('H:i:s');

        return PeminjamanInventaris::where('status', 'disetujui')
            ->where(function ($q) use ($today, $jamNow) {
                $q->whereDate('tanggal', '<', $today)
                  ->orWhere(fn($q2) => $q2->whereDate('tanggal', $today)
                                          ->where('jam_selesai', '<=', $jamNow));
            })
            ->update(['status' => 'selesai']);
    }

    /**
     * Sisa kapasitas item pada slot waktu tertentu.
     * = jumlah_total − Σ jumlah peminjaman 'disetujui' yang bentrok (kecuali $excludeId).
     */
    public function sisaKapasitas(
        int $inventarisId, string $tanggal, string $mulai, string $selesai, ?int $excludeId = null
    ): int {
        $inv = Inventaris::find($inventarisId);
        if (!$inv) return 0;
        $terpakai = $this->bentrokQuery($inventarisId, $tanggal, $this->jam($mulai), $this->jam($selesai), $excludeId)
            ->sum('jumlah');
        return max(0, (int) $inv->jumlah_total - (int) $terpakai);
    }

    // ─── Internal ───────────────────────────────────────────────────────────────

    private function pastikanMuat(
        Inventaris $inv, string $tanggal, string $mulai, string $selesai, int $jumlah, ?int $excludeId
    ): void {
        $terpakai = $this->bentrokQuery($inv->id, $tanggal, $mulai, $selesai, $excludeId)->sum('jumlah');
        $sisa = (int) $inv->jumlah_total - (int) $terpakai;
        if ($jumlah > $sisa) {
            throw new \DomainException(
                "Jadwal bentrok: \"{$inv->nama}\" pada {$tanggal} "
                . substr($mulai, 0, 5) . '–' . substr($selesai, 0, 5)
                . " sudah dipakai (sisa kapasitas {$sisa})."
            );
        }
    }

    private function cegahKembar(int $tpId, int $invId, string $tanggal, string $mulai, string $selesai): void
    {
        $ada = PeminjamanInventaris::where('tenaga_pendidik_id', $tpId)
            ->where('inventaris_id', $invId)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_mulai', $mulai)
            ->where('jam_selesai', $selesai)
            ->whereIn('status', PeminjamanInventaris::STATUS_AKTIF)
            ->exists();
        if ($ada) {
            throw new \DomainException('Anda sudah mengajukan peminjaman untuk item & slot waktu yang sama.');
        }
    }

    /** Query peminjaman 'disetujui' yang BENTROK dengan slot [mulai,selesai] di tanggal itu. */
    private function bentrokQuery(int $invId, string $tanggal, string $mulai, string $selesai, ?int $excludeId)
    {
        return PeminjamanInventaris::where('inventaris_id', $invId)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('status', PeminjamanInventaris::STATUS_MENGUNCI)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            // irisan: mulai < lain.selesai  DAN  selesai > lain.mulai
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai);
    }

    private function jam(string $hhmm): string
    {
        // Normalisasi ke H:i:s agar perbandingan TIME konsisten.
        return Carbon::createFromFormat('H:i', substr($hhmm, 0, 5))->format('H:i:s');
    }
}
