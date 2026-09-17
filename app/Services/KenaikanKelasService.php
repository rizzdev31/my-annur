<?php

namespace App\Services;

use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\Santri;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keanggotaan kelas santri — SATU pintu untuk semua perubahan: panel centang
 * "Atur Santri", form edit santri, dan naik kelas massal.
 *
 * Aturan:
 *  1. RIWAYAT DIJAGA. kelas_santri tidak pernah dihapus; keanggotaan lama ditutup
 *     (is_aktif=false + tanggal_keluar), keanggotaan baru dibuka.
 *  2. SATU KELAS AKTIF PER SLOT (Kelas::SLOT): sekolah = 1, program Quran
 *     (tahfidz ∪ tahsin) = 1. Memasukkan santri ke kelas lain di slot yang sama
 *     otomatis MEMINDAHKAN-nya.
 *  3. Setelah berubah: level tahsin menyesuaikan kelas, data disinkron ke
 *     RamahAnak (outbox, asinkron), dan pengampu kelas tahfidz/tahsin dikabari.
 *
 * Dulu form edit santri memakai sync() yang menghapus riwayat dan membolehkan dua
 * kelas sejenis, sementara naik kelas menjaga riwayat — dua jalur, dua aturan.
 */
class KenaikanKelasService
{
    /** Jenis kelas yang pengampunya dikabari saat santri berubah. */
    public const JENIS_DIKABARI = ['tahfidz', 'tahsin'];

    /** Perubahan per kelas selama satu operasi: [kelas_id => ['masuk'=>[nama], 'keluar'=>[nama]]]. */
    private array $perubahan = [];
    private array $santriBerubah = [];

    public function __construct(private SantriSyncService $sync) {}

    // ══════════════════════════════════════════════════════════════════════
    // API publik
    // ══════════════════════════════════════════════════════════════════════

    /** Pindahkan 1 santri ke kelas tujuan (menutup kelas aktif di slot yang sama). */
    public function pindahKelas(int $santriId, Kelas $tujuan, ?string $tanggal = null, ?string $keterangan = null): bool
    {
        $this->mulai();
        $berubah = DB::transaction(fn () => $this->buka($santriId, $tujuan, $tanggal ?? $this->hariIni(), $keterangan) !== false);
        $this->selesai();
        return $berubah;
    }

    /**
     * Panel centang: jadikan $santriIds sebagai anggota AKTIF kelas ini.
     *   - dicentang & belum anggota → masuk (dipindah dari kelas sejenisnya bila ada)
     *   - anggota & tidak dicentang → keluar (ditutup, tanpa kelas pengganti)
     *
     * @return array{masuk: array, keluar: array}
     */
    public function aturAnggota(Kelas $kelas, array $santriIds, ?string $tanggal = null): array
    {
        $tanggal ??= $this->hariIni();
        $target  = Santri::aktif()->whereIn('id', array_map('intval', $santriIds))->pluck('id');
        $aktif   = DB::table('kelas_santri')->where('kelas_id', $kelas->id)->where('is_aktif', true)->pluck('santri_id');

        $masukIds  = $target->diff($aktif)->values();
        $keluarIds = $aktif->diff($target)->values();

        $this->mulai();
        $hasil = DB::transaction(function () use ($kelas, $masukIds, $keluarIds, $tanggal) {
            $masuk = [];
            foreach ($masukIds as $sid) {
                $dari = $this->buka((int) $sid, $kelas, $tanggal, 'Diatur lewat panel kelas');
                $masuk[] = ['santri_id' => (int) $sid, 'dari' => $dari ?: null];
            }
            $keluar = [];
            foreach ($keluarIds as $sid) {
                $this->tutup((int) $sid, [$kelas->id], $tanggal);
                $keluar[] = ['santri_id' => (int) $sid];
            }
            return ['masuk' => $masuk, 'keluar' => $keluar];
        });
        $this->selesai();

        return $hasil;
    }

    /**
     * Form edit santri: jadikan $kelasIds sebagai kelas aktif santri ini.
     * Maksimal satu kelas per slot; slot yang tidak dipilih ditutup.
     *
     * @throws \DomainException bila dua kelas dari slot yang sama dipilih
     */
    public function aturKelasSantri(Santri $santri, array $kelasIds, ?string $tanggal = null): void
    {
        $tanggal ??= $this->hariIni();
        $kelas = $this->pastikanSatuPerSlot($kelasIds);

        $this->mulai();
        DB::transaction(function () use ($santri, $kelas, $tanggal) {
            foreach ($kelas as $k) {
                $this->buka($santri->id, $k, $tanggal, 'Diatur lewat data santri');
            }
            // Slot yang tidak dipilih sama sekali → keanggotaannya ditutup.
            $jenisDipilih = $kelas->flatMap(fn ($k) => $k->jenisSeslot())->unique()->all();
            $sisa = DB::table('kelas_santri')->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
                ->where('kelas_santri.santri_id', $santri->id)->where('kelas_santri.is_aktif', true)
                ->whereNotIn('kelas.jenis', $jenisDipilih ?: ['__kosong__'])
                ->pluck('kelas_santri.kelas_id')->all();
            if ($sisa) $this->tutup($santri->id, $sisa, $tanggal);
        });
        $this->selesai();
    }

    /**
     * Tolak pilihan dua kelas dari slot yang sama. Dipanggil sebelum menyimpan
     * apa pun, agar santri baru tidak terlanjur dibuat.
     * @return Collection<Kelas>
     * @throws \DomainException
     */
    public function pastikanSatuPerSlot(array $kelasIds): Collection
    {
        $kelas = Kelas::whereIn('id', array_map('intval', $kelasIds))->get();

        foreach ($kelas->groupBy(fn ($k) => implode('+', $k->jenisSeslot())) as $slot => $daftar) {
            if ($daftar->count() > 1) {
                throw new \DomainException($slot === 'sekolah'
                    ? 'Santri hanya boleh punya satu kelas sekolah (dipilih: ' . $daftar->pluck('nama')->implode(', ') . ').'
                    : 'Santri hanya boleh punya satu kelas tahfidz ATAU tahsin (dipilih: ' . $daftar->pluck('nama')->implode(', ') . ').');
            }
        }
        return $kelas;
    }

    /**
     * Naik/pindah kelas MASSAL dari kelas sumber → tujuan (slot yang sama, sehingga
     * Persiapan Tahfidz → Tahfidz juga bisa).
     * @return array{dipindah:int,dilewati:int}
     */
    public function naikKelasMassal(Kelas $sumber, Kelas $tujuan, array $kecuali = [], ?string $tanggal = null, ?string $keterangan = null): array
    {
        if ($sumber->id === $tujuan->id) {
            throw new \DomainException('Kelas tujuan tidak boleh sama dengan kelas sumber.');
        }
        if ($sumber->jenisSeslot() !== $tujuan->jenisSeslot()) {
            throw new \DomainException('Kelas tujuan harus satu kelompok (sekolah ↔ sekolah, tahfidz/tahsin ↔ tahfidz/tahsin).');
        }

        $santriAktif = DB::table('kelas_santri')
            ->where('kelas_id', $sumber->id)->where('is_aktif', true)->pluck('santri_id');

        $dipindah = 0; $dilewati = 0;
        $this->mulai();
        DB::transaction(function () use ($santriAktif, $kecuali, $tujuan, $tanggal, $keterangan, $sumber, &$dipindah, &$dilewati) {
            foreach ($santriAktif as $sid) {
                if (in_array($sid, $kecuali)) { $dilewati++; continue; }
                $this->buka((int) $sid, $tujuan, $tanggal ?? $this->hariIni(), $keterangan ?? "Naik kelas dari {$sumber->nama}");
                $dipindah++;
            }
        });
        $this->selesai();

        return ['dipindah' => $dipindah, 'dilewati' => $dilewati];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Inti
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Buka keanggotaan santri di $tujuan, menutup kelas aktif lain di slot yang sama.
     * @return string|false|null  nama kelas asal bila dipindah; null bila sebelumnya
     *                            tanpa kelas di slot ini; false bila sudah anggota (no-op)
     */
    private function buka(int $santriId, Kelas $tujuan, string $tanggal, ?string $keterangan): string|false|null
    {
        $sudah = DB::table('kelas_santri')->where('santri_id', $santriId)
            ->where('kelas_id', $tujuan->id)->where('is_aktif', true)->exists();
        if ($sudah) return false;

        $lama = DB::table('kelas_santri')->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->where('kelas_santri.santri_id', $santriId)->where('kelas_santri.is_aktif', true)
            ->whereIn('kelas.jenis', $tujuan->jenisSeslot())
            ->get(['kelas_santri.kelas_id', 'kelas.nama']);
        if ($lama->isNotEmpty()) {
            $this->tutup($santriId, $lama->pluck('kelas_id')->all(), $tanggal);
        }

        $payload = [
            'is_aktif'        => true,
            'tanggal_masuk'   => $tanggal,
            'tanggal_keluar'  => null,
            'tahun_ajaran_id' => $tujuan->tahun_ajaran_id,
            'keterangan'      => $keterangan,
            'updated_at'      => now(),
        ];
        // Satu baris per pasangan (unique kelas_id+santri_id): kembali ke kelas yang
        // pernah diikuti mengaktifkan ulang barisnya.
        $ada = DB::table('kelas_santri')->where('santri_id', $santriId)->where('kelas_id', $tujuan->id)->first();
        if ($ada) {
            DB::table('kelas_santri')->where('id', $ada->id)->update($payload);
        } else {
            DB::table('kelas_santri')->insert($payload + [
                'kelas_id' => $tujuan->id, 'santri_id' => $santriId, 'created_at' => now(),
            ]);
        }

        $this->catat($tujuan->id, $santriId, 'masuk');
        return $lama->isNotEmpty() ? $lama->pluck('nama')->implode(', ') : null;
    }

    /** Tutup keanggotaan aktif santri di kelas-kelas tsb (riwayat tetap tersimpan). */
    private function tutup(int $santriId, array $kelasIds, string $tanggal): void
    {
        $n = DB::table('kelas_santri')->where('santri_id', $santriId)
            ->whereIn('kelas_id', $kelasIds)->where('is_aktif', true)
            ->update(['is_aktif' => false, 'tanggal_keluar' => $tanggal, 'updated_at' => now()]);
        if ($n) {
            foreach ($kelasIds as $kid) $this->catat((int) $kid, $santriId, 'keluar');
        }
    }

    private function catat(int $kelasId, int $santriId, string $arah): void
    {
        $this->perubahan[$kelasId][$arah][] = $santriId;
        $this->santriBerubah[$santriId] = true;
    }

    private function mulai(): void
    {
        $this->perubahan = [];
        $this->santriBerubah = [];
    }

    /** Efek lanjutan SETELAH transaksi selesai. */
    private function selesai(): void
    {
        foreach (array_keys($this->santriBerubah) as $sid) {
            $s = Santri::find($sid);
            if (!$s) continue;
            $s->selaraskanLevelTahsin();   // materi tahsin mengikuti kelas
            $this->sync->sync($s);         // RamahAnak (outbox)
        }
        $this->kabariPengampu();
        $this->mulai();
    }

    /**
     * Kabari pengampu kelas tahfidz/tahsin yang anggotanya berubah — termasuk
     * kelas ASAL santri yang dipindah, karena pengampu di sana kehilangan santri.
     */
    private function kabariPengampu(): void
    {
        if (!$this->perubahan) return;

        $kelas = Kelas::whereIn('id', array_keys($this->perubahan))
            ->whereIn('jenis', self::JENIS_DIKABARI)->get()->keyBy('id');
        if ($kelas->isEmpty()) return;

        $nama = Santri::whereIn('id', collect($this->perubahan)->flatten()->unique())->pluck('nama_lengkap', 'id');
        $ringkas = function (array $ids) use ($nama): string {
            $n = collect($ids)->unique()->map(fn ($i) => $nama[$i] ?? '—');
            return $n->take(5)->implode(', ') . ($n->count() > 5 ? ' +' . ($n->count() - 5) . ' lainnya' : '');
        };

        foreach ($kelas as $k) {
            $p = $this->perubahan[$k->id];
            $bagian = collect([
                !empty($p['masuk'])  ? count(array_unique($p['masuk'])) . ' masuk: ' . $ringkas($p['masuk']) : null,
                !empty($p['keluar']) ? count(array_unique($p['keluar'])) . ' keluar: ' . $ringkas($p['keluar']) : null,
            ])->filter()->implode(' · ');

            $pengampu = JadwalMengajar::where('kelas_id', $k->id)->where('is_aktif', true)
                ->with('tenagaPendidik.user')->get()
                ->map(fn ($j) => $j->tenagaPendidik?->user)->filter()->unique('id')->values();

            foreach ($pengampu as $u) {
                NotifikasiService::event('kelas.anggota_berubah', [
                    'judul' => "Santri {$k->nama} diperbarui",
                    'pesan' => $bagian . '.',
                    'tipe'  => 'pengumuman',
                    'data'  => ['route' => '/' . $k->jenis],
                    'dedup' => 'anggota-' . $k->id . '-' . md5(json_encode($p)),
                ], [$u]);
            }
        }
    }

    private function hariIni(): string
    {
        return TimezoneHelper::today()->toDateString();
    }
}
