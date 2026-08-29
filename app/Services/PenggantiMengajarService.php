<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use App\Models\TenagaPendidik;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guru pengganti saat izin.
 *
 * Alur (keputusan kebijakan):
 *  1. Guru izin (PengajuanIzin disetujui yg mencakup tanggal) menunjuk pengganti
 *     untuk jadwalnya → baris absensi_mengajar (unik per jadwal+tanggal) di-set
 *     status='pengganti', tenaga_pendidik_id tetap guru asli (jejak),
 *     digantikan_oleh=pengganti, jp_terlaksana=0 (belum diajar). LANGSUNG SAH.
 *  2. Pengganti WAJIB absen + bukti (foto/jurnal) → jp_terlaksana diisi penuh.
 *  3. Payroll membayar JP baris 'pengganti' ke `digantikan_oleh` (pengganti).
 *     Guru asli otomatis tidak dibayar (status 'pengganti' di luar daftar bayar).
 *  4. Izin TANPA pengganti → JP hangus (jp_terlaksana=0, status 'izin').
 */
class PenggantiMengajarService
{
    /** Guru izin menunjuk pengganti untuk satu jadwal pada tanggal tertentu. */
    public function tunjukPengganti(
        int $jadwalId, int $guruTpId, int $penggantiTpId, ?Carbon $tanggal, ?string $keterangan
    ): AbsensiMengajar {
        $tanggal ??= TimezoneHelper::today();
        $jadwal = JadwalMengajar::findOrFail($jadwalId);

        if ($jadwal->tenaga_pendidik_id !== $guruTpId) {
            throw new \DomainException('Jadwal ini bukan milik Anda.');
        }
        if ($penggantiTpId === $guruTpId) {
            throw new \DomainException('Pengganti harus guru lain (bukan diri sendiri).');
        }

        // Hari jadwal harus cocok dengan tanggal target.
        if (strtolower($jadwal->hari) !== TimezoneHelper::namaHariDB($tanggal)) {
            throw new \DomainException('Jadwal ini tidak berlangsung pada tanggal tersebut.');
        }

        // Guru harus punya izin disetujui yang mencakup tanggal target.
        $izin = PengajuanIzin::where('tenaga_pendidik_id', $guruTpId)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal->toDateString())
            ->where('tanggal_selesai', '>=', $tanggal->toDateString())
            ->with('jenisPengajuan')->first();
        if (!$izin) {
            throw new \DomainException('Anda belum punya izin yang disetujui pada tanggal tersebut.');
        }

        $pengganti = TenagaPendidik::where('id', $penggantiTpId)->where('is_aktif', true)->first();
        if (!$pengganti) {
            throw new \DomainException('Guru pengganti tidak ditemukan / tidak aktif.');
        }

        // #3 — Pengganti tidak boleh sedang izin pada tanggal tersebut.
        $penggantiIzin = PengajuanIzin::where('tenaga_pendidik_id', $penggantiTpId)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal->toDateString())
            ->where('tanggal_selesai', '>=', $tanggal->toDateString())->exists();
        if ($penggantiIzin) {
            throw new \DomainException('Guru pengganti sedang izin pada tanggal tersebut.');
        }

        // #3 — Pengganti tidak boleh punya jadwal sendiri yang beririsan jam di hari itu.
        $bentrok = JadwalMengajar::where('tenaga_pendidik_id', $penggantiTpId)
            ->where('hari', $jadwal->hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            ->where('jam_mulai', '<', $jadwal->jam_selesai)
            ->where('jam_selesai', '>', $jadwal->jam_mulai)
            ->exists();
        if ($bentrok) {
            throw new \DomainException('Guru pengganti punya jadwal mengajar sendiri di jam yang sama.');
        }

        // Tidak boleh menimpa sesi yang sudah benar-benar terlaksana / libur.
        $existing = AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal)->first();
        if ($existing && in_array($existing->status, ['terlaksana', 'hadir', 'libur'])) {
            throw new \DomainException('Sesi ini sudah tercatat "' . $existing->status . '", tidak bisa ditunjuk pengganti.');
        }
        if ($existing && $existing->status === 'pengganti' && (int) $existing->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sudah mengajar sesi ini.');
        }

        $absen = AbsensiMengajar::updateOrCreate(
            ['jadwal_mengajar_id' => $jadwal->id, 'tanggal' => $tanggal->toDateString()],
            [
                'tenaga_pendidik_id' => $guruTpId,        // jejak guru asli
                'digantikan_oleh'    => $penggantiTpId,   // pengganti yang dibayar
                'status'             => 'pengganti',
                'jp_terlaksana'      => 0,                // belum diajar → belum dibayar
                'materi'             => null,
                'foto_mengajar'      => null,
                'sudah_buka_jurnal'  => false,
                'keterangan'         => 'Pengganti izin (' . ($izin->jenisPengajuan?->nama ?? 'Izin') . ')'
                    . ($keterangan ? ' — ' . $keterangan : ''),
            ]
        );

        // Notifikasi ke guru pengganti (menghormati toggle 'pengganti.ditunjuk').
        if ($pengganti->user) {
            $jadwal->loadMissing('mataPelajaran');
            \App\Services\NotifikasiService::event('pengganti.ditunjuk', [
                'user'  => $pengganti->user,
                'judul' => 'Anda Ditunjuk Guru Pengganti',
                'pesan' => 'Menggantikan mengajar ' . ($jadwal->mataPelajaran?->nama ?? '')
                    . ' ' . ($jadwal->kelas ?? '') . ' pada ' . $tanggal->format('d/m/Y')
                    . ' (' . substr((string) $jadwal->jam_mulai, 0, 5) . '–' . substr((string) $jadwal->jam_selesai, 0, 5) . ').',
                'tipe'  => 'tugas_baru',
                'data'  => ['type' => 'kegiatan', 'route' => '/mengajar'],
                'dedup' => "pengganti-{$jadwal->id}-" . $tanggal->toDateString(),
            ]);
        }

        return $absen;
    }

    /** Daftar kelas pengganti untuk guru pengganti: hari ini + mendatang. */
    public function penggantiSaya(int $penggantiTpId, ?Carbon $tanggal)
    {
        $tanggal ??= TimezoneHelper::today();
        return AbsensiMengajar::where('digantikan_oleh', $penggantiTpId)
            ->where('status', 'pengganti')
            ->whereDate('tanggal', '>=', $tanggal->toDateString()) // hari ini + mendatang
            ->with(['jadwalMengajar.mataPelajaran', 'tenagaPendidik.user:id,name'])
            ->orderBy('tanggal')->get();
    }

    /**
     * Pengganti absen + bukti → JP penuh diberikan (dibayar ke pengganti via payroll).
     * Sekaligus merekam kehadiran santri (1 langkah) + sinkron RamahAnak (telat/alpha),
     * konsisten dengan absen guru biasa & handoff guru piket.
     */
    public function absenPengganti(
        int $absensiId, int $penggantiTpId, string $fotoPath, ?string $materi, ?string $keterangan,
        bool $sudahBukaJurnal, array $absensiSantri = [], ?string $actor = null
    ): AbsensiMengajar {
        $absensi = AbsensiMengajar::with('jadwalMengajar.mataPelajaran')->findOrFail($absensiId);

        if ((int) $absensi->digantikan_oleh !== $penggantiTpId) {
            throw new \DomainException('Tugas pengganti ini bukan milik Anda.');
        }
        if ($absensi->status !== 'pengganti') {
            throw new \DomainException('Sesi ini bukan tugas pengganti aktif.');
        }
        // Penanda "sudah absen" = jam_selesai_aktual sudah terisi (bukan jp>0, karena jp bisa 0 saat telat).
        if (!is_null($absensi->jam_selesai_aktual)) {
            throw new \DomainException('Anda sudah absen untuk sesi pengganti ini.');
        }
        // #5 — Penugasan mendatang hanya bisa diabsen pada tanggalnya (cegah absen lebih awal).
        if ($absensi->tanggal->toDateString() !== TimezoneHelper::today()->toDateString()) {
            throw new \DomainException('Sesi pengganti ini hanya bisa diabsen pada tanggalnya.');
        }

        // Cutoff vakasi: lewat jam_selesai → JP 0 (vakasi hangus), tapi sesi & absen santri tetap.
        $jamSelesaiC = \Carbon\Carbon::parse(
            $absensi->tanggal->toDateString() . ' ' . $absensi->jadwalMengajar?->jam_selesai,
            TimezoneHelper::TZ
        );
        $telat = TimezoneHelper::now()->gt($jamSelesaiC);
        $jp    = $telat ? 0 : ($absensi->jadwalMengajar?->jumlah_jp ?? 0);

        DB::transaction(function () use ($absensi, $jp, $telat, $fotoPath, $materi, $keterangan, $absensiSantri) {
            $absensi->update([
                'jam_mulai_aktual'  => TimezoneHelper::now()->toTimeString(),
                'jam_selesai_aktual'=> TimezoneHelper::now()->toTimeString(),
                'jp_terlaksana'     => $jp, // penuh bila tepat waktu; 0 bila telat (vakasi hangus)
                'status'            => 'pengganti', // tetap pengganti; payroll kredit ke digantikan_oleh
                'foto_mengajar'     => $fotoPath,
                'materi'            => $materi,
                'sudah_buka_jurnal' => true,
                'keterangan'        => trim(($absensi->keterangan ? $absensi->keterangan . ' | ' : '')
                    . 'Diajar pengganti' . ($telat ? ' (TELAT — vakasi hangus)' : '') . ($keterangan ? ': ' . $keterangan : '')),
            ]);

            // Rekam kehadiran santri SEKALI (idempoten — sesi pengganti baru pertama absen).
            if (!empty($absensiSantri)
                && !AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->exists()) {
                foreach ($absensiSantri as $row) {
                    AbsensiSantri::create([
                        'absensi_mengajar_id' => $absensi->id,
                        'santri_id'           => $row['santri_id'],
                        'status'              => $row['status'],
                    ]);
                }
            }
        });

        // Sinkron RamahAnak (telat/alpha) — di luar transaksi, aman bila gagal.
        if (!empty($absensiSantri)) {
            app(EducationTelatSync::class)->pushSesi(
                $absensi->fresh(),
                $absensi->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM',
                'Pengganti: ' . ($actor ?? '—'),
            );

            // Notifikasi WA wali per santri — konsisten dgn absen normal (pengganti pun mengirim).
            $pembelajaran = $absensi->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM';
            $tgl = $absensi->tanggal->toDateString();
            foreach (AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->get() as $as) {
                app(WaService::class)->absenMengajar($as->santri_id, $as->status, $pembelajaran, $tgl, $as->id);
            }
        }

        return $absensi->fresh();
    }

    /**
     * Guru asli membatalkan penunjukan pengganti — selama pengganti BELUM mengajar.
     * Sesi kembali ke "izin tanpa pengganti" (JP tidak dibayar).
     */
    public function batalkanPengganti(int $absensiId, int $guruTpId): AbsensiMengajar
    {
        $absensi = AbsensiMengajar::findOrFail($absensiId);

        if ((int) $absensi->tenaga_pendidik_id !== $guruTpId) {
            throw new \DomainException('Sesi ini bukan milik Anda.');
        }
        if ($absensi->status !== 'pengganti') {
            throw new \DomainException('Sesi ini tidak sedang menunjuk pengganti.');
        }
        if (!is_null($absensi->jam_selesai_aktual) || (int) $absensi->jp_terlaksana > 0) {
            throw new \DomainException('Pengganti sudah mengajar — tidak bisa dibatalkan.');
        }

        $absensi->update([
            'digantikan_oleh' => null,
            'status'          => 'izin',
            'jp_terlaksana'   => 0,
            'keterangan'      => 'Penunjukan pengganti dibatalkan — izin tanpa pengganti (JP tidak dibayar).',
        ]);

        return $absensi->fresh();
    }
}
