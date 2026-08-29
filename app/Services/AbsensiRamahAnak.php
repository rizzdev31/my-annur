<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Pengirim jawaban absensi (Controlling & Smart Education) → RamahAnak.
 *
 * Kode RamahAnak per status diambil dari config('controlling.absensi_kode') sehingga
 * FLEKSIBEL diubah lewat .env (telat/alpha bisa kode sama/berbeda, atau dimatikan).
 * Dikirim sebagai laporan PELANGGARAN (jenis outbox = 'absensi') agar kode bebas
 * ditentukan pengirim. Hadir tidak pernah dikirim. Idempotent via ref_id.
 */
class AbsensiRamahAnak
{
    public function __construct(private OutboxService $outbox) {}

    /**
     * Kirim satu jawaban absensi.
     *
     * @param  string      $nip       NISN/NIP santri (kunci sinkron).
     * @param  string      $status    hadir|telat|alpha (hadir & status tak terpetakan dilewati).
     * @param  string      $tanggal   YYYY-MM-DD.
     * @param  string      $kegiatan  Label kegiatan (mapel / kegiatan controlling).
     * @param  string|null $waktu     HH:MM (opsional, untuk catatan).
     * @param  string|null $actor     Jejak pengirim.
     * @param  string      $prefix    Prefix ref_id (mis. 'CTRL' / 'EDU').
     * @return bool true bila dimasukkan ke outbox; false bila dilewati (hadir / kode kosong / tanpa NIP).
     */
    public function kirim(
        string $nip,
        string $status,
        string $tanggal,
        string $kegiatan,
        ?string $waktu = null,
        ?string $actor = null,
        string $prefix = 'ABS',
    ): bool {
        if ($nip === '' || $status === 'hadir') return false;

        $kode = trim((string) config("controlling.absensi_kode.{$status}", ''));
        if ($kode === '') return false; // status ini sengaja tidak dikirim

        $slug    = Str::slug($kegiatan) ?: 'kegiatan';
        $refId   = "{$prefix}-{$tanggal}-{$nip}-{$slug}-{$status}";
        $catatan = "Absensi {$status}: {$kegiatan}" . ($waktu ? " ({$waktu})" : '');

        $this->outbox->enqueue('absensi', [
            'nisn_pelaku' => $nip,
            'kode'        => $kode,
            'tanggal'     => $tanggal,
            'catatan'     => $catatan,
        ], $refId, $actor);

        return true;
    }
}
