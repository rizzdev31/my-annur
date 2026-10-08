<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ujian;
use App\Models\UjianSesi;
use App\Services\UjianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * UJIAN SEKOLAH (admin). Seluruh aturan ada di UjianService — controller ini
 * hanya memvalidasi masukan dan menyusun pesan.
 *
 * Inval penjaga sengaja HANYA dari sini (keputusan 8 Okt 2026): guru tidak
 * mencari penggantinya sendiri untuk tugas jaga, panitia yang mengatur.
 */
class UjianController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?: now()->year);

        $daftar = Ujian::with(['sesi.kelas:id,nama', 'sesi.mataPelajaran:id,nama',
                'sesi.penjaga.user:id,name', 'sesi.jadwal:id,ujian_sesi_id', 'dibuatOleh:id,name'])
            ->whereYear('tanggal_mulai', $tahun)
            ->orderByDesc('tanggal_mulai')->get()
            ->map(fn ($u) => $this->paketPayload($u));

        return Inertia::render('Admin/SmartEducation/Ujian/Index', [
            'ujian'      => $daftar,
            'tahun'      => $tahun,
            'kelasOpsi'  => Kelas::aktif()->reguler()->orderBy('nama')
                ->get(['id', 'nama', 'jenis'])
                ->map(fn ($k) => ['id' => $k->id, 'nama' => $k->nama, 'jenis' => $k->jenis]),
            'mapelOpsi'  => MataPelajaran::aktif()
                ->where(fn ($q) => $q->where('tipe', 'reguler')->orWhereNull('tipe'))
                ->orderBy('nama')->get(['id', 'nama', 'kode']),
            'summary'    => [
                'paket'          => $daftar->count(),
                'sesi'           => $daftar->sum(fn ($u) => count($u['sesi'])),
                'belum_penjaga'  => $daftar->sum(fn ($u) => $u['belum_penjaga']),
                'belum_diabsen'  => $daftar->sum(fn ($u) => $u['belum_diabsen']),
            ],
        ]);
    }

    private function paketPayload(Ujian $u): array
    {
        $sesi = $u->sesi->map(function (UjianSesi $s) {
            $am = $s->absensi();
            return [
                'id'          => $s->id,
                'tanggal'     => $s->tanggal->toDateString(),
                'tanggal_label' => $s->tanggal->locale('id')->isoFormat('ddd, D MMM'),
                'jam'         => $s->jamLabel(),
                'jam_mulai'   => substr((string) $s->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $s->jam_selesai, 0, 5),
                'kelas'       => $s->kelas?->nama ?? '—',
                'kelas_id'    => $s->kelas_id,
                'mapel'       => $s->mataPelajaran?->nama ?? '—',
                'ruangan'     => $s->ruangan,
                'jumlah_jp'   => $s->jumlah_jp,
                'penjaga'     => $s->penjaga?->user?->name,
                'penjaga_id'  => $s->penjaga_id,
                'nominal_vakasi' => (float) $s->nominal_vakasi,
                'vakasi_dibayar' => $s->vakasi_dibayar,
                // Keadaan sesi: belum berpenjaga → belum diabsen → dijaga / di-inval
                'status'      => $this->statusSesi($s, $am),
                'inval_oleh'  => $am?->digantikanOleh?->user?->name,
                'sudah_dijaga'=> $s->sudahDijaga(),
                'catatan'     => $s->catatan,
            ];
        })->values();

        return [
            'id'              => $u->id,
            'nama'            => $u->nama,
            'tanggal_mulai'   => $u->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $u->tanggal_akhir->toDateString(),
            'rentang'         => $u->durasi_hari > 1
                ? $u->tanggal_mulai->locale('id')->isoFormat('D MMM') . ' – ' . $u->tanggal_akhir->locale('id')->isoFormat('D MMM YYYY')
                : $u->tanggal_mulai->locale('id')->isoFormat('D MMMM YYYY'),
            'durasi_hari'     => $u->durasi_hari,
            'keterangan'      => $u->keterangan,
            'is_aktif'        => $u->is_aktif,
            'is_dibatalkan'   => $u->is_dibatalkan,
            'alasan_pembatalan' => $u->alasan_pembatalan,
            'libur_aktif'     => $u->liburPembelajaran && !$u->liburPembelajaran->is_dibatalkan,
            'dibuat_oleh'     => $u->dibuatOleh?->name,
            'sesi'            => $sesi,
            'belum_penjaga'   => $sesi->whereNull('penjaga_id')->count(),
            'belum_diabsen'   => $sesi->where('sudah_dijaga', false)->whereNotNull('penjaga_id')->count(),
            'tanggal_cakupan' => $u->tanggalCakupan(),
        ];
    }

    private function statusSesi(UjianSesi $s, $am): string
    {
        if (!$s->penjaga_id)            return 'belum_penjaga';
        if ($am && $am->digantikan_oleh) return $s->sudahDijaga() ? 'dijaga_inval' : 'inval';
        if ($s->sudahDijaga())           return 'dijaga';
        if ($am && $am->status === 'tidak_terlaksana') return 'tidak_dijaga';

        return 'ditugaskan';
    }

    // ══════════════════════════════════════════════════════════════════════
    // Paket
    // ══════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $d = $request->validate([
            'nama'            => 'required|string|max:150',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'nullable|string|max:500',
        ]);

        $ujian = app(UjianService::class)->buat($d, Auth::id());

        return back()->with('success', "Paket ujian \"{$ujian->nama}\" dibuat. "
            . 'Susun jadwal sesinya, lalu tunjuk penjaganya.');
    }

    public function batalkan(Request $request, Ujian $ujian)
    {
        $d = $request->validate(['alasan' => 'nullable|string|max:255']);

        $h = app(UjianService::class)->batalkan($ujian, $d['alasan'] ?? null, Auth::id());

        $pesan = "Paket \"{$ujian->nama}\" dibatalkan: {$h['sesi']} sesi dibersihkan";
        if ($h['absensi']) $pesan .= ", {$h['absensi']} absensi dihapus";
        if ($h['terbayar']) $pesan .= ". {$h['terbayar']} sesi dipertahankan karena vakasinya sudah dibayar";

        return back()->with('success', $pesan . '. Pembelajaran reguler dipulihkan.');
    }

    /** Matikan pembelajaran reguler kelas-kelas yang berujian (atau perbarui cakupannya). */
    public function sinkronLibur(Ujian $ujian)
    {
        $libur = app(UjianService::class)->sinkronLiburPembelajaran($ujian);
        if (!$libur) {
            return back()->with('error', 'Belum ada sesi ujian — tidak ada kelas yang perlu diliburkan.');
        }

        return back()->with('success', 'Pembelajaran reguler kelas peserta ujian dimatikan. '
            . 'Absensi santri hari itu dicatat lewat sesi ujian, bukan lewat libur, '
            . 'supaya kehadirannya tidak terhitung dua kali.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Sesi
    // ══════════════════════════════════════════════════════════════════════

    public function storeSesi(Request $request, Ujian $ujian)
    {
        $d = $request->validate([
            'tanggal'           => 'required|date',
            'jam_mulai'         => 'required|date_format:H:i,H:i:s',
            'jam_selesai'       => 'required|date_format:H:i,H:i:s',
            'kelas_id'          => 'required|exists:kelas,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'ruangan'           => 'nullable|string|max:50',
            'penjaga_id'        => 'nullable|exists:tenaga_pendidik,id',
            'catatan'           => 'nullable|string|max:255',
        ]);

        try {
            app(UjianService::class)->tambahSesi($ujian, $d);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sesi ujian ditambahkan.');
    }

    /** Susun banyak sesi sekaligus: slot jam × kelas × tanggal. */
    public function generateSesi(Request $request, Ujian $ujian)
    {
        $d = $request->validate([
            'kelas_ids'              => 'required|array|min:1',
            'kelas_ids.*'            => 'integer|exists:kelas,id',
            'slot'                   => 'required|array|min:1',
            'slot.*.tanggal'         => 'required|date',
            'slot.*.jam_mulai'       => 'required|date_format:H:i,H:i:s',
            'slot.*.jam_selesai'     => 'required|date_format:H:i,H:i:s',
            'slot.*.mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'slot.*.ruangan'         => 'nullable|string|max:50',
        ]);

        $h = app(UjianService::class)->generate($ujian, $d['slot'], $d['kelas_ids']);

        $pesan = "{$h['dibuat']} sesi dibuat";
        if ($h['dilewati']) {
            $pesan .= ", {$h['dilewati']} dilewati: " . implode(' · ', array_slice($h['alasan'], 0, 4))
                . (count($h['alasan']) > 4 ? ' …' : '');
        }

        return back()->with($h['dibuat'] ? 'success' : 'error', $pesan . '.');
    }

    public function destroySesi(UjianSesi $ujianSesi)
    {
        try {
            app(UjianService::class)->hapusSesi($ujianSesi);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sesi ujian dihapus beserta jadwal & absensinya.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Penjaga
    // ══════════════════════════════════════════════════════════════════════

    /** Kandidat penjaga + alasan bila tidak bisa (JSON). */
    public function calonPenjaga(UjianSesi $ujianSesi): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'sesi' => [
                'tanggal' => $ujianSesi->tanggal->toDateString(),
                'jam'     => $ujianSesi->jamLabel(),
                'kelas'   => $ujianSesi->kelas?->nama,
                'mapel'   => $ujianSesi->mataPelajaran?->nama,
                'penjaga_id' => $ujianSesi->penjaga_id,
            ],
            'calon' => app(UjianService::class)->calonPenjaga($ujianSesi),
        ]]);
    }

    public function tunjukPenjaga(Request $request, UjianSesi $ujianSesi)
    {
        $d = $request->validate(['penjaga_id' => 'required|exists:tenaga_pendidik,id']);

        try {
            $sesi = app(UjianService::class)->tunjukPenjaga($ujianSesi, (int) $d['penjaga_id']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penjaga ditetapkan: ' . ($sesi->penjaga?->user?->name ?? '—')
            . '. Sesi ujian kini muncul di aplikasi guru untuk diabsen.');
    }

    public function lepasPenjaga(UjianSesi $ujianSesi)
    {
        try {
            app(UjianService::class)->lepasPenjaga($ujianSesi);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penjaga dilepas — sesi ini kembali tanpa penjaga.');
    }

    /** Sebar penjaga otomatis & merata ke sesi yang belum berpenjaga. */
    public function sebarPenjaga(Ujian $ujian)
    {
        $h = app(UjianService::class)->sebarPenjaga($ujian);

        $pesan = "{$h['ditunjuk']} sesi mendapat penjaga";
        if ($h['gagal']) {
            $pesan .= ", {$h['gagal']} gagal: " . implode(' · ', array_slice($h['alasan'], 0, 3))
                . (count($h['alasan']) > 3 ? ' …' : '');
        }

        return back()->with($h['ditunjuk'] ? 'success' : 'error', $pesan . '.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // Inval penjaga
    // ══════════════════════════════════════════════════════════════════════

    public function inval(Request $request, UjianSesi $ujianSesi)
    {
        $d = $request->validate([
            'pengganti_id' => 'required|exists:tenaga_pendidik,id',
            'alasan'       => 'nullable|string|max:255',
        ]);

        try {
            app(UjianService::class)->invalPenjaga($ujianSesi, (int) $d['pengganti_id'], $d['alasan'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $nama = \App\Models\TenagaPendidik::find($d['pengganti_id'])?->user?->name ?? 'Guru pengganti';

        return back()->with('success', "Sesi dialihkan ke {$nama}. "
            . 'Vakasi jaga ujian ikut pindah ke pengganti setelah ia mengabsen.');
    }

    public function batalInval(UjianSesi $ujianSesi)
    {
        try {
            app(UjianService::class)->batalkanInval($ujianSesi);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Inval dibatalkan — sesi kembali menjadi tugas penjaga yang ditunjuk.');
    }
}
