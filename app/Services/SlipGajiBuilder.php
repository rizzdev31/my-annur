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
        // Prinsip: setiap baris slip memakai KETERANGAN ASLI dari rincian
        // (detail_penggajian) — mis. "Tugas Absen Kegiatan: Briefing Pekanan
        // (2 kegiatan)" — bukan label umum "Vakasi Tugas Tambahan". Kolom ringkas
        // di tabel penggajian hanya dipakai sebagai cadangan untuk periode lama
        // yang rinciannya belum tersimpan.
        $penerimaan = [];

        // Baris dari rincian: dipakai bila ada, kalau tidak → cadangan ringkas.
        $dariDetail = function (string $tipe) use ($detail) {
            return $detail->where('tipe', $tipe)->map(fn ($d) => [
                // Keterangan asli dulu; kalau memang kosong baru pakai nama jenisnya
                // supaya tidak ada baris yang hilang dari slip.
                'label'   => $d->keterangan ?: $d->label_tipe,
                'qty'     => $d->jumlah_satuan > 1 && $d->satuan
                    ? rtrim(rtrim(number_format((float) $d->jumlah_satuan, 2, ',', '.'), '0'), ',') . ' ' . $d->satuan
                    : null,
                'nominal' => (float) $d->subtotal,
            ])->values()->all();
        };

        // Beberapa baris kecil sejenis (mis. piket per hari) dirangkum agar slip
        // tetap terbaca, dengan jumlah satuan & tarif per satuan tetap terlihat.
        $rangkum = function (string $tipe, string $label, string $satuan) use ($detail) {
            $rows = $detail->where('tipe', $tipe);
            if ($rows->isEmpty()) return null;
            $jml    = (float) $rows->sum('jumlah_satuan');
            $tarif  = (float) $rows->first()->nilai_per_satuan;
            $seragam = $rows->every(fn ($d) => (float) $d->nilai_per_satuan === $tarif);
            return [
                'label'   => $label . ($seragam && $tarif > 0
                    ? ' @ Rp ' . number_format($tarif, 0, ',', '.') . '/' . $satuan : ''),
                'qty'     => $jml > 0 ? rtrim(rtrim(number_format($jml, 2, ',', '.'), '0'), ',') . ' ' . $satuan : null,
                'nominal' => (float) $rows->sum('subtotal'),
            ];
        };

        // 1. Gaji pokok (pecah per jabatan bila ada rinciannya)
        foreach ($dariDetail('gaji_pokok') as $r) $penerimaan[] = $r;
        if (!$detail->where('tipe', 'gaji_pokok')->count() && $penggajian->gaji_pokok > 0) {
            $penerimaan[] = ['label' => 'Gaji Pokok', 'qty' => null, 'nominal' => (float) $penggajian->gaji_pokok];
        }

        // 2. Mengajar (vakasi guru pengganti) — tarif per JP ikut ditampilkan
        $barisMengajar = $dariDetail('vakasi_mengajar');
        if ($barisMengajar) {
            foreach ($barisMengajar as $r) $penerimaan[] = $r;
        } elseif ($penggajian->vakasi_mengajar > 0) {
            $penerimaan[] = [
                'label'   => 'Mengajar Pengganti (Vakasi)',
                'qty'     => $penggajian->total_jp_mengajar ? $penggajian->total_jp_mengajar . ' JP' : null,
                'nominal' => (float) $penggajian->vakasi_mengajar,
            ];
        }

        // 3. Kehadiran harian
        $barisAbsen = $rangkum('vakasi_absen', 'Kehadiran Harian', 'hari');
        if ($barisAbsen) {
            $penerimaan[] = $barisAbsen;
        } elseif ($penggajian->vakasi_absen_harian > 0) {
            $penerimaan[] = [
                'label'   => 'Kehadiran Harian',
                'qty'     => $penggajian->total_hadir ? $penggajian->total_hadir . ' hari' : null,
                'nominal' => (float) $penggajian->vakasi_absen_harian,
            ];
        }

        // 4. Tugas jabatan
        foreach ($dariDetail('vakasi_tugas_jabatan') as $r) $penerimaan[] = $r;
        if (!$detail->where('tipe', 'vakasi_tugas_jabatan')->count() && $penggajian->vakasi_tugas_jabatan > 0) {
            $penerimaan[] = ['label' => 'Vakasi Tugas Jabatan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_tugas_jabatan];
        }

        // 5. TUGAS TAMBAHAN — per judul tugas, inilah permintaan utamanya
        foreach ($dariDetail('vakasi_tugas_tambahan') as $r) $penerimaan[] = $r;
        if (!$detail->where('tipe', 'vakasi_tugas_tambahan')->count() && $penggajian->vakasi_tugas_tambahan > 0) {
            $penerimaan[] = ['label' => 'Vakasi Tugas Tambahan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_tugas_tambahan];
        }

        // 6. Ikut kegiatan (nama kegiatannya ditampilkan)
        foreach ($dariDetail('vakasi_peserta_kegiatan') as $r) $penerimaan[] = $r;
        if (!$detail->where('tipe', 'vakasi_peserta_kegiatan')->count() && ($penggajian->vakasi_peserta_kegiatan ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Vakasi Peserta Kegiatan', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_peserta_kegiatan];
        }

        // 7. Lembur
        foreach ($dariDetail('vakasi_lembur') as $r) $penerimaan[] = $r;
        if (!$detail->where('tipe', 'vakasi_lembur')->count() && ($penggajian->vakasi_lembur ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Vakasi Lembur', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_lembur];
        }

        // 8. Piket & ekstrakurikuler — dirangkum (per hari / per pertemuan)
        $barisPiket = $rangkum('vakasi_piket', 'Tugas Piket', 'hari');
        if ($barisPiket) {
            $penerimaan[] = $barisPiket;
        } elseif (($penggajian->vakasi_piket ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Tugas Piket', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_piket];
        }

        $barisEkskul = $rangkum('vakasi_ekstrakurikuler', 'Pembina Ekstrakurikuler', 'pertemuan');
        if ($barisEkskul) {
            $penerimaan[] = $barisEkskul;
        } elseif (($penggajian->vakasi_ekstrakurikuler ?? 0) > 0) {
            $penerimaan[] = ['label' => 'Pembina Ekstrakurikuler', 'qty' => null, 'nominal' => (float) $penggajian->vakasi_ekstrakurikuler];
        }

        if ($penggajian->tunjangan_lainnya > 0) {
            $penerimaan[] = ['label' => 'Tunjangan Lainnya', 'qty' => null, 'nominal' => (float) $penggajian->tunjangan_lainnya];
        }

        // Baris rincian yang belum terwakili (mis. tipe baru) tetap ikut tampil,
        // supaya tidak ada rupiah yang hilang dari mata guru.
        $sudah = ['gaji_pokok', 'vakasi_mengajar', 'vakasi_absen', 'vakasi_tugas_jabatan',
            'vakasi_tugas_tambahan', 'vakasi_peserta_kegiatan', 'vakasi_lembur',
            'vakasi_piket', 'vakasi_ekstrakurikuler'];
        foreach ($detail->whereNotIn('tipe', $sudah) as $d) {
            if ((float) $d->subtotal <= 0) continue;                 // potongan diurus di bawah
            if ($d->isPotongan()) continue;                          // potongan diurus terpisah
            $penerimaan[] = ['label' => $d->keterangan ?: $d->label_tipe, 'qty' => null, 'nominal' => (float) $d->subtotal];
        }

        // ── POTONGAN ──────────────────────────────────────────────────────────
        $potongan = [];
        if ($penggajian->potongan_alfa > 0) {
            $potongan[] = ['label' => 'Tidak Hadir (Alfa)', 'qty' => $penggajian->total_alfa ? $penggajian->total_alfa.' hari' : null, 'nominal' => (float) $penggajian->potongan_alfa];
        }
        if ($penggajian->potongan_keterlambatan > 0) {
            $potongan[] = ['label' => 'Keterlambatan', 'qty' => $penggajian->total_terlambat ? $penggajian->total_terlambat.'×' : null, 'nominal' => (float) $penggajian->potongan_keterlambatan];
        }
        if ($penggajian->potongan_tetap > 0) {
            $potongan[] = ['label' => 'Potongan Tetap', 'qty' => null, 'nominal' => (float) $penggajian->potongan_tetap];
        }
        if (($penggajian->potongan_liburan ?? 0) > 0) {
            $potongan[] = ['label' => $penggajian->keterangan_liburan ?: 'Penyesuaian Liburan', 'qty' => null, 'nominal' => (float) $penggajian->potongan_liburan];
        }
        // Potongan rutin per guru (voucher/simpanan/LAZISMU) — selalu per item.
        foreach ($detail->where('tipe', 'potongan_guru') as $d) {
            $potongan[] = ['label' => $d->keterangan, 'qty' => null, 'nominal' => abs((float) $d->subtotal)];
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
                // Bila potongan melebihi pendapatan, gaji bersih dibulatkan ke 0.
                // Sisanya ditulis terbuka di slip supaya guru tahu, bukan hilang.
                'potongan_tidak_terbayar' => (float) ($penggajian->potongan_tidak_terbayar ?? 0),
                'tunjangan_lainnya' => (float) $penggajian->tunjangan_lainnya,
                'potongan_lainnya'  => (float) $penggajian->potongan_lainnya,
            ],
        ];
    }
}
