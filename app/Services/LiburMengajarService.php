<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\AbsensiMengajar;
use Carbon\Carbon;

/**
 * Otomatis mengisi absensi mengajar (REGULER + TAHFIDZ + TAHSIN) menjadi 'libur'
 * pada hari libur (nasional/pesantren/darurat). JP tetap diberikan penuh (gaji jalan),
 * keterangan mencantumkan jenis libur. Idempotent: lewati sesi yang sudah tercatat,
 * dan koreksi sesi 'tidak_terlaksana' (auto no-show) → 'libur' bila ternyata hari libur.
 */
class LiburMengajarService
{
    /** Isi libur untuk satu tanggal (semua jadwal). Return jumlah sesi yang ditandai libur. */
    public function isiLiburTanggal(string $tanggal): int
    {
        $libur = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
            ->orderByDesc('is_darurat')->first();
        if (!$libur) return 0;

        $hari = TimezoneHelper::namaHariDB(Carbon::parse($tanggal));
        $ket  = 'Libur ' . ucfirst($libur->sumber ?: $libur->tipe ?: 'pesantren') . ': ' . $libur->nama;

        $jadwal = JadwalMengajar::where('hari', $hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->get(['id', 'tenaga_pendidik_id', 'jumlah_jp']);

        $n = 0;
        foreach ($jadwal as $j) {
            $am = AbsensiMengajar::where('jadwal_mengajar_id', $j->id)->whereDate('tanggal', $tanggal)->first();

            if (!$am) {
                AbsensiMengajar::create([
                    'jadwal_mengajar_id' => $j->id,
                    'tenaga_pendidik_id' => $j->tenaga_pendidik_id,
                    'tanggal'            => $tanggal,
                    'jp_terlaksana'      => $j->jumlah_jp, // JP tetap diberikan saat libur
                    'status'             => 'libur',
                    'sudah_buka_jurnal'  => false,
                    'keterangan'         => $ket,
                ]);
                $n++;
            } elseif ($am->status === 'tidak_terlaksana') {
                // Sempat ter-mark "tidak hadir" sebelum libur ditetapkan → koreksi ke libur.
                $am->update(['status' => 'libur', 'jp_terlaksana' => $j->jumlah_jp, 'keterangan' => $ket]);
                $n++;
            }
            // status lain (terlaksana/izin/pengganti/libur) dibiarkan apa adanya.
        }
        return $n;
    }

    /** Isi semua tanggal dalam rentang sebuah HariLibur s/d hari ini (dipakai saat libur dibuat). */
    public function isiUntukLibur(HariLibur $libur): int
    {
        $today = TimezoneHelper::today();
        $start = $libur->tanggal instanceof Carbon ? $libur->tanggal->copy() : Carbon::parse($libur->tanggal);
        $end   = $libur->tanggal_selesai
            ? ($libur->tanggal_selesai instanceof Carbon ? $libur->tanggal_selesai->copy() : Carbon::parse($libur->tanggal_selesai))
            : $start->copy();
        if ($end->gt($today)) $end = $today->copy(); // tanggal masa depan ditangani command harian

        $n = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $n += $this->isiLiburTanggal($d->toDateString());
        }
        return $n;
    }
}
