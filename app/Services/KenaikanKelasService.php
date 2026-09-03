<?php

namespace App\Services;

use App\Models\Kelas;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan & kenaikan kelas santri — MENJAGA HISTORI (tidak menghapus data).
 * kelas_santri = riwayat: baris lama ditutup (is_aktif=false + tanggal_keluar), baris baru dibuka.
 * Per JENIS kelas (sekolah/tahfidz/tahsin bisa aktif bersamaan) — hanya jenis yang sama yang ditutup.
 */
class KenaikanKelasService
{
    public function __construct(private SantriSyncService $sync) {}

    /**
     * Pindahkan 1 santri ke kelas tujuan. Menutup keanggotaan aktif berjenis SAMA,
     * lalu membuka keanggotaan baru. Idempoten (kalau sudah aktif di tujuan → no-op).
     * Setelah pindah, identitas+kelas santri disinkron ke RamahAnak (Smart = master).
     */
    public function pindahKelas(int $santriId, Kelas $tujuan, ?string $tanggal = null, ?string $keterangan = null): bool
    {
        $tanggal ??= now()->toDateString();

        $berubah = DB::transaction(function () use ($santriId, $tujuan, $tanggal, $keterangan) {
            // Sudah aktif di kelas tujuan → tidak perlu apa-apa.
            $sudahDiTujuan = DB::table('kelas_santri')
                ->where('santri_id', $santriId)->where('kelas_id', $tujuan->id)->where('is_aktif', true)->exists();
            if ($sudahDiTujuan) return false;

            // Tutup keanggotaan AKTIF berjenis sama (mis. sekolah → hanya kelas sekolah yang ditutup).
            $aktifSameJenis = DB::table('kelas_santri')
                ->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
                ->where('kelas_santri.santri_id', $santriId)
                ->where('kelas_santri.is_aktif', true)
                ->where('kelas.jenis', $tujuan->jenis)
                ->pluck('kelas_santri.id');
            if ($aktifSameJenis->isNotEmpty()) {
                DB::table('kelas_santri')->whereIn('id', $aktifSameJenis)
                    ->update(['is_aktif' => false, 'tanggal_keluar' => $tanggal, 'updated_at' => now()]);
            }

            // Buka keanggotaan baru (reaktivasi bila baris untuk kelas ini sudah pernah ada).
            $adaBaris = DB::table('kelas_santri')->where('santri_id', $santriId)->where('kelas_id', $tujuan->id)->first();
            $payload = [
                'is_aktif'        => true,
                'tanggal_masuk'   => $tanggal,
                'tanggal_keluar'  => null,
                'tahun_ajaran_id' => $tujuan->tahun_ajaran_id,
                'keterangan'      => $keterangan,
                'updated_at'      => now(),
            ];
            if ($adaBaris) {
                DB::table('kelas_santri')->where('id', $adaBaris->id)->update($payload);
            } else {
                DB::table('kelas_santri')->insert($payload + [
                    'kelas_id' => $tujuan->id, 'santri_id' => $santriId, 'created_at' => now(),
                ]);
            }
            return true;
        });

        // Sinkron ke RamahAnak SETELAH commit (kelas terbaru sudah tersimpan).
        if ($berubah) {
            // Materi tahsin mengikuti kelas: kelas tahsin → set tahsin_level = level kelas.
            \App\Models\Santri::find($santriId)?->selaraskanLevelTahsin();
            $this->sync->sync($santriId);
        }
        return $berubah;
    }

    /**
     * Naik/pindah kelas MASSAL dari kelas sumber → tujuan.
     * @param array $kecuali  daftar santri_id yang DIKECUALIKAN (mis. tinggal kelas / mutasi).
     * @return array{dipindah:int,dilewati:int}
     */
    public function naikKelasMassal(Kelas $sumber, Kelas $tujuan, array $kecuali = [], ?string $tanggal = null, ?string $keterangan = null): array
    {
        if ($sumber->id === $tujuan->id) {
            throw new \DomainException('Kelas tujuan tidak boleh sama dengan kelas sumber.');
        }
        if ($sumber->jenis !== $tujuan->jenis) {
            throw new \DomainException('Kelas tujuan harus sejenis (sama-sama '.$sumber->jenis.').');
        }

        // Santri yang AKTIF di kelas sumber.
        $santriAktif = DB::table('kelas_santri')
            ->where('kelas_id', $sumber->id)->where('is_aktif', true)->pluck('santri_id');

        $dipindah = 0; $dilewati = 0;
        foreach ($santriAktif as $sid) {
            if (in_array($sid, $kecuali)) { $dilewati++; continue; }
            $this->pindahKelas((int) $sid, $tujuan, $tanggal, $keterangan ?? "Naik kelas dari {$sumber->nama}");
            $dipindah++;
        }
        return ['dipindah' => $dipindah, 'dilewati' => $dilewati];
    }
}
