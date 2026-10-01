<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KATEGORI KELAS BARU: `pesantren`.
 *
 * Masalahnya nyata: kegiatan pesantren seperti **Muhadharah SMA Putra/Putri**
 * mengumpulkan santri dari kelas X, XI dan XII sekaligus — satu kelas untuk
 * seluruh santri laki-laki, satu lagi untuk seluruh santri perempuan. Kedua
 * kelas itu sebelumnya dibuat berjenis `sekolah`, sehingga satu slot dengan
 * kelas X/XI/XII: memasukkan santri ke sana justru MENGELUARKANNYA dari kelas
 * sekolahnya (lihat Kelas::SLOT — satu kelas aktif per slot).
 *
 * `pesantren` memakai slotnya sendiri, jadi keanggotaan sekolah dan pesantren
 * berjalan berdampingan. Selain slot itu, cara kerjanya SAMA DENGAN SEKOLAH:
 * dijadwalkan di Jadwal Mengajar umum, mapel bertipe `reguler`, jurnal dan
 * absensi santri biasa — tidak ada jalur khusus seperti tahfidz/tahsin.
 *
 * Migration ini sekaligus membereskan data yang sudah ada: kedua kelas
 * Muhadoroh dipindah ke jenis `pesantren`, lalu seluruh santri aktif kelas
 * X/XI/XII dimasukkan sesuai jenis kelaminnya.
 */
return new class extends Migration
{
    /** Tingkat yang termasuk SMA (kelas X, XI, XII). */
    private array $tingkatSma = ['10', '11', '12'];

    public function up(): void
    {
        DB::statement("ALTER TABLE `kelas` MODIFY COLUMN `jenis` "
            . "ENUM('sekolah','pesantren','tahfidz','tahsin') NOT NULL DEFAULT 'sekolah'");

        $tahunAjaranId = DB::table('tahun_ajaran')->where('is_aktif', true)->value('id')
            ?? DB::table('tahun_ajaran')->orderByDesc('id')->value('id');

        // ── Kelas Muhadoroh → jenis pesantren ────────────────────────────────
        // Dicari lewat nama (bukan id) supaya migration tetap benar di database
        // lain; 'uhad' menangkap ejaan Muhadoroh maupun Muhadharah.
        $muhadhoroh = DB::table('kelas')
            ->where('jenis', 'sekolah')
            ->where('nama', 'like', '%uhad%')
            ->get(['id', 'nama', 'tingkat', 'tahun_ajaran_id']);

        foreach ($muhadhoroh as $k) {
            DB::table('kelas')->where('id', $k->id)->update([
                'jenis'           => 'pesantren',
                'tingkat'         => $k->tingkat ?: 'SMA',
                'tahun_ajaran_id' => $k->tahun_ajaran_id ?: $tahunAjaranId,
                'updated_at'      => now(),
            ]);
        }

        // ── Isi anggotanya dari kelas X/XI/XII ───────────────────────────────
        $putra = $muhadhoroh->first(fn ($k) => str_contains(mb_strtolower($k->nama), 'putra'));
        $putri = $muhadhoroh->first(fn ($k) => str_contains(mb_strtolower($k->nama), 'putri'));
        if (!$putra && !$putri) return;

        $santriSma = DB::table('kelas_santri')
            ->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->join('santri', 'santri.id', '=', 'kelas_santri.santri_id')
            ->where('kelas_santri.is_aktif', true)
            ->where('kelas.jenis', 'sekolah')
            ->where('kelas.is_aktif', true)
            ->whereIn('kelas.tingkat', $this->tingkatSma)
            ->where('santri.is_aktif', true)
            ->get(['santri.id', 'santri.jenis_kelamin']);

        foreach ($santriSma as $s) {
            $tujuan = $s->jenis_kelamin === 'P' ? $putri : $putra;
            if (!$tujuan) continue;
            $this->jadikanAnggota((int) $tujuan->id, (int) $s->id, $tahunAjaranId);
        }
    }

    public function down(): void
    {
        // Tutup keanggotaan pesantren (riwayatnya tetap ada, seperti aturan
        // kelas_santri pada umumnya), lalu kembalikan jenisnya.
        $idPesantren = DB::table('kelas')->where('jenis', 'pesantren')->pluck('id');
        if ($idPesantren->isNotEmpty()) {
            DB::table('kelas_santri')->whereIn('kelas_id', $idPesantren)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false, 'tanggal_keluar' => now()->toDateString(), 'updated_at' => now()]);
            DB::table('kelas')->whereIn('id', $idPesantren)->update(['jenis' => 'sekolah', 'updated_at' => now()]);
        }

        DB::statement("ALTER TABLE `kelas` MODIFY COLUMN `jenis` "
            . "ENUM('sekolah','tahfidz','tahsin') NOT NULL DEFAULT 'sekolah'");
    }

    /**
     * Buka keanggotaan satu santri di satu kelas — meniru
     * KenaikanKelasService::buka(), tanpa menyentuh slot lain (pesantren slotnya
     * sendiri) dan tanpa memanggil service, agar migration tidak ikut berubah
     * bila service-nya berubah.
     */
    private function jadikanAnggota(int $kelasId, int $santriId, ?int $tahunAjaranId): void
    {
        $ada = DB::table('kelas_santri')
            ->where('kelas_id', $kelasId)->where('santri_id', $santriId)->first();

        if ($ada && $ada->is_aktif) return;      // sudah anggota — tidak diapa-apakan

        $payload = [
            'is_aktif'        => true,
            'tanggal_masuk'   => now()->toDateString(),
            'tanggal_keluar'  => null,
            'tahun_ajaran_id' => $tahunAjaranId,
            'keterangan'      => 'Kegiatan pesantren SMA (otomatis saat kategori pesantren dibuat)',
            'updated_at'      => now(),
        ];

        if ($ada) {
            DB::table('kelas_santri')->where('id', $ada->id)->update($payload);
        } else {
            DB::table('kelas_santri')->insert($payload + [
                'kelas_id' => $kelasId, 'santri_id' => $santriId, 'created_at' => now(),
            ]);
        }
    }
};
