<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiSantri;
use App\Models\HariLibur;
use App\Models\JadwalMengajar;
use App\Models\PengajuanIzin;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber aturan "TIDAK TERLAKSANA" untuk sesi mengajar.
 *
 * Definisi (kebijakan pesantren, 16 Sep 2026):
 *   Tidak terlaksana = guru tidak mengisi absen & jurnal sampai batas waktu
 *   (jam selesai + tenggang KebijakanMengajar::GRACE_MENIT).
 *   Akibatnya: JP sesi itu TIDAK diberikan dan tercatat di KINERJA.
 *   TIDAK ada potongan gaji per sesi.
 *
 * Sebelumnya ada dua istilah untuk kejadian yang sama. "Terlewat" hanya label
 * tampilan untuk sesi yang lewat batas tanpa catatan, sedangkan
 * "tidak_terlaksana" baru tersimpan bila guru kebetulan membuka aplikasi atau
 * piket mengisinya. Guru yang tidak membuka aplikasi sama sekali tidak pernah
 * tercatat — lolos dari kinerja — sementara guru yang jujur mengisi terlambat
 * justru tercatat. Layanan ini mencatat setiap sesi yang lewat batas, apa pun
 * jalurnya, sehingga tidak ada lagi sesi yang menggantung tanpa status.
 *
 * Jalur yang wajib memakai aturan di sini: scheduler, halaman absen guru,
 * papan guru piket, monitoring pimpinan, pengingat, dan kinerja.
 */
class SesiMengajarService
{
    /**
     * Pencatatan otomatis hanya berlaku mulai tanggal ini. Hari-hari sebelumnya
     * tidak disentuh scheduler agar kinerja bulan berjalan tidak berubah
     * diam-diam; pengisian mundur hanya lewat opsi --dari yang eksplisit.
     */
    public const BERLAKU_MULAI = '2026-09-16';

    public const KET_OTOMATIS = 'Otomatis: absen & jurnal tidak diisi sampai batas waktu.';

    /**
     * Status yang BUKAN kelalaian guru → tidak dinilai di kinerja mengajar.
     * Libur ditetapkan pesantren; izin sudah disetujui admin. Sesi yang
     * digantikan dikenali dari kolom digantikan_oleh, bukan dari status.
     */
    public const STATUS_NETRAL = ['libur', 'izin'];

    /** Batas akhir sesi dalam format jam, untuk pesan ke guru & piket. */
    public function batasJam(string $tanggal, string $jamSelesai): string
    {
        return KebijakanMengajar::batasAbsenSesi($tanggal, $jamSelesai)->format('H:i');
    }

    /**
     * Status sesi saat ini, termasuk sesi yang belum punya catatan.
     *
     *   belum            : kelas belum dimulai
     *   berlangsung      : kelas sudah mulai, guru belum absen, masih dalam batas
     *   tidak_terlaksana : sudah lewat batas tanpa catatan (scheduler akan menyimpannya)
     *   <status catatan> : bila sudah tercatat
     */
    public function statusLive(?AbsensiMengajar $absensi, string $tanggal, string $jamMulai, string $jamSelesai, ?Carbon $now = null): string
    {
        if ($absensi) return $absensi->status;

        $now ??= TimezoneHelper::now();
        if ($now->gt(KebijakanMengajar::batasAbsenSesi($tanggal, $jamSelesai))) return 'tidak_terlaksana';

        $mulai = Carbon::parse("$tanggal $jamMulai", TimezoneHelper::TZ);
        return $now->gte($mulai) ? 'berlangsung' : 'belum';
    }

    /**
     * Sesi tidak terlaksana yang absensi santrinya belum diisi siapa pun.
     * Inilah yang masih bisa ditolong piket (atau gurunya sendiri).
     */
    public function perluAbsensiSantri(AbsensiMengajar $absensi): bool
    {
        return $absensi->status === 'tidak_terlaksana'
            && !AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->exists();
    }

    /**
     * Sesi yang DINILAI kinerja mengajar seorang guru dalam rentang tanggal.
     *
     * Setiap elemen: ['absensi' => AbsensiMengajar, 'terlaksana' => bool, 'jp_jadwal' => int]
     *
     *   Sesi jadwal sendiri
     *     - dinilai kecuali libur/izin (bukan kelalaian) dan sesi yang dialihkan
     *       ke pengganti (tanggung jawabnya pindah ke pengganti)
     *     - terlaksana = status 'terlaksana'/'hadir'
     *   Sesi sebagai PENGGANTI (inval)
     *     - sudah diabsen pengganti          → terlaksana
     *     - lewat batas tanpa absen pengganti → tidak terlaksana, DIHITUNG ke
     *       pengganti (kebijakan 17 Sep 2026: pengganti yang tidak datang kena kinerja)
     *     - penugasan yang belum waktunya     → belum dinilai
     */
    public function sesiDinilaiKinerja(int $tenagaPendidikId, string $mulai, string $selesai): Collection
    {
        $jpJadwal = fn ($a) => (int) ($a->jadwalMengajar?->jumlah_jp ?? 0);

        $sendiri = AbsensiMengajar::with('jadwalMengajar:id,jumlah_jp')
            ->where('tenaga_pendidik_id', $tenagaPendidikId)
            ->whereNull('digantikan_oleh')
            ->whereNotIn('status', self::STATUS_NETRAL)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get()
            ->map(fn ($a) => [
                'absensi'    => $a,
                'terlaksana' => in_array($a->status, ['terlaksana', 'hadir'], true),
                'jp_jadwal'  => $jpJadwal($a),
            ]);

        $inval = AbsensiMengajar::with('jadwalMengajar:id,jumlah_jp')
            ->where('digantikan_oleh', $tenagaPendidikId)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->where(fn ($q) => $q->where('status', 'tidak_terlaksana')
                ->orWhere(fn ($p) => $p->where('status', 'pengganti')->whereNotNull('jam_selesai_aktual')))
            ->get()
            ->map(fn ($a) => [
                'absensi'    => $a,
                'terlaksana' => $a->status === 'pengganti',
                'jp_jadwal'  => $jpJadwal($a),
            ]);

        return $sendiri->concat($inval)->values();
    }

    /** Hari libur aktif pada tanggal tsb (nasional/pesantren/darurat). */
    public function hariLibur(string $tanggal): ?HariLibur
    {
        return HariLibur::where('is_aktif', true)->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
            ->orderByDesc('is_darurat')->first();
    }

    /**
     * Jadwal yang seharusnya berlangsung pada tanggal tsb — reguler, tahfidz,
     * dan tahsin sekaligus. Guru nonaktif (cuti/resign) dikecualikan: sesinya
     * bukan kelalaian. Jadwal yang baru dibuat setelah tanggal itu juga
     * dikecualikan agar tidak menghukum hari saat jadwalnya belum ada.
     */
    public function jadwalTanggal(string $tanggal, ?int $tenagaPendidikId = null): Collection
    {
        return JadwalMengajar::with(['mataPelajaran:id,nama,tipe', 'kelasRel:id,nama', 'tenagaPendidik.user:id,name'])
            ->where('hari', TimezoneHelper::namaHariDB(Carbon::parse($tanggal)))
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->whereHas('tenagaPendidik', fn ($q) => $q->where('is_aktif', true))
            ->whereDate('created_at', '<=', $tanggal)
            ->when($tenagaPendidikId, fn ($q) => $q->where('tenaga_pendidik_id', $tenagaPendidikId))
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Catat setiap sesi yang sudah lewat batas tanpa catatan.
     *
     *   - guru punya izin disetujui → 'izin'   (JP 0, netral di kinerja)
     *   - selain itu                → 'tidak_terlaksana' (JP 0, dinilai kinerja)
     *
     * Hari libur dilewati seluruhnya; command mengajar:isi-libur yang menanganinya.
     * Aturan izin sengaja sama persis dengan auto-mark lama di halaman absen
     * guru, agar hasilnya tidak bergantung pada jalur mana yang jalan duluan.
     *
     * @return Collection<AbsensiMengajar> catatan yang baru dibuat
     */
    public function tandaiLewatBatas(
        string $tanggal,
        ?int $tenagaPendidikId = null,
        ?Carbon $now = null,
        bool $abaikanTanggalBerlaku = false,
        bool $simulasi = false,
    ): Collection {
        $now ??= TimezoneHelper::now();
        $dibuat = collect();

        if (!$abaikanTanggalBerlaku && $tanggal < self::BERLAKU_MULAI) return $dibuat;
        if ($this->hariLibur($tanggal)) return $dibuat;

        $jadwal = $this->jadwalTanggal($tanggal, $tenagaPendidikId)
            ->filter(fn ($j) => $j->jam_selesai
                && $now->gt(KebijakanMengajar::batasAbsenSesi($tanggal, (string) $j->jam_selesai)));
        if ($jadwal->isEmpty()) return $dibuat;

        $catatan = AbsensiMengajar::whereDate('tanggal', $tanggal)
            ->whereIn('jadwal_mengajar_id', $jadwal->pluck('id'))
            ->get()->keyBy('jadwal_mengajar_id');
        $sudahAda = $catatan->map(fn () => true);

        // Inval yang ditunjuk tapi tidak mengisi sampai batas → tidak terlaksana.
        // digantikan_oleh dibiarkan terisi: itulah yang membuat sesinya dihitung
        // ke kinerja PENGGANTI (keputusan 17 Sep 2026), sementara guru asli yang
        // izin tetap netral. Status 'pengganti' saja tidak cukup untuk memicu
        // kinerja, karena belum-diisi dan belum-waktunya tampak sama.
        foreach ($jadwal as $j) {
            $am = $catatan->get($j->id);
            if (!$am || $am->status !== 'pengganti' || !is_null($am->jam_selesai_aktual)) continue;

            if ($simulasi) {
                $dibuat->push((clone $am)->forceFill(['status' => 'tidak_terlaksana'])->setRelation('jadwalMengajar', $j));
                continue;
            }

            $n = AbsensiMengajar::whereKey($am->id)->where('status', 'pengganti')->whereNull('jam_selesai_aktual')
                ->update([
                    'status'        => 'tidak_terlaksana',
                    'jp_terlaksana' => 0,
                    'keterangan'    => trim(($am->keterangan ? $am->keterangan . ' | ' : '')
                        . 'Otomatis: pengganti tidak mengisi absen & jurnal sampai batas '
                        . $this->batasJam($tanggal, (string) $j->jam_selesai) . '.'),
                    'updated_at'    => now(),
                ]);
            if ($n) $dibuat->push($am->fresh()->setRelation('jadwalMengajar', $j));
        }

        $izin = PengajuanIzin::where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal)->where('tanggal_selesai', '>=', $tanggal)
            ->whereIn('tenaga_pendidik_id', $jadwal->pluck('tenaga_pendidik_id')->unique())
            ->with('jenisPengajuan')->get()->keyBy('tenaga_pendidik_id');

        foreach ($jadwal as $j) {
            if ($sudahAda->has($j->id)) continue;

            $iz = $izin->get($j->tenaga_pendidik_id);
            $data = $iz
                ? ['status' => 'izin', 'keterangan' => 'Otomatis: guru izin ('
                    . ($iz->jenisPengajuan?->nama ?? 'Izin') . ') tanpa pengganti — JP tidak dibayar.']
                : ['status' => 'tidak_terlaksana', 'keterangan' => self::KET_OTOMATIS
                    . ' Batas ' . $this->batasJam($tanggal, (string) $j->jam_selesai) . '.'];

            if ($simulasi) {
                $dibuat->push((new AbsensiMengajar($data + [
                    'jadwal_mengajar_id' => $j->id, 'tenaga_pendidik_id' => $j->tenaga_pendidik_id, 'tanggal' => $tanggal,
                ]))->setRelation('jadwalMengajar', $j));
                continue;
            }

            // Tabel tidak punya unique index (jadwal, tanggal). Kunci baris jadwal
            // lalu periksa ulang agar scheduler dan guru yang mengisi di detik yang
            // sama tidak menghasilkan dua catatan untuk satu sesi.
            $am = DB::transaction(function () use ($j, $tanggal, $data) {
                JadwalMengajar::whereKey($j->id)->lockForUpdate()->first();
                if (AbsensiMengajar::where('jadwal_mengajar_id', $j->id)->whereDate('tanggal', $tanggal)->exists()) {
                    return null;
                }

                return AbsensiMengajar::create($data + [
                    'jadwal_mengajar_id' => $j->id,
                    'tenaga_pendidik_id' => $j->tenaga_pendidik_id,
                    'tanggal'            => $tanggal,
                    'jp_terlaksana'      => 0,
                    'sudah_buka_jurnal'  => false,
                ]);
            });

            if ($am) $dibuat->push($am->setRelation('jadwalMengajar', $j));
        }

        return $dibuat;
    }
}
