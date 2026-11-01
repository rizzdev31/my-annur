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
                // Penanda diturunkan dari KODE jenisnya, bukan diserahkan ke
                // pemanggil. Sebelumnya form izin umum tidak menyetelnya, sehingga
                // guru yang memilih "Izin Datang Terlambat" dari daftar tersimpan
                // sebagai izin SEHARI PENUH — lalu ikut hilang dari absensi
                // kegiatan padahal ia tetap masuk kerja.
                'is_datang_terlambat'         => $jenis->kode === 'DATANG_TERLAMBAT',
                'is_sementara'                => $jenis->kode === 'IZIN_SEMENTARA',
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

    /**
     * @param  float|null  $vakasiDinas  Nominal vakasi yang DITETAPKAN penyetuju
     *   untuk tugas tambahan dinas luar (lihat TugasDinasLuarService). null =
     *   pakai tarif Setting Vakasi; 0 = sengaja tanpa vakasi. Diabaikan untuk
     *   pengajuan yang bukan dinas luar.
     */
    public function setujui(
        PengajuanIzin $pengajuan,
        ?string $catatan = null,
        ?float $vakasiDinas = null,
    ): PengajuanIzin {
        if (!$pengajuan->isPending()) {
            throw new \InvalidArgumentException(
                "Pengajuan ini sudah {$pengajuan->status}, tidak bisa disetujui."
            );
        }

        return DB::transaction(function () use ($pengajuan, $catatan, $vakasiDinas) {

            $pengajuan->update([
                'status'            => 'disetujui',
                'catatan_admin'     => $catatan,
                'diproses_oleh'     => Auth::id(),
                'tanggal_keputusan' => now(),
            ]);

            $this->prosesSetelahDisetujui($pengajuan->fresh('jenisPengajuan'), $vakasiDinas);

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

            // Libur pekanan: cabut hari liburnya. Tanpa ini harinya tetap
            // libur padahal pengajuannya sudah dibatalkan — dan guru bisa
            // memakai jatah pekan itu dua kali.
            if ($statusLama === 'disetujui' && $pengajuan->jenisPengajuan?->jadi_libur_tendik) {
                $this->cabutLiburTendik($pengajuan);
            }

            // Rollback absensi jika sudah disetujui
            if ($statusLama === 'disetujui' && $pengajuan->absensi_sudah_diupdate) {
                $this->rollbackAbsensi($pengajuan);
            }

            // Rollback kuota
            if ($statusLama === 'disetujui') {
                $this->rollbackKuota($pengajuan);
            }

            // Tugas tambahan dinas luar yang sudah terbit ikut dibatalkan,
            // supaya vakasinya tidak terbayar untuk dinas yang tidak terjadi.
            // Ditolak bila vakasinya sudah masuk periode berslip — pesannya
            // disampaikan ke admin lewat flash, tanpa menggagalkan pembatalan
            // izinnya.
            if ($pengajuan->tugas_tambahan_id) {
                $pesan = app(\App\Services\TugasDinasLuarService::class)
                    ->batalkanDariPengajuan($pengajuan, $alasan);
                if ($pesan) {
                    session()->flash('warning', $pesan);
                }
            }

            $this->log('batalkan_pengajuan', $pengajuan, ['alasan' => $alasan]);

            return $pengajuan->fresh();
        });
    }

    // ════════════════════════════════════════════════════════════════════════
    // PROSES SETELAH DISETUJUI — inti integrasi
    // ════════════════════════════════════════════════════════════════════════

    private function prosesSetelahDisetujui(PengajuanIzin $pengajuan, ?float $vakasiDinas = null): void
    {
        $jenis = $pengajuan->jenisPengajuan;
        $guru  = $pengajuan->tenagaPendidik;

        // LIBUR PEKANAN berhenti di sini: yang ditulis adalah hari liburnya,
        // bukan absensi izin, dan tidak ada kuota/status kepegawaian yang
        // tersentuh. Lihat tulisLiburTendik() untuk alasannya.
        if ($jenis->jadi_libur_tendik) {
            $this->tulisLiburTendik($pengajuan);

            return;
        }

        // 1. Update absensi harian untuk setiap hari dalam rentang
        $this->generateAbsensi($pengajuan);

        // 1b. DINAS LUAR → terbitkan tugas tambahan otomatis.
        //
        // Dipasang di sini, BUKAN saat guru mengisi formulir: tugas tambahan
        // membawa vakasi, jadi tidak boleh terbit tanpa persetujuan. Gerbangnya
        // sendiri (kategori dinas + sakelar per jenis + idempoten) ada di
        // TugasDinasLuarService; servicenya tidak pernah melempar galat supaya
        // kegagalan menerbitkan tugas tidak membatalkan persetujuan izinnya.
        //
        // `$vakasiDinas` adalah nominal yang ditetapkan penyetuju di layar
        // persetujuan (web admin / PWA pimpinan); null = pakai tarif Setting
        // Vakasi. Jalur auto-approve saat guru mengisi formulir tidak pernah
        // mengirimnya — jadi guru tidak bisa menetapkan vakasinya sendiri.
        app(\App\Services\TugasDinasLuarService::class)
            ->buatDariPengajuan($pengajuan, Auth::id(), $vakasiDinas);

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

        // Izin SEMENTARA & DATANG TERLAMBAT tidak membebaskan kehadiran harian —
        // gurunya tetap masuk kerja. Seluruh bagian lain sistem sudah mengecualikan
        // keduanya; dulu hanya bagian ini yang belum, sehingga hari itu tertimpa
        // jadi "izin sehari penuh" bila persetujuan datang sebelum guru check-in
        // (2 kasus izin sementara + 1 datang terlambat di produksi).
        if ($pengajuan->is_sementara || $pengajuan->is_datang_terlambat) {
            $this->catatKeteranganIzinJam($pengajuan);
            $pengajuan->update(['absensi_sudah_diupdate' => true]);
            return;
        }

        // Libur bisa berlangsung beberapa hari — dulu hanya tanggal AWAL yang cocok,
        // sehingga hari ke-2 dst tetap dibuatkan baris izin.
        $hariLibur = [];
        foreach (HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')->get() as $hl) {
            $s = Carbon::parse($hl->tanggal);
            $e = Carbon::parse($hl->tanggal_selesai ?? $hl->tanggal);
            while ($s->lte($e)) { $hariLibur[] = $s->format('Y-m-d'); $s->addDay(); }
        }

        $hariKerjaMap = [
            'Monday'    => 'senin',
            'Tuesday'   => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday'  => 'kamis',
            'Friday'    => 'jumat',
            'Saturday'  => 'sabtu',
            'Sunday'    => 'ahad',
        ];

        // Hari kerja mengikuti jam kerja GURU pada tanggal itu (shift satpam/asrama,
        // libur mingguan sendiri, dan guru yang dibebaskan absen harian) — bukan
        // jam kerja default global seperti sebelumnya.
        $guruIzin = $pengajuan->tenagaPendidik;

        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);

        foreach ($period as $tanggal) {
            $namaHari   = $hariKerjaMap[$tanggal->format('l')];
            $tanggalStr = $tanggal->format('Y-m-d');

            // Skip hari libur & hari yang memang bukan hari kerja guru ini
            if (in_array($tanggalStr, $hariLibur, true)) continue;
            if ($guruIzin && !$guruIzin->jadwalHari($namaHari, $tanggalStr)) continue;

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

    /**
     * Izin berbasis jam (sementara / datang terlambat): kehadiran harian TIDAK
     * diubah, tapi jejaknya dicatat agar terbaca di laporan — dan statusnya
     * dihitung ulang supaya izin yang disetujui SETELAH guru check-in tetap
     * berlaku (mis. datang terlambat yang disetujui siang hari, dulu tetap
     * tercatat "terlambat").
     */
    private function catatKeteranganIzinJam(PengajuanIzin $pengajuan): void
    {
        $jenis = $pengajuan->jenisPengajuan;
        $jam   = $pengajuan->jam_mulai
            ? ' ' . substr((string) $pengajuan->jam_mulai, 0, 5)
                . ($pengajuan->jam_selesai ? '–' . substr((string) $pengajuan->jam_selesai, 0, 5) : '')
            : '';
        $ket = "{$jenis->nama}{$jam}: {$pengajuan->alasan} (Pengajuan #{$pengajuan->id})";

        $period = CarbonPeriod::create($pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai);
        foreach ($period as $tanggal) {
            $tgl = $tanggal->format('Y-m-d');

            $absensi = AbsensiHarian::where('tenaga_pendidik_id', $pengajuan->tenaga_pendidik_id)
                ->whereDate('tanggal', $tgl)->first();
            if (!$absensi) continue;   // belum check-in → biarkan alur absensi biasa

            $data = ['keterangan' => trim(($absensi->keterangan ? $absensi->keterangan . ' | ' : '') . $ket)];

            if ($absensi->jam_masuk && !$absensi->is_koreksi) {
                $hasil = \App\Services\AbsensiKalkulasiService::hitungStatus(
                    $absensi->jam_masuk, $tgl, $pengajuan->tenagaPendidik
                );
                $data['status']          = $hasil['status'];
                $data['menit_terlambat'] = $hasil['menit_terlambat'];
            }

            $absensi->update($data);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // ROLLBACK ABSENSI — jika pengajuan dibatalkan
    // ════════════════════════════════════════════════════════════════════════

    private function rollbackAbsensi(PengajuanIzin $pengajuan): void
    {
        // Izin berbasis jam tidak pernah membuat baris — ia hanya menempelkan
        // keterangan pada absensi yang sudah ada. Barisnya jangan dihapus
        // (itu kehadiran asli guru), cukup keterangannya yang dicabut.
        if ($pengajuan->is_sementara || $pengajuan->is_datang_terlambat) {
            foreach (AbsensiHarian::where('tenaga_pendidik_id', $pengajuan->tenaga_pendidik_id)
                ->whereBetween('tanggal', [$pengajuan->tanggal_mulai, $pengajuan->tanggal_selesai])
                ->where('keterangan', 'like', "%Pengajuan #{$pengajuan->id}%")->get() as $a) {

                $sisa = collect(explode(' | ', (string) $a->keterangan))
                    ->reject(fn ($b) => str_contains($b, "Pengajuan #{$pengajuan->id}"))
                    ->implode(' | ');

                $data = ['keterangan' => $sisa ?: null];
                if ($a->jam_masuk && !$a->is_koreksi) {
                    $h = \App\Services\AbsensiKalkulasiService::hitungStatus(
                        $a->jam_masuk, Carbon::parse($a->tanggal)->toDateString(), $pengajuan->tenagaPendidik
                    );
                    $data['status']          = $h['status'];
                    $data['menit_terlambat'] = $h['menit_terlambat'];
                }
                $a->update($data);
            }

            $pengajuan->update(['absensi_sudah_diupdate' => false]);
            return;
        }

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

    /**
     * Pagar libur pekanan guru asrama.
     *
     * Semua angkanya dibaca dari setting jenis (hari_diizinkan, maks_per_pekan),
     * bukan ditanam di sini, supaya mengubah kebijakan "Jumat atau Sabtu"
     * menjadi mis. "Ahad" tidak perlu menyentuh kode.
     */
    private function validasiLiburPekanan(
        TenagaPendidik $guru,
        SettingJenisPengajuan $jenis,
        Carbon $mulai,
        Carbon $selesai,
    ): void {
        if (!$guru->boleh_libur_pekanan) {
            throw new \InvalidArgumentException(
                'Anda tidak terdaftar sebagai penerima hak libur pekanan asrama. '
                . 'Hubungi superadmin bila seharusnya berhak.'
            );
        }

        // Satu HARI, bukan rentang: libur pekanan bukan cuti.
        if (!$mulai->isSameDay($selesai)) {
            throw new \InvalidArgumentException('Libur pekanan hanya untuk satu hari.');
        }

        $hariDiizinkan = $jenis->hari_diizinkan ?: [];
        if ($hariDiizinkan && !in_array($mulai->dayOfWeekIso, array_map('intval', $hariDiizinkan), true)) {
            $nama = collect($hariDiizinkan)
                ->map(fn ($h) => Carbon::now()->startOfWeek()->addDays((int) $h - 1)
                    ->locale('id')->isoFormat('dddd'))
                ->implode(' atau ');

            throw new \InvalidArgumentException(
                "Libur pekanan hanya boleh pada hari {$nama}. Tanggal yang dipilih hari "
                . $mulai->locale('id')->isoFormat('dddd') . '.'
            );
        }

        // Sudah libur pada tanggal itu? (bisa dari generator admin atau tukar)
        if (\App\Models\LiburTendik::isLibur($guru->id, $mulai->toDateString())) {
            throw new \InvalidArgumentException(
                'Tanggal ' . $mulai->locale('id')->isoFormat('D MMMM YYYY')
                . ' sudah tercatat sebagai hari libur Anda.'
            );
        }

        // Satu per pekan. Pekannya dihitung Senin–Ahad (ISO), supaya Jumat dan
        // Sabtu selalu berada di pekan yang sama — kalau pekannya dimulai Ahad,
        // Sabtu akan terhitung pekan berikutnya dan jatah jadi dua.
        $maks = (int) ($jenis->maks_per_pekan ?? 0);
        if ($maks > 0) {
            $awalPekan  = $mulai->copy()->startOfWeek(Carbon::MONDAY);
            $akhirPekan = $mulai->copy()->endOfWeek(Carbon::SUNDAY);

            $terpakai = PengajuanIzin::where('tenaga_pendidik_id', $guru->id)
                ->where('setting_jenis_pengajuan_id', $jenis->id)
                ->whereIn('status', ['pending', 'disetujui'])
                ->whereBetween('tanggal_mulai', [$awalPekan->toDateString(), $akhirPekan->toDateString()])
                ->get();

            if ($terpakai->count() >= $maks) {
                $sudah = $terpakai->first();
                throw new \InvalidArgumentException(
                    'Jatah libur pekan ini sudah terpakai pada '
                    . $sudah->tanggal_mulai->locale('id')->isoFormat('dddd, D MMMM YYYY')
                    . '. Batalkan dulu bila ingin memindahkannya.'
                );
            }
        }
    }

    /**
     * Tuliskan libur individu dari pengajuan yang disetujui.
     *
     * Harinya menjadi BUKAN HARI KERJA — bukan hari kerja yang ditinggalkan.
     * Itu sebabnya absensi 'izin' sengaja TIDAK dibuat: kalau ditandai izin,
     * guru kehilangan vakasi kehadiran hari itu dan harinya tetap terhitung
     * hari kerja di payroll. Sebagai libur, auto-alfa melewatinya, kinerja
     * mengeluarkan tanggalnya, payroll tidak menghitungnya sebagai hari kerja,
     * dan ia bukan peserta kegiatan piket.
     *
     * Sesi mengajarnya tetap netral karena SesiMengajarService membaca
     * pengajuan izin yang disetujui (status 'izin', JP 0, netral di kinerja) —
     * jadi tidak ada kode tambahan yang diperlukan untuk pembelajaran.
     */
    private function tulisLiburTendik(PengajuanIzin $pengajuan): void
    {
        $tanggal = $pengajuan->tanggal_mulai->toDateString();

        \App\Models\LiburTendik::updateOrCreate(
            ['tenaga_pendidik_id' => $pengajuan->tenaga_pendidik_id, 'tanggal' => $tanggal],
            [
                'tipe'              => 'pekanan',
                'pengajuan_izin_id' => $pengajuan->id,
                'alasan'            => $pengajuan->alasan ?: 'Libur pekanan asrama',
                'dibuat_oleh'       => Auth::id(),
            ]
        );
    }

    /** Cabut liburnya saat pengajuannya dibatalkan — hanya yang lahir darinya. */
    private function cabutLiburTendik(PengajuanIzin $pengajuan): void
    {
        \App\Models\LiburTendik::where('pengajuan_izin_id', $pengajuan->id)->delete();
    }

    private function validasiBuat(TenagaPendidik $guru, SettingJenisPengajuan $jenis, array $data): void
    {
        $mulai   = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai']);

        // Libur pekanan punya pagarnya sendiri (hak, hari, satu per pekan) dan
        // tidak terkena pagar kuota/dokumen yang berlaku bagi izin biasa.
        if ($jenis->jadi_libur_tendik) {
            $this->validasiLiburPekanan($guru, $jenis, $mulai, $selesai);

            return;
        }

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