<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use Illuminate\Support\Facades\Log;

/**
 * Hubungkan absensi Smart Education (KBM/Tahfidz/Tahsin) → RamahAnak.
 * Mengirim santri berstatus TELAT & ALPHA pada satu sesi (Hadir tidak dikirim).
 * Kode RamahAnak per status diatur di config('controlling.absensi_kode') — fleksibel.
 *
 * Batch per sesi mengajar (1 sesi = banyak baris). Idempotent via ref_id (di AbsensiRamahAnak).
 */
class EducationTelatSync
{
    public function __construct(private AbsensiRamahAnak $absensi) {}

    /**
     * Kirim semua santri TELAT & ALPHA pada satu sesi.
     *
     * @param  AbsensiMengajar $am       Sesi mengajar (sumber tanggal).
     * @param  string          $kegiatan Label kegiatan (nama mapel / "Tahfidz").
     * @param  string|null     $actor    Jejak pengirim (nama + NIP guru).
     * @return int Jumlah baris yang dimasukkan ke outbox.
     */
    public function pushSesi(AbsensiMengajar $am, string $kegiatan, ?string $actor = null): int
    {
        try {
            $tanggal = $am->tanggal instanceof \Carbon\Carbon
                ? $am->tanggal->toDateString()
                : (string) $am->tanggal;

            $rows = AbsensiSantri::with('santri:id,nip,nama_lengkap')
                ->where('absensi_mengajar_id', $am->id)
                ->whereIn('status', ['telat', 'alpha'])
                ->get();

            $count = 0;
            foreach ($rows as $r) {
                $nip = $r->santri?->nip;
                if (!$nip) continue; // tanpa NIP tak bisa map ke RamahAnak → lewati

                $terkirim = $this->absensi->kirim($nip, $r->status, $tanggal, $kegiatan, null, $actor, 'EDU');
                if ($terkirim) $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            // Jangan pernah menggagalkan proses absen guru karena sinkronisasi.
            Log::warning('[EducationTelatSync] gagal push sesi #' . $am->id . ': ' . $e->getMessage());
            return 0;
        }
    }
}
