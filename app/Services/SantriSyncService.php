<?php

namespace App\Services;

use App\Models\Santri;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi identitas & kelas santri Smart → RamahAnak (Smart = master/override).
 * Mengirim payload UPSERT (kunci: NISN = santri.nip) via outbox 'santri_sync'.
 *
 * refId di-hash dari payload → hanya perubahan data yang benar-benar terkirim
 * (payload sama = firstOrCreate no-op). Kelas dipetakan by kode_kelas + tahun_ajaran
 * (kelas.nama = "10A", tahun_ajaran.nama = "2025/2026"). Lihat docs/PRD-Sync-Santri-RamahAnak.md.
 */
class SantriSyncService
{
    public function __construct(private OutboxService $outbox) {}

    /** L/P (Smart) → enum RamahAnak. */
    private function jenisKelamin(?string $jk): string
    {
        return $jk === 'P' ? 'Perempuan' : 'Laki-laki';
    }

    /** Tingkat (int) untuk RA: pakai kolom Smart bila ada, else derive dari kode kelas. */
    private function tingkat($tingkatSmart, ?string $kode): int
    {
        if ($tingkatSmart !== null && $tingkatSmart !== '' && is_numeric($tingkatSmart)) {
            return (int) $tingkatSmart;
        }
        $prefix = strtoupper(trim(explode(' ', (string) $kode)[0])); // "VII" dari "VII A"
        $roman = ['IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
        if (isset($roman[$prefix])) return $roman[$prefix];
        if (preg_match('/^(\d{1,2})/', $prefix, $m)) return (int) $m[1]; // "9A" → 9
        return 0;
    }

    /** Susun payload upsert untuk 1 santri (null bila santri/nip tak valid). */
    public function payloadFor(Santri $s): ?array
    {
        if (!$s->nip) return null;

        // Semua keanggotaan kelas jenis "sekolah" (riwayat utama untuk RA).
        $rows = DB::table('kelas_santri')
            ->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->leftJoin('tahun_ajaran', 'tahun_ajaran.id', '=', 'kelas_santri.tahun_ajaran_id')
            ->where('kelas_santri.santri_id', $s->id)
            ->where('kelas.jenis', 'sekolah')
            ->orderBy('kelas_santri.tanggal_masuk')
            ->get([
                'kelas.nama as kode_kelas',
                'kelas.nama_deskriptif',
                'kelas.tingkat',
                'tahun_ajaran.nama as tahun_ajaran',
                'kelas_santri.tanggal_masuk',
                'kelas_santri.tanggal_keluar',
                'kelas_santri.is_aktif',
            ]);

        $riwayat = $rows->map(fn($r) => [
            'kode_kelas'     => $r->kode_kelas,
            'nama'           => $r->nama_deskriptif,                 // nama tokoh (dikelola Smart)
            'tingkat'        => $this->tingkat($r->tingkat, $r->kode_kelas),
            'tahun_ajaran'   => $r->tahun_ajaran,
            'tanggal_masuk'  => $r->tanggal_masuk,
            'tanggal_keluar' => $r->tanggal_keluar,
            'is_active'      => (bool) $r->is_aktif,
        ])->values()->all();

        $aktif = collect($riwayat)->firstWhere('is_active', true);

        return [
            'nisn'           => (string) $s->nip,
            'nama_lengkap'   => $s->nama_lengkap,
            'nama_panggilan' => $s->nama_panggilan,
            'jenis_kelamin'  => $this->jenisKelamin($s->jenis_kelamin),
            'tempat_lahir'   => $s->tempat_lahir,
            'tanggal_lahir'  => optional($s->tanggal_lahir)->toDateString(),
            'no_whatsapp'    => $s->no_whatsapp,
            'is_aktif'       => (bool) $s->is_aktif,
            'kelas'          => $aktif ? [
                'kode_kelas'   => $aktif['kode_kelas'],
                'nama'         => $aktif['nama'],
                'tingkat'      => $aktif['tingkat'],
                'tahun_ajaran' => $aktif['tahun_ajaran'],
            ] : null,
            'riwayat_kelas'  => $riwayat,
        ];
    }

    /** Enqueue sinkron 1 santri (by id atau model). Aman dipanggil berulang. */
    public function sync(Santri|int $santri): void
    {
        $s = $santri instanceof Santri ? $santri : Santri::find($santri);
        if (!$s) return;

        $payload = $this->payloadFor($s);
        if (!$payload) return;

        // refId stabil-per-versi: berubah hanya bila data berubah.
        $refId = 'SANTRISYNC-' . $s->nip . '-' . substr(md5(json_encode($payload)), 0, 12);
        $this->outbox->enqueue('santri_sync', $payload, $refId);
    }

    /** Backfill: kirim seluruh santri (default hanya aktif). Mengembalikan jumlah yang diantre. */
    public function syncSemua(bool $hanyaAktif = true): int
    {
        $q = Santri::query()->when($hanyaAktif, fn($x) => $x->where('is_aktif', true));
        $n = 0;
        $q->orderBy('id')->chunkById(200, function ($batch) use (&$n) {
            foreach ($batch as $s) { $this->sync($s); $n++; }
        });
        return $n;
    }
}
