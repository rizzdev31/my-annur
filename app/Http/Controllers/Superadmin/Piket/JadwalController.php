<?php

namespace App\Http\Controllers\Superadmin\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketJadwal;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Penunjukan Guru Piket per hari. Window aktif piket (boleh menilai/mengabsen)
 * mengikuti jam absen masuk–pulang piket itu sendiri — diberlakukan saat input (Tahap 4).
 * Boleh >1 piket/hari (mis. shift pagi & sore); 1 guru hanya 1 penugasan/tanggal.
 */
class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        $jadwal = PiketJadwal::with('tenagaPendidik.user:id,name')
            ->withCount('penilaian')
            ->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')->get()
            ->map(fn($j) => [
                'id'               => $j->id,
                'tanggal'          => $j->tanggal->toDateString(),
                'guru'             => $j->tenagaPendidik?->user?->name ?? '—',
                'catatan_harian'   => $j->catatan_harian,
                'jumlah_penilaian' => $j->penilaian_count,
                'vakasi_dibayar'   => $j->vakasi_dibayar,
            ]);

        return Inertia::render('Admin/Piket/Jadwal/Index', [
            'jadwal'   => $jadwal,
            'filter'   => ['bulan' => $bulan, 'tahun' => $tahun],
            'guruOpsi' => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])
                ->filter(fn($g) => $g['nama'] !== '—')->sortBy('nama')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'tanggal'            => 'required|date',
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
        ]);

        $row = PiketJadwal::firstOrCreate(
            ['tanggal' => $d['tanggal'], 'tenaga_pendidik_id' => $d['tenaga_pendidik_id']],
            ['ditunjuk_oleh' => Auth::id()]
        );

        if (!$row->wasRecentlyCreated) {
            return back()->with('error', 'Guru ini sudah ditunjuk piket pada tanggal tersebut.');
        }
        return back()->with('success', 'Guru piket ditunjuk.');
    }

    /**
     * Tunjuk piket untuk RENTANG tanggal sekaligus (tak perlu input harian).
     * Opsi `hari` (senin..ahad) = filter hari; kosong = semua hari dalam rentang.
     * Idempoten: tanggal yang sudah ditunjuk (guru sama) dilewati.
     */
    public function storeRentang(Request $request)
    {
        $d = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal_mulai'      => 'required|date',
            'tanggal_selesai'    => 'required|date|after_or_equal:tanggal_mulai',
            'hari'               => 'nullable|array',
            'hari.*'             => 'in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
        ]);

        $mulai   = \Carbon\Carbon::parse($d['tanggal_mulai']);
        $selesai = \Carbon\Carbon::parse($d['tanggal_selesai']);
        if ($mulai->diffInDays($selesai) > 366) {
            return back()->with('error', 'Rentang maksimal 1 tahun.');
        }
        $hari = $d['hari'] ?? [];

        $dibuat = 0; $dilewati = 0;
        for ($t = $mulai->copy(); $t->lte($selesai); $t->addDay()) {
            if (!empty($hari) && !in_array(\App\Services\TimezoneHelper::namaHariDB($t), $hari, true)) continue;
            $row = PiketJadwal::firstOrCreate(
                ['tanggal' => $t->toDateString(), 'tenaga_pendidik_id' => $d['tenaga_pendidik_id']],
                ['ditunjuk_oleh' => Auth::id()]
            );
            $row->wasRecentlyCreated ? $dibuat++ : $dilewati++;
        }

        if ($dibuat === 0 && $dilewati === 0) {
            return back()->with('error', 'Tidak ada tanggal yang cocok dengan pilihan hari.');
        }
        return back()->with('success',
            "$dibuat hari ditunjuk piket" . ($dilewati ? ", $dilewati dilewati (sudah ada)" : '') . '.');
    }

    public function destroy(PiketJadwal $jadwal)
    {
        if ($jadwal->penilaian()->exists()) {
            return back()->with('error', 'Sudah ada penilaian pada penugasan ini — tidak dapat dihapus.');
        }
        $jadwal->delete();
        return back()->with('success', 'Penugasan piket dihapus.');
    }
}
