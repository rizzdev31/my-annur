<?php

namespace App\Services;

use App\Models\TenagaPendidik;

/**
 * Sinkronisasi identitas guru (tenaga_pendidik) Smart → RamahAnak (Smart = master).
 * Upsert by NIP (= tenaga_pendidik.nip ⇄ RA tenaga_pendidik_profiles.nip).
 * Smart HANYA mengelola role 'tenaga_pendidik'; akun guru_bk = milik RA (tak disentuh).
 * refId di-hash dari payload → hanya perubahan yang terkirim. Lihat PRD-Sync-Santri-RamahAnak (analog).
 */
class GuruSyncService
{
    public function __construct(private OutboxService $outbox) {}

    private function jenisKelamin(?string $jk): string
    {
        return $jk === 'P' ? 'Perempuan' : 'Laki-laki';
    }

    /** Susun payload upsert untuk 1 guru (null bila tak valid / tanpa NIP). */
    public function payloadFor(TenagaPendidik $g): ?array
    {
        if (!$g->nip) return null;
        $g->loadMissing(['user', 'jabatan']);

        return [
            'nip'            => (string) $g->nip,
            'nama_lengkap'   => $g->user?->name,
            'nama_panggilan' => null, // Smart tak menyimpan; biarkan RA yang ada
            'jenis_kelamin'  => $this->jenisKelamin($g->jenis_kelamin),
            'tempat_lahir'   => $g->tempat_lahir,
            'tanggal_lahir'  => optional($g->tanggal_lahir)->toDateString(),
            'no_whatsapp'    => $g->no_hp,
            'jabatan'        => $g->jabatan?->nama_jabatan,
            'foto'           => $g->user?->foto,
            'is_aktif'       => (bool) $g->is_aktif,
        ];
    }

    /** Enqueue sinkron 1 guru (by id atau model). */
    public function sync(TenagaPendidik|int $guru): void
    {
        $g = $guru instanceof TenagaPendidik ? $guru : TenagaPendidik::find($guru);
        if (!$g) return;

        $payload = $this->payloadFor($g);
        if (!$payload) return;

        $refId = 'GURUSYNC-' . $g->nip . '-' . substr(md5(json_encode($payload)), 0, 12);
        $this->outbox->enqueue('guru_sync', $payload, $refId);
    }

    /** Backfill: kirim seluruh guru (default hanya aktif). */
    public function syncSemua(bool $hanyaAktif = true): int
    {
        $q = TenagaPendidik::query()->when($hanyaAktif, fn($x) => $x->where('is_aktif', true));
        $n = 0;
        $q->with(['user', 'jabatan'])->orderBy('id')->chunkById(200, function ($batch) use (&$n) {
            foreach ($batch as $g) { $this->sync($g); $n++; }
        });
        return $n;
    }
}
