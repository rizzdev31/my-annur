<?php

namespace App\Services;

use App\Models\Santri;
use App\Models\HariLibur;
use App\Models\IzinSantri;
use App\Models\ControllingPeriode;
use App\Models\ControllingJadwal;
use App\Models\ControllingAbsensi;
use App\Services\WaService;
use Carbon\Carbon;

/**
 * Inti Smart Controlling: scan absensi (hadir/telat/alpha) + auto-Alpha.
 *
 * Model waktu (disepakati untuk konteks asrama):
 *   • [jam_mulai .. jam_selesai]           → HADIR
 *   • (jam_selesai .. jam_selesai+ambang]  → TELAT  (ambang = "telat > menit" jadi patokan tutup)
 *   • setelah jam_selesai+ambang           → DITUTUP → yang belum scan = ALPHA (auto)
 * Ambang sekaligus jadi BATAS antara jadwal satu dan berikutnya.
 * Telat & Alpha dikirim ke RamahAnak via AbsensiRamahAnak (kode dari config, fleksibel).
 */
class ControllingService
{
    public function __construct(
        private AbsensiRamahAnak $absensi,
        private WaService $wa,
    ) {}

    /** Pesan WA kehadiran kegiatan untuk wali santri. */
    private function pesanControlling($santri, string $status, string $namaKeg, string $tgl, ?string $jam): string
    {
        return WaTemplate::controlling($santri->nama_lengkap, $status, $namaKeg, $tgl, $jam);
    }

    /** Proses 1 scan barcode. Lempar DomainException bila gagal (controller petakan ke HTTP). */
    public function scan(string $nip, ?int $kegiatanId, ?string $petugas): array
    {
        $santri = Santri::where('is_aktif', true)->where('nip', $nip)->first();
        if (!$santri) {
            throw new \DomainException('Santri tidak ditemukan / tidak aktif.', 404);
        }

        $periode = ControllingPeriode::aktif();
        if (!$periode) {
            throw new \DomainException('Belum ada periode controlling yang aktif.', 422);
        }

        $now   = TimezoneHelper::now();
        $today = $now->toDateString();
        $hari  = TimezoneHelper::namaHariDB($now);

        $jadwalList = ControllingJadwal::with('kegiatan')
            ->where('periode_id', $periode->id)->where('hari', $hari)->where('is_aktif', true)
            ->when($kegiatanId, fn($q) => $q->where('kegiatan_id', $kegiatanId))
            ->get();

        // Kandidat: window absen masih terbuka → [jam_mulai .. jam_selesai + ambang].
        $kandidat = $jadwalList->map(function ($j) use ($now, $today) {
            $start = Carbon::parse("$today {$j->jam_mulai}", TimezoneHelper::TZ);
            $end   = Carbon::parse("$today {$j->jam_selesai}", TimezoneHelper::TZ);
            $close = $end->copy()->addMinutes((int) $j->ambang_telat_menit);
            return ['j' => $j, 'start' => $start, 'end' => $end, 'close' => $close];
        })->filter(fn($c) => $now->betweenIncluded($c['start'], $c['close']));

        if ($kandidat->isEmpty()) {
            throw new \DomainException('Tidak ada kegiatan absen yang aktif saat ini (window sudah tutup).', 422);
        }

        // Prioritas: kegiatan yang MASIH dalam window utama (hadir) menang atas masa-tenggang
        // jadwal sebelumnya. Bila beberapa cocok, ambil yang jam_mulai paling baru.
        $pilih = $kandidat->sortByDesc(fn($c) => [$now->lte($c['end']) ? 1 : 0, $c['start']->timestamp])->first();
        $jadwal = $pilih['j'];

        // Sudah absen (hadir/telat) → idempotent, jangan timpa.
        $ada = ControllingAbsensi::where('jadwal_id', $jadwal->id)
            ->where('santri_id', $santri->id)->whereDate('tanggal', $today)->first();
        if ($ada && in_array($ada->status, ['hadir', 'telat'], true)) {
            return $this->hasil($santri, $jadwal, $ada->status, $ada->jam_scan, true);
        }

        // Status: HADIR bila masih dalam window utama, TELAT bila sudah di masa tenggang.
        $status  = $now->lte($pilih['end']) ? 'hadir' : 'telat';
        $jamScan = $now->format('H:i:s');

        $abs = ControllingAbsensi::updateOrCreate(
            ['jadwal_id' => $jadwal->id, 'santri_id' => $santri->id, 'tanggal' => $today],
            [
                'periode_id'  => $periode->id,
                'kegiatan_id' => $jadwal->kegiatan_id,
                'status'      => $status,
                'jam_scan'    => $jamScan,
                'petugas'     => $petugas,
            ]
        );

        // Telat → kirim ke RamahAnak (kode dari config). Hadir tidak dikirim.
        if ($status === 'telat' && !$abs->dikirim_outbox) {
            $terkirim = $this->absensi->kirim(
                $santri->nip, 'telat', $today,
                $jadwal->kegiatan?->nama ?? 'Kegiatan',
                $now->format('H:i'), $petugas ?: 'sistem-controlling', 'CTRL'
            );
            if ($terkirim) $abs->update(['dikirim_outbox' => true]);
        }

        // Notifikasi WA wali (hadir/telat) — idempotent per baris absensi.
        $this->wa->enqueue('controlling', $santri,
            $this->pesanControlling($santri, $status, $jadwal->kegiatan?->nama ?? 'Kegiatan', $today, $jamScan),
            'WA-CTRL-' . $abs->id);

        return $this->hasil($santri, $jadwal, $status, $jamScan, false);
    }

    private function hasil($santri, $jadwal, string $status, $jamScan, bool $sudah): array
    {
        return [
            'sudah_absen' => $sudah,
            'santri'      => ['id' => $santri->id, 'nip' => $santri->nip, 'nama' => $santri->nama_lengkap],
            'kegiatan'    => $jadwal->kegiatan?->nama ?? '—',
            'status'      => $status,
            'jam_scan'    => is_string($jamScan) ? substr($jamScan, 0, 5) : $jamScan,
        ];
    }

    /**
     * Tandai ALPHA santri yang tak hadir pada kegiatan yang window-nya sudah TUTUP
     * (jam_selesai + ambang). Lewati hari libur. Alpha juga dikirim ke RamahAnak.
     * Return jumlah baris alpha dibuat.
     */
    public function autoAlpha(?string $tanggal = null): int
    {
        $periode = ControllingPeriode::aktif();
        if (!$periode) return 0;

        $tgl  = $tanggal ?: TimezoneHelper::today()->toDateString();
        $hari = TimezoneHelper::namaHariDB(Carbon::parse($tgl));

        // Skip hari libur aktif.
        $libur = HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tgl)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tgl))
            ->exists();
        if ($libur) return 0;

        $now = TimezoneHelper::now();
        $jadwalList = ControllingJadwal::with('kegiatan')
            ->where('periode_id', $periode->id)->where('hari', $hari)->where('is_aktif', true)->get()
            ->filter(function ($j) use ($now, $tgl) {
                // window + masa tenggang sudah tutup (telat sudah tidak mungkin).
                $close = Carbon::parse("$tgl {$j->jam_selesai}", TimezoneHelper::TZ)
                    ->addMinutes((int) $j->ambang_telat_menit);
                return $now->gt($close);
            });

        $santri = Santri::where('is_aktif', true)->get(['id', 'nip', 'nama_lengkap', 'no_whatsapp']);

        // Santri yang IZIN (disetujui) mencakup tanggal ini — termasuk izin pulang
        // Smart Health. Mereka DIKECUALIKAN dari Alpha → tercatat 'izin', tanpa WA.
        $izinIds = IzinSantri::where('status', 'disetujui')
            ->whereDate('tanggal_mulai', '<=', $tgl)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $tgl))
            ->pluck('santri_id')->unique()->flip();

        $dibuat = 0;

        foreach ($jadwalList as $j) {
            $sudah = ControllingAbsensi::where('jadwal_id', $j->id)->whereDate('tanggal', $tgl)
                ->pluck('santri_id')->all();
            $namaKeg = $j->kegiatan?->nama ?? 'Kegiatan';

            foreach ($santri->whereNotIn('id', $sudah) as $s) {
                // Sedang izin → tandai 'izin' (dimaafkan), tidak kirim WA/RamahAnak.
                if ($izinIds->has($s->id)) {
                    ControllingAbsensi::create([
                        'periode_id'  => $periode->id,
                        'jadwal_id'   => $j->id,
                        'kegiatan_id' => $j->kegiatan_id,
                        'santri_id'   => $s->id,
                        'tanggal'     => $tgl,
                        'status'      => 'izin',
                        'jam_scan'    => null,
                        'keterangan'  => 'Auto: sedang izin (disetujui).',
                    ]);
                    continue;
                }

                $abs = ControllingAbsensi::create([
                    'periode_id'  => $periode->id,
                    'jadwal_id'   => $j->id,
                    'kegiatan_id' => $j->kegiatan_id,
                    'santri_id'   => $s->id,
                    'tanggal'     => $tgl,
                    'status'      => 'alpha',
                    'jam_scan'    => null,
                    'keterangan'  => 'Auto: tidak scan sampai window tutup.',
                ]);
                $dibuat++;

                // Alpha → kirim ke RamahAnak (kode dari config; bisa dimatikan via .env).
                if ($s->nip) {
                    $terkirim = $this->absensi->kirim(
                        $s->nip, 'alpha', $tgl, $namaKeg, null, 'sistem-controlling', 'CTRL'
                    );
                    if ($terkirim) $abs->update(['dikirim_outbox' => true]);
                }

                // Notifikasi WA wali (alfa).
                $this->wa->enqueue('controlling', $s,
                    $this->pesanControlling($s, 'alpha', $namaKeg, $tgl, null),
                    'WA-CTRL-' . $abs->id);
            }
        }
        return $dibuat;
    }
}
