<?php

namespace App\Services;

use App\Models\TenagaPendidik;
use App\Models\SmartHealthLaporan;
use App\Models\SmartHealthPengecekan;
use App\Models\IzinSantri;

/**
 * Inti Smart Health: lapor → validasi Bagian Kesehatan → pemantauan (Hari 1–3) →
 * WA otomatis ke wali (+foto). "Izin pulang" (Hari 3 / Darurat) otomatis membuat
 * izin_santri syar'i (sakit) agar data izin & kesehatan sinkron.
 */
class SmartHealthService
{
    public function __construct(private WaService $wa) {}

    /** Guru melaporkan santri sakit. */
    public function lapor(TenagaPendidik $pelapor, array $d): SmartHealthLaporan
    {
        return SmartHealthLaporan::create([
            'santri_id'                  => $d['santri_id'],
            'pelapor_tenaga_pendidik_id' => $pelapor->id,
            'deskripsi_penyakit'         => $d['deskripsi_penyakit'],
            'foto'                       => $d['foto'] ?? null,
            'status'                     => 'menunggu',
        ]);
    }

    /** Bagian Kesehatan menolak → laporan dihapus (sesuai alur). */
    public function tolak(SmartHealthLaporan $l): void
    {
        if ($l->status !== 'menunggu') {
            throw new \DomainException('Laporan ini sudah diproses.', 422);
        }
        $l->delete();
    }

    /** Bagian Kesehatan menyetujui → masuk pemantauan + WA "laporan diterima". */
    public function setujui(SmartHealthLaporan $l, TenagaPendidik $petugas): SmartHealthLaporan
    {
        if ($l->status !== 'menunggu') {
            throw new \DomainException('Laporan ini sudah diproses.', 422);
        }
        $l->update([
            'status'         => 'dalam_pengecekan',
            'disetujui_oleh' => $petugas->id,
            'disetujui_pada' => now(),
        ]);

        // Notifikasi awal ke wali (deskripsi + foto).
        $this->kirimWa($l, 'diterima', null, 'WA-HEALTH-APRV-' . $l->id);
        return $l->fresh();
    }

    /**
     * Catat keputusan pemantauan: sembuh | pengecekan | darurat.
     * Hari pengecekan naik otomatis (1→2→3); Hari 3 / darurat → izin pulang.
     */
    public function catatPengecekan(SmartHealthLaporan $l, TenagaPendidik $petugas, string $keputusan, ?string $catatan): SmartHealthPengecekan
    {
        if ($l->status !== 'dalam_pengecekan') {
            throw new \DomainException('Laporan tidak dalam masa pengecekan.', 422);
        }

        $hari = null;
        if ($keputusan === 'pengecekan') {
            $hari = SmartHealthPengecekan::where('laporan_id', $l->id)
                ->where('keputusan', 'pengecekan')->count() + 1;
        }

        $p = SmartHealthPengecekan::create([
            'laporan_id'              => $l->id,
            'hari_ke'                 => $hari,
            'keputusan'               => $keputusan,
            'catatan'                 => $catatan,
            'oleh_tenaga_pendidik_id' => $petugas->id,
            'tanggal'                 => TimezoneHelper::today()->toDateString(),
        ]);

        // Update status laporan + integrasi izin pulang.
        if ($keputusan === 'sembuh') {
            $l->update(['status' => 'selesai', 'kondisi_akhir' => 'sembuh']);
        } elseif ($keputusan === 'darurat') {
            $l->update(['status' => 'selesai', 'kondisi_akhir' => 'darurat']);
            $this->buatIzinPulang($l);
        } elseif ($keputusan === 'pengecekan' && $hari >= 3) {
            $l->update(['status' => 'selesai', 'kondisi_akhir' => 'izin_pulang']);
            $this->buatIzinPulang($l);
        }

        $this->kirimWa($l, $keputusan, $hari, 'WA-HEALTH-' . $p->id);
        return $p;
    }

    // ─── Internal ───────────────────────────────────────────────────────────────

    private function kirimWa(SmartHealthLaporan $l, string $tipe, ?int $hari, string $refId): void
    {
        $s = $l->santri;
        if (!$s) return;

        $pesan = $tipe === 'diterima'
            ? WaTemplate::bungkus(
                "🏥 *Info Kesehatan Santri*\n\n"
                . "👤 Nama       : *{$s->nama_lengkap}*\n"
                . "🩺 Keluhan  : {$l->deskripsi_penyakit}\n"
                . "📋 Status     : *Laporan diterima — dalam pengecekan*\n\n"
                . "Ananda sedang dipantau Bagian Kesehatan. Kami kabari perkembangannya.")
            : WaTemplate::kesehatan($s->nama_lengkap, $l->deskripsi_penyakit, $tipe, $hari);

        $this->wa->enqueue('kesehatan', $s, $pesan, $refId, $l->fotoUrl());
    }

    /** Buat izin santri syar'i (sakit) otomatis saat izin pulang / darurat. */
    private function buatIzinPulang(SmartHealthLaporan $l): void
    {
        $today = TimezoneHelper::today();
        IzinSantri::updateOrCreate(
            ['santri_id' => $l->santri_id, 'sumber' => 'smart_health', 'tanggal_mulai' => $today->toDateString()],
            [
                'jenis'           => 'syari',
                'alasan'          => 'Sakit: ' . $l->deskripsi_penyakit,
                'tanggal_selesai' => $today->copy()->addDays(2)->toDateString(),
                'status'          => 'disetujui',
                'pengaju_tipe'    => 'guru',
                'diajukan_oleh'   => $l->disetujui_oleh,
                'disetujui_oleh'  => $l->disetujui_oleh,
                'diputuskan_pada' => now(),
            ]
        );
    }
}
