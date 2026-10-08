<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\LiburPembelajaran;
use App\Services\LiburMengajarService;
use App\Services\NotifikasiService;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * LIBUR PEMBELAJARAN — pembelajaran diganti kegiatan, orangnya tetap masuk.
 * Tampil sebagai tab di menu Hari Libur (rute ikut prefix hari-libur agar hak
 * aksesnya satu dengan modul Kalender Libur).
 *
 * Seluruh penulisan absensi diserahkan ke LiburMengajarService; controller ini
 * hanya mengurus validasi, pratinjau, dan pesan.
 */
class LiburPembelajaranController extends Controller
{
    /** Aturan yang sama dipakai pratinjau & simpan. */
    private function aturan(): array
    {
        return [
            'nama'               => 'required|string|max:150',
            'tanggal'            => 'required|date',
            'tanggal_selesai'    => 'nullable|date|after_or_equal:tanggal',
            'cakupan'            => 'required|in:semua,kelas,sesi',
            'kelas_ids'          => 'nullable|array',
            'kelas_ids.*'        => 'integer|exists:kelas,id',
            'jadwal_ids'         => 'nullable|array',
            'jadwal_ids.*'       => 'integer|exists:jadwal_mengajar,id',
            'jenis_kelas'        => 'nullable|array',
            'jenis_kelas.*'      => 'in:sekolah,pesantren,tahfidz,tahsin',
            'jam_mulai'          => 'nullable|date_format:H:i,H:i:s',
            'jam_selesai'        => 'nullable|date_format:H:i,H:i:s|after:jam_mulai',
            'materi_jurnal'      => 'nullable|string|max:255',
            'hitung_jp'          => 'boolean',
            'isi_absensi_santri' => 'boolean',
            'keterangan'         => 'nullable|string|max:500',
        ];
    }

    /** Baris kegiatan (belum disimpan) untuk menghitung pratinjau. */
    private function rakit(array $d): LiburPembelajaran
    {
        $lp = new LiburPembelajaran([
            'nama'               => $d['nama'],
            'tanggal'            => $d['tanggal'],
            'tanggal_selesai'    => $d['tanggal_selesai'] ?? null,
            'cakupan'            => $d['cakupan'],
            'jenis_kelas'        => $d['jenis_kelas'] ?? null,
            'jam_mulai'          => $d['jam_mulai'] ?? null,
            'jam_selesai'        => $d['jam_selesai'] ?? null,
            'materi_jurnal'      => $d['materi_jurnal'] ?? null,
            'hitung_jp'          => (bool) ($d['hitung_jp'] ?? true),
            'isi_absensi_santri' => (bool) ($d['isi_absensi_santri'] ?? true),
            'status_santri'      => 'hadir',
            'keterangan'         => $d['keterangan'] ?? null,
            'is_aktif'           => true,
        ]);

        return $lp;
    }

    /** Cakupan harus benar-benar menunjuk sesuatu, kalau tidak hasilnya kosong diam-diam. */
    private function periksaCakupan(array $d): ?string
    {
        if ($d['cakupan'] === 'kelas' && empty($d['kelas_ids'])) {
            return 'Pilih minimal satu kelas untuk cakupan "Kelas terpilih".';
        }
        if ($d['cakupan'] === 'sesi' && empty($d['jadwal_ids'])) {
            return 'Pilih minimal satu sesi untuk cakupan "Sesi terpilih".';
        }
        if (!empty($d['jam_mulai']) xor !empty($d['jam_selesai'])) {
            return 'Jam mulai dan jam selesai harus diisi berdua, atau dikosongkan berdua.';
        }
        return null;
    }

    /**
     * POST pratinjau — menghitung dampak lalu membatalkannya (DB::rollBack),
     * supaya admin melihat angka nyata sebelum menyimpan.
     */
    public function preview(Request $request): JsonResponse
    {
        $d = $request->validate($this->aturan());
        if ($pesan = $this->periksaCakupan($d)) {
            return response()->json(['success' => false, 'message' => $pesan], 422);
        }

        // Pratinjau butuh baris tersimpan (absensi menyimpan id kegiatannya), jadi
        // seluruhnya dijalankan di dalam transaksi luar lalu dibatalkan — tidak ada
        // satu baris pun yang tertinggal, termasuk baris kegiatannya sendiri.
        DB::beginTransaction();
        try {
            $lp = $this->rakit($d);
            $lp->dibuat_oleh = Auth::id();
            $lp->save();
            if (!empty($d['kelas_ids']))  $lp->kelas()->sync($d['kelas_ids']);
            if (!empty($d['jadwal_ids'])) $lp->jadwal()->sync($d['jadwal_ids']);

            $hasil = app(LiburMengajarService::class)->isiPembelajaran($lp->fresh());
        } finally {
            DB::rollBack();
            LiburMengajarService::lupakanPeta();
        }

        $hasil['simulasi'] = true;

        return response()->json(['success' => true, 'data' => $hasil]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->aturan());
        if ($pesan = $this->periksaCakupan($d)) {
            return back()->with('error', $pesan);
        }

        $lp = $this->rakit($d);
        $lp->dibuat_oleh = Auth::id();
        $lp->save();
        if (!empty($d['kelas_ids']))  $lp->kelas()->sync($d['kelas_ids']);
        if (!empty($d['jadwal_ids'])) $lp->jadwal()->sync($d['jadwal_ids']);

        $hasil = app(LiburMengajarService::class)->isiPembelajaran($lp->fresh());
        $this->kabariGuru($lp, $hasil);

        $pesan = "Libur pembelajaran \"{$lp->nama}\" disimpan: "
            . ($hasil['sesi_dibuat'] + $hasil['sesi_diperbarui']) . ' sesi diliburkan';
        if ($hasil['roster_dibuat'])   $pesan .= ", {$hasil['roster_dibuat']} absensi santri terisi";
        if ($hasil['sesi_dilewati'])   $pesan .= ", {$hasil['sesi_dilewati']} sesi dilewati (sudah diajar)";
        if ($hasil['tanggal_terkunci']) {
            $pesan .= '. Dilewati karena periode penggajiannya sudah terkunci atau slipnya '
                . 'sudah terbit: ' . implode(', ', $hasil['tanggal_terkunci']);
        }
        if ($lp->tanggal_akhir->gt(TimezoneHelper::today())) {
            $pesan .= '. Tanggal yang belum tiba akan diisi otomatis setiap hari.';
        }

        return back()->with('success', $pesan . '.');
    }

    /** Batalkan kegiatan & pulihkan sesi (opsional hanya sisa tanggal ke depan). */
    public function batalkan(Request $request, LiburPembelajaran $liburPembelajaran)
    {
        $d = $request->validate([
            'alasan'       => 'nullable|string|max:255',
            'hanya_sisanya'=> 'boolean',
        ]);

        $dari = ($d['hanya_sisanya'] ?? false) ? TimezoneHelper::today()->toDateString() : null;

        $hasil = app(LiburMengajarService::class)->batalkanPembelajaran(
            $liburPembelajaran, $d['alasan'] ?? null, Auth::id(), $dari
        );

        return back()->with('success', "Kegiatan \"{$liburPembelajaran->nama}\" dibatalkan: "
            . "{$hasil['sesi_dihapus']} sesi dikembalikan ke keadaan semula, "
            . "{$hasil['sesi_dipulihkan']} status dipulihkan, "
            . "{$hasil['roster_dihapus']} absensi kegiatan dihapus.");
    }

    /** Opsi sesi untuk cakupan "Sesi terpilih" pada tanggal tsb (JSON). */
    public function opsiSesi(Request $request): JsonResponse
    {
        $d = $request->validate(['tanggal' => 'required|date']);
        $hari = TimezoneHelper::namaHariDB(\Carbon\Carbon::parse($d['tanggal']));

        $rows = \App\Models\JadwalMengajar::with(['kelasRel:id,nama,jenis', 'mataPelajaran:id,nama', 'tenagaPendidik.user:id,name'])
            ->where('hari', $hari)->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->berlakuPada($d['tanggal'])->bukanUjian()
            ->orderBy('jam_mulai')->get()
            ->map(fn ($j) => [
                'id'          => $j->id,
                'kelas'       => $j->kelasRel?->nama ?? $j->kelas ?? '—',
                'jenis_kelas' => $j->kelasRel?->jenis ?? '—',
                'mapel'       => $j->mataPelajaran?->nama ?? '—',
                'guru'        => $j->tenagaPendidik?->user?->name ?? '—',
                'jam'         => substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5),
                'jp'          => (int) $j->jumlah_jp,
            ]);

        return response()->json(['success' => true, 'data' => ['hari' => $hari, 'sesi' => $rows]]);
    }

    /**
     * Beri tahu guru yang sesinya diliburkan — tanpa ini ada guru yang datang
     * lalu bingung sesinya sudah tertulis libur.
     */
    private function kabariGuru(LiburPembelajaran $lp, array $hasil): void
    {
        if (empty($hasil['guru_ids'])) return;

        $users = \App\Models\TenagaPendidik::whereIn('id', $hasil['guru_ids'])
            ->with('user')->get()->map(fn ($t) => $t->user)->filter()->values();
        if ($users->isEmpty()) return;

        $rentang = $lp->durasi_hari > 1
            ? $lp->tanggal->locale('id')->isoFormat('D MMM') . ' – ' . $lp->tanggal_akhir->locale('id')->isoFormat('D MMM YYYY')
            : $lp->tanggal->locale('id')->isoFormat('D MMMM YYYY');

        NotifikasiService::event('pembelajaran.diliburkan', [
            'judul' => 'Pembelajaran diliburkan: ' . $lp->nama,
            'pesan' => "{$rentang} — sesi Anda yang terdampak otomatis tercatat libur"
                . ($lp->hitung_jp ? ' dan JP tetap dihitung.' : '.')
                . ' Anda tetap masuk sesuai jam kerja.',
            'tipe'  => 'pengumuman',
            'data'  => ['route' => '/absen-mengajar'],
            'dedup' => 'libur-pbm-' . $lp->id,
        ], $users->all());
    }
}
