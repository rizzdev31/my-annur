<?php

namespace App\Console\Commands;

use App\Models\AbsensiHarian;
use App\Models\AbsensiMengajar;
use App\Models\JadwalMengajar;
use App\Models\Notifikasi;
use App\Models\PengajuanIzin;
use App\Models\SettingNotifikasi;
use App\Models\TenagaPendidik;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pengingat & ESKALASI notifikasi terjadwal (lihat docs/PRD-Notifikasi.md).
 *  - absensi.reminder_masuk / reminder_pulang  (per guru per hari)
 *  - mengajar.reminder  (absen mengajar belum diisi & melewati jadwal, per sesi)
 * Reminder berulang tiap `ulang_menit` s/d `batas_menit`, dibatasi `maks_per_hari`.
 * ESKALASI: bila masih belum ditindak setelah `eskalasi.setelah_menit`, kabari admin (1×).
 * Dijadwalkan tiap 15 menit. Menghormati toggle aktif di setting_notifikasi.
 */
class NotifikasiReminder extends Command
{
    protected $signature = 'notifikasi:reminder';
    protected $description = 'Pengingat & eskalasi notifikasi wajib (absensi & mengajar)';

    public function handle(): int
    {
        $now      = TimezoneHelper::now();
        $today    = $now->toDateString();
        $namaHari = TimezoneHelper::namaHariDB($now);
        $n = 0;

        $n += $this->remindAbsensi($now, $today, $namaHari);
        $n += $this->remindMengajar($now, $today, $namaHari);
        $n += $this->remindKegiatan($now, $today);

        $this->info("notifikasi:reminder → {$n} pengingat/eskalasi terkirim.");
        return self::SUCCESS;
    }

    // ── Absensi masuk & pulang ────────────────────────────────────────────────
    private function remindAbsensi(Carbon $now, string $today, string $namaHari): int
    {
        $cfgMasuk  = SettingNotifikasi::untuk('absensi.reminder_masuk');
        $cfgPulang = SettingNotifikasi::untuk('absensi.reminder_pulang');
        if ((!$cfgMasuk || !$cfgMasuk->aktif) && (!$cfgPulang || !$cfgPulang->aktif)) return 0;

        $n = 0;
        $guruList = TenagaPendidik::where('is_aktif', true)->with('user')->get();

        foreach ($guruList as $g) {
            if (!$g->user) continue;
            $jk = $g->jamKerjaAktif();
            if (!$jk || $jk->isHariLibur($namaHari)) continue;
            $jadwal = $jk->getJamUntukHari($namaHari);
            if (!$jadwal || empty($jadwal['jam_masuk']) || empty($jadwal['jam_pulang'])) continue;

            if ($this->sedangIzin($g->id, $today)) continue;

            $absen  = AbsensiHarian::where('tenaga_pendidik_id', $g->id)->whereDate('tanggal', $today)->first();
            $masuk  = Carbon::parse($today . ' ' . $jadwal['jam_masuk'], TimezoneHelper::TZ);
            $pulang = Carbon::parse($today . ' ' . $jadwal['jam_pulang'], TimezoneHelper::TZ);
            if ($jadwal['lintas_hari'] ?? false) $pulang->addDay();

            // MASUK — window [masuk-sebelum, masuk+batas], belum check-in.
            if ($cfgMasuk && $cfgMasuk->aktif && (!$absen || !$absen->jam_masuk)) {
                $start = $masuk->copy()->subMinutes($cfgMasuk->reminder['sebelum_menit'] ?? 15);
                $end   = $masuk->copy()->addMinutes($cfgMasuk->reminder['batas_menit'] ?? 30);
                if ($now->betweenIncluded($start, $end)) {
                    $bucket = $this->bucket($now, $start, $cfgMasuk->reminder['ulang_menit'] ?? 0);
                    $n += NotifikasiService::event('absensi.reminder_masuk', [
                        'user'  => $g->user,
                        'judul' => 'Pengingat Absen Masuk',
                        'pesan' => 'Jangan lupa absen masuk. Jadwal ' . substr($jadwal['jam_masuk'], 0, 5) . '.',
                        'tipe'  => 'absensi', 'data' => ['route' => '/absensi'],
                        'dedup' => "masuk-{$today}-{$bucket}",
                    ]);
                }
            }

            // PULANG — window [pulang, pulang+batas], sudah masuk belum pulang.
            if ($cfgPulang && $cfgPulang->aktif && $absen && $absen->jam_masuk && !$absen->jam_pulang) {
                $end = $pulang->copy()->addMinutes($cfgPulang->reminder['batas_menit'] ?? 45);
                if ($now->betweenIncluded($pulang, $end)) {
                    $bucket = $this->bucket($now, $pulang, $cfgPulang->reminder['ulang_menit'] ?? 0);
                    $n += NotifikasiService::event('absensi.reminder_pulang', [
                        'user'  => $g->user,
                        'judul' => 'Pengingat Absen Pulang',
                        'pesan' => 'Sudah jam pulang (' . substr($jadwal['jam_pulang'], 0, 5) . '). Jangan lupa absen pulang.',
                        'tipe'  => 'absensi', 'data' => ['route' => '/absensi'],
                        'dedup' => "pulang-{$today}-{$bucket}",
                    ]);
                }
                // ESKALASI ke admin bila lewat ambang & tetap belum pulang.
                $n += $this->eskalasi($cfgPulang, $now, $pulang, "esk-pulang-{$g->id}-{$today}",
                    'Guru belum absen pulang',
                    ($g->user->name ?? 'Guru') . ' belum absen pulang melewati batas (jadwal ' . substr($jadwal['jam_pulang'], 0, 5) . ').');
            }
        }
        return $n;
    }

    // ── Mengajar: absen mengajar belum diisi & melewati jadwal ────────────────
    private function remindMengajar(Carbon $now, string $today, string $namaHari): int
    {
        $cfg = SettingNotifikasi::untuk('mengajar.reminder');
        if (!$cfg || !$cfg->aktif) return 0;

        $n = 0;
        $sesiHariIni = JadwalMengajar::where('hari', $namaHari)
            ->where('is_aktif', true)
            ->with(['tenagaPendidik.user', 'mataPelajaran'])
            ->get();

        foreach ($sesiHariIni as $j) {
            if (!$j->tenagaPendidik?->user) continue;
            if (empty($j->jam_selesai)) continue;

            $selesai = Carbon::parse($today . ' ' . $j->jam_selesai, TimezoneHelper::TZ);
            // Hanya setelah sesi MELEWATI jadwal (jam selesai lewat).
            if ($now->lte($selesai)) continue;

            // Sudah diisi? (ada record absensi mengajar utk sesi ini hari ini)
            $sudahDiisi = AbsensiMengajar::where('jadwal_mengajar_id', $j->id)
                ->whereDate('tanggal', $today)->exists();
            if ($sudahDiisi) continue;

            // Guru sedang izin → lewati.
            if ($this->sedangIzin($j->tenaga_pendidik_id, $today)) continue;

            $end = $selesai->copy()->addMinutes($cfg->reminder['batas_menit'] ?? 60);
            if ($now->betweenIncluded($selesai, $end)) {
                $bucket = $this->bucket($now, $selesai, $cfg->reminder['ulang_menit'] ?? 0);
                $mapel  = $j->mataPelajaran?->nama ?? 'sesi';
                $kelas  = $j->kelas ?? '';
                $n += NotifikasiService::event('mengajar.reminder', [
                    'user'  => $j->tenagaPendidik->user,
                    'judul' => 'Absen Mengajar Belum Diisi',
                    'pesan' => "Sesi {$mapel} {$kelas} (" . substr($j->jam_mulai, 0, 5) . '–' . substr($j->jam_selesai, 0, 5)
                        . ') sudah lewat & belum diabsen. Segera isi absen mengajar/jurnal.',
                    'tipe'  => 'tugas_update', 'data' => ['route' => '/mengajar'],
                    'dedup' => "j{$j->id}-{$today}-{$bucket}",
                ]);
            }
            // ESKALASI ke admin bila lewat ambang & tetap belum diisi.
            $n += $this->eskalasi($cfg, $now, $selesai, "esk-mengajar-{$j->id}-{$today}",
                'Sesi mengajar belum diabsen',
                ($j->tenagaPendidik->user->name ?? 'Guru') . ' belum mengisi absen mengajar sesi '
                    . ($j->mataPelajaran?->nama ?? '') . ' ' . ($j->kelas ?? '')
                    . ' (jadwal ' . substr($j->jam_selesai, 0, 5) . ').');
        }
        return $n;
    }

    // ── Kegiatan Penting: ingatkan guru piket mencatat kehadiran ──────────────
    private function remindKegiatan(Carbon $now, string $today): int
    {
        $cfg = SettingNotifikasi::untuk('kegiatan.reminder');
        if (!$cfg || !$cfg->aktif) return 0;

        // Guru piket bertugas hari ini.
        $piketUsers = \App\Models\PiketJadwal::whereDate('tanggal', $today)
            ->with('tenagaPendidik.user')->get()
            ->map(fn ($p) => $p->tenagaPendidik?->user)->filter()->unique('id')->values();
        if ($piketUsers->isEmpty()) return 0;

        $n = 0;
        foreach (\App\Models\KegiatanPenting::where('is_aktif', true)->get() as $keg) {
            if (empty($keg->jam)) continue;
            $jam   = Carbon::parse($today . ' ' . $keg->jam, TimezoneHelper::TZ);
            $start = $jam->copy()->subMinutes($cfg->reminder['sebelum_menit'] ?? 5);
            $end   = $jam->copy()->addMinutes($cfg->reminder['batas_menit'] ?? 60);
            if (!$now->betweenIncluded($start, $end)) continue;

            // Sudah dicatat guru piket? (ada record hari ini) → stop mengingatkan.
            $sudah = \App\Models\AbsensiKegiatanPenting::where('kegiatan_penting_id', $keg->id)
                ->whereDate('tanggal', $today)->exists();
            if ($sudah) continue;

            $bucket = $this->bucket($now, $start, $cfg->reminder['ulang_menit'] ?? 0);
            foreach ($piketUsers as $u) {
                $n += NotifikasiService::event('kegiatan.reminder', [
                    'judul' => 'Kegiatan: ' . $keg->nama,
                    'pesan' => 'Waktunya mencatat kehadiran ' . $keg->nama . ' (jam ' . substr((string) $keg->jam, 0, 5) . ').',
                    'tipe'  => 'pengumuman',
                    'data'  => ['route' => '/piket'],
                    'dedup' => "keg{$keg->id}-{$today}-{$bucket}",
                ], [$u]);
            }
        }
        return $n;
    }

    // ── Eskalasi ke admin (1× per kunci) ──────────────────────────────────────
    private function eskalasi(?SettingNotifikasi $cfg, Carbon $now, Carbon $acuan, string $dedup, string $judul, string $pesan): int
    {
        $esk = $cfg?->eskalasi;
        if (!$esk || empty($esk['setelah_menit'])) return 0;
        if ($now->lt($acuan->copy()->addMinutes((int) $esk['setelah_menit']))) return 0;

        $targets = $this->targetEskalasi($esk['ke'] ?? ['admin']);
        $n = 0;
        foreach ($targets as $uid) {
            $sudah = Notifikasi::where('user_id', $uid)
                ->where('event_kode', $cfg->event_kode)
                ->where('data->dedup', $dedup)->exists();
            if ($sudah) continue;
            NotifikasiService::kirim($uid, "[Eskalasi] {$judul}", $pesan, 'pengumuman',
                ['dedup' => $dedup, 'eskalasi' => true, 'route' => '/monitoring'],
                $cfg->event_kode, 'tinggi');
            $n++;
        }
        return $n;
    }

    private function targetEskalasi(array $ke): array
    {
        $ids = [];
        foreach ($ke as $t) {
            if ($t === 'admin')     $ids = array_merge($ids, User::where('role', 'super_admin')->pluck('id')->all());
            if ($t === 'pimpinan')  $ids = array_merge($ids, User::whereIn('id', function ($q) {
                $q->select('user_id')->from('user_peran')
                  ->join('peran', 'peran.id', '=', 'user_peran.peran_id')
                  ->where('peran.kode', 'pimpinan');
            })->pluck('id')->all());
        }
        return array_values(array_unique($ids));
    }

    /** Bucket waktu utk pengingat berulang (0 bila ulang_menit=0 → 1× per window). */
    private function bucket(Carbon $now, Carbon $start, int $ulangMenit): int
    {
        if ($ulangMenit <= 0) return 0;
        return (int) floor($start->diffInMinutes($now) / $ulangMenit);
    }

    private function sedangIzin(int $tpId, string $today): bool
    {
        return PengajuanIzin::where('tenaga_pendidik_id', $tpId)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $today)
            ->where('tanggal_selesai', '>=', $today)
            ->exists();
    }
}
