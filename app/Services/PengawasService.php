<?php

namespace App\Services;

use App\Models\Pengawas;
use App\Models\TenagaPendidik;
use Illuminate\Support\Collection;

/**
 * GERBANG TUNGGAL izin monitoring pimpinan.
 *
 * ATURAN WAJIB (jangan dilanggar di controller mana pun):
 *  - Selalu panggil boleh() sebelum menyajikan data modul apa pun.
 *  - Selalu saring target dengan bolehLihatGuru()/idGuruDiawasi() — JANGAN percaya
 *    id guru yang dikirim klien.
 *  - Pengawas TIDAK boleh memantau/menyetujui dirinya sendiri (konflik kepentingan).
 */
class PengawasService
{
    /** Baris pengawas AKTIF milik seorang tendik (null bila bukan pengawas). */
    public function untuk(int $tenagaPendidikId): ?Pengawas
    {
        return Pengawas::aktif()->where('tenaga_pendidik_id', $tenagaPendidikId)->first();
    }

    /** Apakah tendik ini pengawas aktif untuk modul tertentu. */
    public function boleh(int $tenagaPendidikId, string $modul): bool
    {
        $p = $this->untuk($tenagaPendidikId);
        return $p !== null && in_array($modul, (array) ($p->modul ?? []), true);
    }

    /** Boleh menyetujui izin (final) — tetap dilarang untuk diri sendiri. */
    public function bolehSetujuiIzin(int $tenagaPendidikId): bool
    {
        $p = $this->untuk($tenagaPendidikId);
        return $p !== null && $p->boleh_setujui_izin
            && in_array('perizinan', (array) ($p->modul ?? []), true);
    }

    /**
     * ID guru yang boleh dipantau. Selalu MENGECUALIKAN diri sendiri.
     * @return int[]
     */
    public function idGuruDiawasi(int $tenagaPendidikId): array
    {
        $p = $this->untuk($tenagaPendidikId);
        if (!$p) return [];

        $q = TenagaPendidik::where('is_aktif', true)->where('id', '!=', $tenagaPendidikId);
        if ($p->cakupan === 'pilih') {
            $ids = $p->guruDiawasi()->pluck('tenaga_pendidik.id')->all();
            if (empty($ids)) return [];
            $q->whereIn('id', $ids);
        }
        return $q->pluck('id')->all();
    }

    /** Guru yang diawasi (model + user), untuk daftar di UI. */
    public function guruDiawasi(int $tenagaPendidikId): Collection
    {
        $ids = $this->idGuruDiawasi($tenagaPendidikId);
        if (empty($ids)) return collect();

        return TenagaPendidik::with(['user:id,name', 'jabatan:id,nama_jabatan'])
            ->whereIn('id', $ids)->get()
            ->sortBy(fn($g) => $g->user?->name ?? '')->values();
    }

    /** Penjagaan per-target: bolehkah pengawas melihat guru ini. */
    public function bolehLihatGuru(int $tenagaPendidikId, int $targetId): bool
    {
        return in_array($targetId, $this->idGuruDiawasi($tenagaPendidikId), true);
    }

    /** Ringkasan hak untuk klien (PWA) — dipakai menyembunyikan menu. */
    public function ringkasan(int $tenagaPendidikId): array
    {
        $p = $this->untuk($tenagaPendidikId);
        if (!$p) {
            return ['is_pengawas' => false, 'modul' => [], 'boleh_setujui_izin' => false, 'jumlah_guru' => 0];
        }
        return [
            'is_pengawas'        => true,
            'modul'              => array_values((array) ($p->modul ?? [])),
            'modul_label'        => Pengawas::MODUL,
            'cakupan'            => $p->cakupan,
            'boleh_setujui_izin' => (bool) $p->boleh_setujui_izin,
            'jumlah_guru'        => count($this->idGuruDiawasi($tenagaPendidikId)),
        ];
    }
}
