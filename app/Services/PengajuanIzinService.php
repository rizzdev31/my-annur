<?php

namespace App\Services;

use App\Models\PengajuanIzin;
use App\Models\SettingJenisPengajuan;
use App\Models\KuotaIzinTahunan;
use App\Models\AbsensiHarian;
use App\Models\HariLibur;
use App\Models\TenagaPendidik;
use App\Models\Notifikasi;
use App\Models\LogAktivitas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PengajuanIzinService
{
    public function __construct(
        private readonly StatusKepegawaianService $statusService
    ) {}

    // ════════════════════════════════════════════════════════════════════════
    // BUAT PENGAJUAN (dari guru via Flutter atau superadmin)
    // ════════════════════════════════════════════════════════════════════════

    public function buat(
        TenagaPendidik $guru,
        array $data,
        ?object $fileDokumen = null
    ): PengajuanIzin {

        $jenis = SettingJenisPengajuan::findOrFail($data['setting_jenis_pengajuan_id']);

        // ── Validasi bisnis ───────────────────────────────────────────────
        $this->validasiBuat($guru, $jenis, $data);

        $tanggalMulai  = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);
        $hariKerjaEfektif = $this->hitungHariKerjaEfektif($tanggalMulai, $tanggalSelesai);

        return DB::transaction(function () use ($guru, $jenis, $data, $fileDokumen, $tanggalMulai, $tanggalSelesai, $hariKerjaEfektif) {

            // Upload dokumen
            $filePath  = null;
            $namaDokumen = null;
            if ($fileDokumen) {
                $filePath    = $fileDokumen->store("dokumen-pengajuan/{$guru->id}", 'public');
                $namaDokumen = $fileDokumen->getClientOriginalName();
            }

            $pengajuan = PengajuanIzin::create([
                'tenaga_pendidik_id'          => $guru->id,
                'setting_jenis_pengajuan_id'  => $jenis->id,
                'tanggal_mulai'               => $tanggalMulai,
                'tanggal_selesai'             => $tanggalSelesai,
                'jumlah_hari'                 => $hariKerjaEfektif,
                'alasan'                      => $data['alasan'],
                'file_dokumen'                => $filePath,
                'nama_dokumen'                => $namaDokumen,
                'status'                      => $jenis->auto_approve ? 'disetujui' : 'pending',
                'diproses_oleh'               => $jenis->auto_approve ? null : null,
                'tanggal_keputusan'           => $jenis->auto_approve ? now() : null,
            ]);

            // Auto approve — langsung proses absensi
            if ($jenis->auto_approve) {
                $this->prosesSetelahDisetujui($pengajuan);
            } else {
                // Kirim notifikasi ke superadmin
                $this->kirimNotifKeAdmin($pengajuan);
            }

            $this->log('buat_pengajuan', $pengajuan);

            return $pengajuan->fresh(['jenisPengajuan', 'tenagaPendidik.user']);
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // SETUJUI PENGAJUAN (oleh superadmin)
    // ════════════════════════════════════════════════════════════════════════

    public function setujui(PengajuanIzin $pengajuan, ?string $catatan = null): PengajuanIzin
    {
        if (!$pengajuan->isPending()) {
            throw new \InvalidArgumentException(
                "Pengajuan ini sudah {$pengajuan->status}, tidak bisa disetujui."
            );
        }

        return DB::transaction(function () use ($pengajuan, $catatan) {

            $pengajuan->update([
                'status'            => 'disetujui',
                'catatan_admin'     => $catatan,
                'diproses_oleh'     => Auth::id(),
                'tanggal_keputusan' => now(),
            ]);

            $this->prosesSetelahDisetujui($pengajuan->fresh('jenisPengajuan'));

            // Notifikasi ke guru
            $this->kirimNotifKeGuru($pengajuan, 'disetujui');

            $this->log('setujui_pengajuan', $pengajuan);

            return $pengajuan->fresh(['jenisPengajuan', 'tenagaPendidik.user']);
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // TOLAK PENGAJUAN (oleh superadmin)
    // ════════════════════════════════════════════════════════════════════════

    public function tolak(PengajuanIzin $pengajuan, string $catatan): PengajuanIzin
    {
        if (!$pengajuan->isPending()) {
            throw new \InvalidArgumentException(
                "Pengajuan ini sudah {$pengajuan->status}, tidak bisa ditolak."
            );
        }

        return DB::transaction(function () use ($pengajuan, $catatan) {

            $pengajuan->update([
                'status'            => 'ditolak',
                'catatan_admin'     => $catatan,
                'diproses_oleh'     => Auth::id(),
                'tanggal_keputusan' => now(),
            ]);

            $this->kirimNotifKeGuru($pengajuan, 'ditolak');
            $this->log('tolak_pengajuan', $pengajuan);

            return $pengajuan->fresh(['jenisPengajuan', 'tenagaPendidik.user']);
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // BATALKAN PENGAJUAN (oleh guru atau superadmin)
    // ════════════════════════════════════════════════════════════════════════

    public function batalkan(PengajuanIzin $pengajuan, string $alasan): PengajuanIzin
    {
        if ($pengajuan->status === 'dibatalkan') {
            throw new \InvalidArgumentException('Pengajuan sudah dibatalkan.');
        }

        if ($pengajuan->isDisetujui() && $pengajuan->tanggal_mulai->isPast()) {
            throw new \InvalidArgumentException(
                'Pengajuan yang sudah berjalan tidak bisa dibatalkan. Hubungi superadmin.'
            );
        }

        return DB::transaction(function () use ($pengajuan, $alasan) {

            $statusLama = $pengajuan->status;

            $pengajuan->update([
                'status'        => 'dibatalkan',
                'catatan_admin' => "Dibatalkan: {$alasan}",
            ]);

            // Rollback absensi jika sudah disetujui
            if ($statusLama === 'disetujui' && $pengajuan->absensi_sudah_diupdate) {
                $this->rollbackAbsensi($pengajuan);
            }

            // Rollback kuota
            if ($statusLama === 'disetujui') {
                $this->rollbackKuota($pengajuan);
            }

            $this->log('batalkan_pengajuan', $pengajuan, ['alasan' => $alasan]);

            return $pengajuan->fresh();
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // PROSES SETELAH DISETUJUI — inti integrasi
    // ════════════════════════════════════════════════════════════════════════

    private function prosesSetelahDisetujui(PengajuanIzin $pengajuan): void
    {
        $jenis = $pengajuan->jenisPengajuan;
        $guru  = $pengajuan->tenagaPendidik;

        // 1. Update absensi harian untuk setiap hari dalam rentang
        $this->generateAbsensi($pengajuan);

        // 2. Kurangi kuota jika ada
        $this->kurangiKuota($pengajuan);

        // 3. Update status kepegawaian jika diperlukan
        if (
            $jenis->update_status_kepegawaian &&
            $jenis->status_kepegawaian_tujuan &&
            $pengajuan->jumlah_hari >= $jenis->min_hari_untuk_update_status
        ) {
            try {
                $this->statusService->ubahStatus($guru, $jenis->status_kepegawaian_tujuan, [
                    'tanggal_efektif' => $pengajuan->tanggal_mulai->format('Y-m-d'),
                    'tanggal_kembali' => $pengajuan->tanggal_selesai->format('Y-m-d'),
                    'alasan'          => "Auto: Pengajuan {$jenis->nama} disetujui (ID: {$pengajuan->id})",
                ]);

                $pengajuan->update(['status_kepegawaian_diupdate' => true]);
            } catch (\Throwable $e) {
                // Log tapi jangan gagalkan transaction utama
                \Log::warning("Gagal update status kepegawaian untuk pengajuan {$pengajuan->id}: " . $e->getMessage());
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // GENERATE ABSENSI — loop hari kerja efektif
    // ════════════════════════════════════════════════════════════════════════

    private function generateAbsensi(PengajuanIzin $pengajuan): void
    {
        $jenis         = $pengajuan->jenisPengajuan;
        $statusAbsensi = $pengajuan->getStatusAbsensi();

        $hariLibur = HariLibur::whereBetween('tanggal', [
            $pengajuan->tanggal_mulai,
            $pengajuan->tanggal_selesai,
        ])->where('pengaruh_gaji', true)
          ->pluck('tanggal')
          ->map(fn($t) => Carbon::parse($t)->format('Y-m-d'))
          ->toArray();

        $hariKerjaMap = [
            'Monday'    => 'senin',
            'Tuesday'   => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday'  => 'kamis',
            'Friday'    => 'jumat',
            'Saturday'  => 'sabtu',
            'Sunday'    => 'ahad',
        ];

        $settingJamKerja = \App\Models\SettingJamKerja::where('is_default', true)->first();
        $hariKerja = $settingJamKerja
            ? ($settingJamKerja->hari_kerja ?? ['senin','selasa','rabu','kamis','jumat','sabtu'])
            : ['senin','selasa','rabu','kamis','jumat','sabtu'];

        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);

        foreach ($period as $tanggal) {
            $namaHari   = $hariKerjaMap[$tanggal->format('l')];
            $tanggalStr = $tanggal->format('Y-m-d');

            // Skip hari libur & non-hari kerja
            if (!in_array($namaHari, $hariKerja) || in_array($tanggalStr, $hariLibur)) {
                continue;
            }

            AbsensiHarian::updateOrCreate(
                [
                    'tenaga_pendidik_id' => $pengajuan->tenaga_pendidik_id,
                    'tanggal'            => $tanggalStr,
                ],
                [
                    'status'         => $statusAbsensi,
                    'keterangan'     => "{$jenis->nama}: {$pengajuan->alasan} (Pengajuan #{$pengajuan->id})",
                    'is_koreksi'     => true,
                    'dikoreksi_oleh' => Auth::id() ?? $pengajuan->diproses_oleh,
                ]
            );
        }

        $pengajuan->update(['absensi_sudah_diupdate' => true]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ROLLBACK ABSENSI — jika pengajuan dibatalkan
    // ════════════════════════════════════════════════════════════════════════

    private function rollbackAbsensi(PengajuanIzin $pengajuan): void
    {
        // Hapus absensi yang dibuat dari pengajuan ini
        AbsensiHarian::where('tenaga_pendidik_id', $pengajuan->tenaga_pendidik_id)
            ->whereBetween('tanggal', [$pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai])
            ->where('keterangan', 'like', "%Pengajuan #{$pengajuan->id}%")
            ->delete();

        $pengajuan->update(['absensi_sudah_diupdate' => false]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // KUOTA
    // ════════════════════════════════════════════════════════════════════════

    private function kurangiKuota(PengajuanIzin $pengajuan): void
    {
        $jenis = $pengajuan->jenisPengajuan;
        if (!$jenis->kuota_per_tahun) return;

        $tahun = $pengajuan->tanggal_mulai->year;

        // Lock row agar tidak bisa overflow kuota jika dua approval simultan
        $kuota = KuotaIzinTahunan::where('tenaga_pendidik_id', $pengajuan->tenaga_pendidik_id)
            ->where('setting_jenis_pengajuan_id', $jenis->id)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->first();

        if (!$kuota) {
            $kuota = KuotaIzinTahunan::create([
                'tenaga_pendidik_id'         => $pengajuan->tenaga_pendidik_id,
                'setting_jenis_pengajuan_id' => $jenis->id,
                'tahun'                      => $tahun,
                'kuota_total'                => $jenis->kuota_per_tahun,
                'terpakai'                   => 0,
            ]);
        }

        // Validasi ulang sisa kuota setelah lock — cegah overflow concurrent
        $sisaKuota = $kuota->kuota_total - $kuota->terpakai;
        if ($pengajuan->jumlah_hari > $sisaKuota) {
            throw new \InvalidArgumentException(
                "Kuota {$jenis->nama} tidak mencukupi setelah divalidasi ulang. Sisa: {$sisaKuota} hari."
            );
        }

        $kuota->increment('terpakai', $pengajuan->jumlah_hari);
    }

    private function rollbackKuota(PengajuanIzin $pengajuan): void
    {
        $jenis = $pengajuan->jenisPengajuan;
        if (!$jenis->kuota_per_tahun) return;

        $tahun = $pengajuan->tanggal_mulai->year;

        KuotaIzinTahunan::where('tenaga_pendidik_id', $pengajuan->tenaga_pendidik_id)
            ->where('setting_jenis_pengajuan_id', $jenis->id)
            ->where('tahun', $tahun)
            ->decrement('terpakai', $pengajuan->jumlah_hari);
    }

    // ════════════════════════════════════════════════════════════════════════
    // VALIDASI
    // ════════════════════════════════════════════════════════════════════════

    private function validasiBuat(TenagaPendidik $guru, SettingJenisPengajuan $jenis, array $data): void
    {
        $mulai   = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai']);

        // Cek dokumen wajib
        if ($jenis->butuh_dokumen && empty($data['file_dokumen']) && !isset($data['_file_object'])) {
            throw new \InvalidArgumentException(
                "Pengajuan {$jenis->nama} memerlukan dokumen pendukung: {$jenis->keterangan_dokumen}"
            );
        }

        // Cek min hari sebelumnya
        if ($jenis->min_hari_pengajuan_sebelumnya > 0) {
            $minDate = now()->addDays($jenis->min_hari_pengajuan_sebelumnya)->startOfDay();
            if ($mulai->lt($minDate)) {
                throw new \InvalidArgumentException(
                    "Pengajuan {$jenis->nama} harus diajukan minimal {$jenis->min_hari_pengajuan_sebelumnya} hari sebelumnya."
                );
            }
        }

        // Cek max hari
        $jumlahHari = $this->hitungHariKerjaEfektif($mulai, $selesai);
        if ($jumlahHari > $jenis->max_hari_per_pengajuan) {
            throw new \InvalidArgumentException(
                "Maksimal pengajuan {$jenis->nama} adalah {$jenis->max_hari_per_pengajuan} hari kerja."
            );
        }

        // Cek kuota tahunan
        if ($jenis->kuota_per_tahun) {
            $tahun = $mulai->year;
            $kuota = KuotaIzinTahunan::where('tenaga_pendidik_id', $guru->id)
                ->where('setting_jenis_pengajuan_id', $jenis->id)
                ->where('tahun', $tahun)
                ->first();

            $terpakai = $kuota?->terpakai ?? 0;

            // Hitung juga pending yang belum diproses
            $pendingHari = PengajuanIzin::where('tenaga_pendidik_id', $guru->id)
                ->where('setting_jenis_pengajuan_id', $jenis->id)
                ->where('status', 'pending')
                ->whereYear('tanggal_mulai', $tahun)
                ->sum('jumlah_hari');

            if (($terpakai + $pendingHari + $jumlahHari) > $jenis->kuota_per_tahun) {
                $sisa = $jenis->kuota_per_tahun - $terpakai - $pendingHari;
                throw new \InvalidArgumentException(
                    "Kuota {$jenis->nama} tahun {$tahun} tidak mencukupi. Sisa: {$sisa} hari."
                );
            }
        }

        // Cek tidak ada pengajuan overlap
        $overlap = PengajuanIzin::where('tenaga_pendidik_id', $guru->id)
            ->whereIn('status', ['pending', 'disetujui'])
            ->where(function ($q) use ($mulai, $selesai) {
                $q->whereBetween('tanggal_mulai', [$mulai, $selesai])
                  ->orWhereBetween('tanggal_selesai', [$mulai, $selesai])
                  ->orWhere(fn($q2) =>
                      $q2->where('tanggal_mulai', '<=', $mulai)
                         ->where('tanggal_selesai', '>=', $selesai)
                  );
            })
            ->exists();

        if ($overlap) {
            throw new \InvalidArgumentException(
                'Terdapat pengajuan lain yang bentrok dengan tanggal yang dipilih.'
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER
    // ════════════════════════════════════════════════════════════════════════

    public function hitungHariKerjaEfektif(Carbon $mulai, Carbon $selesai): int
    {
        $hariLibur = HariLibur::whereBetween('tanggal', [$mulai, $selesai])
            ->where('pengaruh_gaji', true)
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->format('Y-m-d'))
            ->toArray();

        $settingJamKerja = \App\Models\SettingJamKerja::where('is_default', true)->first();
        $hariKerja = $settingJamKerja
            ? ($settingJamKerja->hari_kerja ?? ['senin','selasa','rabu','kamis','jumat','sabtu'])
            : ['senin','selasa','rabu','kamis','jumat','sabtu'];

        $hariKerjaMap = [
            'Monday'    => 'senin', 'Tuesday'  => 'selasa',
            'Wednesday' => 'rabu',  'Thursday' => 'kamis',
            'Friday'    => 'jumat', 'Saturday' => 'sabtu', 'Sunday' => 'ahad',
        ];

        $count  = 0;
        $period = CarbonPeriod::create($mulai, $selesai);

        foreach ($period as $tanggal) {
            $namaHari   = $hariKerjaMap[$tanggal->format('l')];
            $tanggalStr = $tanggal->format('Y-m-d');
            if (in_array($namaHari, $hariKerja) && !in_array($tanggalStr, $hariLibur)) {
                $count++;
            }
        }

        return $count;
    }

    public function getSisaKuota(TenagaPendidik $guru, SettingJenisPengajuan $jenis, int $tahun): int
    {
        if (!$jenis->kuota_per_tahun) return 999;

        $kuota = KuotaIzinTahunan::where('tenaga_pendidik_id', $guru->id)
            ->where('setting_jenis_pengajuan_id', $jenis->id)
            ->where('tahun', $tahun)
            ->first();

        return $kuota ? $kuota->sisa : $jenis->kuota_per_tahun;
    }

    private function kirimNotifKeAdmin(PengajuanIzin $pengajuan): void
    {
        // Lewat gerbang event → menghormati toggle 'izin.diajukan' di Setting Notifikasi.
        \App\Services\NotifikasiService::event('izin.diajukan', [
            'judul' => 'Pengajuan Baru: ' . $pengajuan->jenisPengajuan->nama,
            'pesan' => "{$pengajuan->tenagaPendidik->user->name} mengajukan {$pengajuan->jenisPengajuan->nama} " .
                       "({$pengajuan->tanggal_mulai->format('d/m')} - {$pengajuan->tanggal_selesai->format('d/m/Y')})",
            'tipe'  => 'pengumuman',
            'data'  => ['pengajuan_id' => $pengajuan->id, 'type' => 'pengajuan_izin'],
            'dedup' => "izin-ajukan-{$pengajuan->id}",
        ]);
    }

    private function kirimNotifKeGuru(PengajuanIzin $pengajuan, string $keputusan): void
    {
        \App\Services\NotifikasiService::event('izin.diputuskan', [
            'user'  => $pengajuan->tenagaPendidik->user,
            'judul' => 'Pengajuan ' . ucfirst($keputusan),
            'pesan' => "Pengajuan {$pengajuan->jenisPengajuan->nama} Anda " .
                       "({$pengajuan->tanggal_mulai->format('d/m')} - {$pengajuan->tanggal_selesai->format('d/m/Y')}) " .
                       "telah {$keputusan}." .
                       ($pengajuan->catatan_admin ? " Catatan: {$pengajuan->catatan_admin}" : ''),
            'tipe'  => 'pengumuman',
            'data'  => ['pengajuan_id' => $pengajuan->id, 'type' => 'pengajuan_izin'],
            'dedup' => "izin-putus-{$pengajuan->id}-{$keputusan}",
        ]);
    }

    private function log(string $aksi, PengajuanIzin $pengajuan, array $extra = []): void
    {
        LogAktivitas::create([
            'user_id'    => Auth::id(),
            'aksi'       => $aksi,
            'model_type' => PengajuanIzin::class,
            'model_id'   => $pengajuan->id,
            'keterangan' => $extra['alasan'] ?? null,
            'ip_address' => request()->ip(),
        ]);
    }
}