<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\KegiatanPenting;
use App\Services\KegiatanPentingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KegiatanPentingController extends Controller
{
    public function __construct(private KegiatanPentingService $service) {}

    public function index()
    {
        return Inertia::render('Admin/SmartPayroll/KegiatanPenting/Index', [
            'kegiatan' => KegiatanPenting::orderBy('jam')->get()->map(fn ($k) => [
                'id'         => $k->id,
                'nama'       => $k->nama,
                'sasaran'    => $k->sasaran,
                'jam'        => substr((string) $k->jam, 0, 5),
                'poin_hadir' => $k->poin_hadir,
                'poin_absen' => $k->poin_absen,
                'is_aktif'   => $k->is_aktif,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        KegiatanPenting::create($data + ['dibuat_oleh' => auth()->id()]);
        return back()->with('success', 'Kegiatan penting ditambahkan.');
    }

    public function update(Request $request, KegiatanPenting $kegiatanPenting)
    {
        $kegiatanPenting->update($this->validasi($request));
        return back()->with('success', 'Kegiatan penting diperbarui.');
    }

    public function toggle(KegiatanPenting $kegiatanPenting)
    {
        $kegiatanPenting->update(['is_aktif' => !$kegiatanPenting->is_aktif]);
        return back()->with('success', 'Status kegiatan diubah.');
    }

    public function destroy(KegiatanPenting $kegiatanPenting)
    {
        $kegiatanPenting->delete();
        return back()->with('success', 'Kegiatan penting dihapus.');
    }

    /** Laporan harian: rekap kehadiran tiap kegiatan pada satu tanggal. */
    public function laporan(Request $request)
    {
        $tanggal = $request->tanggal ?: now()->toDateString();

        $laporan = KegiatanPenting::where('is_aktif', true)->orderBy('jam')->get()
            ->map(function ($keg) use ($tanggal) {
                $peserta = $this->service->pesertaHariIni($keg, $tanggal);
                return [
                    'id'      => $keg->id,
                    'nama'    => $keg->nama,
                    'jam'     => substr((string) $keg->jam, 0, 5),
                    'sasaran' => $keg->sasaran,
                    'total'   => $peserta->count(),
                    'hadir'   => $peserta->where('status', 'hadir')->count(),
                    'tidak'   => $peserta->where('status', 'tidak_hadir')->count(),
                    'belum'   => $peserta->whereNull('status')->count(),
                    'peserta' => $peserta,
                ];
            });

        return Inertia::render('Admin/SmartPayroll/KegiatanPenting/Laporan', [
            'tanggal' => $tanggal,
            'laporan' => $laporan,
        ]);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama'       => 'required|string|max:150',
            'sasaran'    => 'required|in:mukim,non_mukim,semua',
            'jam'        => 'required|date_format:H:i',
            'poin_hadir' => 'required|integer|min:0|max:100',
            'poin_absen' => 'required|integer|min:0|max:100',
            'is_aktif'   => 'boolean',
        ]);
    }
}
