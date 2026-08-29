<?php

namespace App\Services;

use App\Models\Penggajian;

/**
 * SlipGajiBuilder
 *
 * Menyiapkan payload slip gaji format instansi (dua kolom Penerimaan/Potongan)
 * dari satu record Penggajian. Dipakai bersama oleh:
 *   - LaporanController@slipGaji  (Laporan → Penggajian)
 *   - PenggajianController@slip   (Smart Payroll → Penggajian)
 * agar desain & data slip konsisten di kedua fitur.
 */
class SlipGajiBuilder
{
    /**
     * Identitas instansi default (dipakai slip gaji & laporan vakasi).
     * Sesuaikan bila kelak ada master data Profil Instansi.
     */
    public static function instansi(): array
    {
        $logoPath = public_path('storage/logo1.png');
        return [
            'nama'      => 'PONPES MUHAMMADIYAH AN-NUR SIDOARJO',
            'alamat'    => 'Jl. H. Ahmad Dahlan No. 1, RT. 03, RW. 01 Desa Penatarsewu, Kec. Tanggulangin, Kab. Sidoarjo',
            'perihal'   => 'Absensi kehadiran Per-Tanggal 25',
            'bendahara' => "L'ANATUS SHOLIHAH, S.Pd.",
            'logo'      => file_exists($logoPath) ? asset('storage/logo1.png') : null,
        ];
    }

    public static function build(Penggajian $penggajian): array
    {
        $penggajian->loadMissing([
            'tenagaPendidik.user',
            'tenagaPendidik.jabatan',
            'periodePenggajian',
            'detailPenggajian',
        ]);

        $guru   = $penggajian->tenagaPendidik;
        $detail = $penggajian->detailPenggajian;

        // ── PENERIMAAN ────────────────────────────────────────────────────────
        $penerimaan = [];

        // Gaji pokok (pecah per jabatan bila ada detail-nya)
        $gajiPokokRows = $detail->where('tipe', 'gaji_pokok');
        if ($gajiPokokRows->isNotEmpty()) {
            foreach ($gajiPokokRows as $d) {
                $penerimaan[] = ['label' => $d->keterangan ?: 'Gaji Pokok', 'qty' => null, 'nominal' => (float) $d->subtotal];
            }
        } elseif ($penggajian->gaji_pokok > 0) {
            $penerimaan[] = ['label' => 'Gaji Pokok', 'qty' => null, 'nominal' => (float) $penggajian->gaji_pokok];
        }

        if ($penggajian->vakasi_mengajar > 0) {
            $penerimaan[] = [
                'label'   => 'Jam Mengajar (Vakasi)',
                'qty'     => $penggajian->total_jp_mengajar ? $penggajian->total_jp_mengajar.' JP' : null,
                'nominal' => (float) $penggajian->vakasi_mengajar,
            ];
        }
        if ($penggajian->vakasi_absen_harian > 0) {
            $penerimaan[] = [
                'label'   => 'Kehadiran (Vakasi Absen)',
                'qty'     => $penggajian->total_hadir ? $penggajian->total_hadir.' hari' : null,
                'nominal' => (float) $penggajian->vakasi_absen_harian,
            ];
        }
        if ($penggajian->vakasi_tugas_jabatan > 0) {
            $penerimaan[] = ['label' => 'Vakasi Tugas Jabatan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_tugas_jabatan];
        }
        if ($penggajian->vakasi_tugas_tambahan > 0) {
            $penerimaan[] = ['label' => 'Vakasi Tugas Tambahan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_tugas_tambahan];
        }
        if (($penggajian->vakasi_peserta_kegiatan ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Vakasi Peserta Kegiatan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_peserta_kegiatan];
        }
        if (($penggajian->vakasi_lembur ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Vakasi Lembur', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_lembur];
        }
        if ($penggajian->tunjangan_lainnya > 0) {
            $penerimaan[] = ['label' => 'Tunjangan Lainnya', 'qty' => null, 'nominal' => (float) $penggajian->tunjangan_lainnya];
        }

        // ── POTONGAN ──────────────────────────────────────────────────────────
        $potongan = [];
        if ($penggajian->potongan_alfa > 0) {
            $potongan[] = ['label' => 'Tidak Hadir / Hari (Alfa)', 'qty' => $penggajian->total_alfa ? $penggajian->total_alfa.' hari' : null, 'nominal' => (float) $penggajian->potongan_alfa];
        }
        if ($penggajian->potongan_keterlambatan > 0) {
            $potongan[] = ['label' => 'Tidak Hadir / Jam (Terlambat)', 'qty' => $penggajian->total_terlambat ? $penggajian->total_terlambat.'×' : null, 'nominal' => (float) $penggajian->potongan_keterlambatan];
        }
        if ($penggajian->potongan_tetap > 0) {
            $potongan[] = ['label' => 'Potongan Tetap', 'qty' => null, 'nominal' => (float) $penggajian->potongan_tetap];
        }
        if (($penggajian->potongan_liburan ?? 0) > 0) {
            $potongan[] = ['label' => $penggajian->keterangan_liburan ?: 'Penyesuaian Liburan', 'qty' => null, 'nominal' => (float) $penggajian->potongan_liburan];
        }
        if ($penggajian->potongan_lainnya > 0) {
            $potongan[] = ['label' => 'Potongan Lainnya', 'qty' => null, 'nominal' => (float) $penggajian->potongan_lainnya];
        }

        // ── Identitas instansi (default — sesuaikan bila ada master data instansi) ──
        $instansi = self::instansi();

        return [
            'instansi' => $instansi,
            'logo'     => $instansi['logo'],
            'guru'     => [
                'nama'    => $guru?->user?->name ?? '—',
                'nip'     => $guru?->nip,
                'foto'    => $guru?->user?->foto ? asset('storage/'.$guru->user->foto) : null,
                'jabatan' => $guru?->jabatan?->nama_jabatan ?? '—',
            ],
            'periode' => [
                'id'    => $penggajian->periodePenggajian?->id,
                'label' => $penggajian->periodePenggajian?->nama_bulan,
            ],
            'slip' => [
                'id'                => $penggajian->id,
                'penerimaan'        => $penerimaan,
                'potongan'          => $potongan,
                'total_pendapatan'  => (float) $penggajian->total_pendapatan,
                'total_potongan'    => (float) $penggajian->total_potongan,
                'gaji_bersih'       => (float) $penggajian->gaji_bersih,
                'status'            => $penggajian->status,
                'status_badge'      => $penggajian->status_badge,
                'catatan'           => $penggajian->catatan,
                'ada_koreksi'       => (bool) $penggajian->ada_koreksi_manual,
                'tunjangan_lainnya' => (float) $penggajian->tunjangan_lainnya,
                'potongan_lainnya'  => (float) $penggajian->potongan_lainnya,
            ],
        ];
    }
}
