<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\PetugasPeran;
use App\Models\SmartHealthLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Admin Smart Health: menunjuk Petugas Kesehatan (delegasi) + monitor kasus.
 * Validasi & pemantauan dilakukan petugas via aplikasi guru (Flutter).
 */
class SmartHealthController extends Controller
{
    private const PERAN = 'kesehatan';

    public function index(Request $request)
    {
        $statusFilter = $request->status ?: 'all';

        $petugasIds = PetugasPeran::where('peran', self::PERAN)->where('is_aktif', true)
            ->pluck('tenaga_pendidik_id')->all();

        $guru = TenagaPendidik::aktif()->with('user:id,name')->get()
            ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—', 'is_petugas' => in_array($g->id, $petugasIds, true)])
            ->filter(fn($g) => $g['nama'] !== '—')->sortBy('nama')->values();

        $laporan = SmartHealthLaporan::with(['santri:id,nama_lengkap', 'pengecekan', 'disetujuiOleh.user:id,name'])
            ->when($statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($l) => [
                'id'       => $l->id,
                'santri'   => $l->santri?->nama_lengkap ?? '—',
                'penyakit' => $l->deskripsi_penyakit,
                'foto'     => $l->fotoUrl(),
                'status'   => $l->status,
                'kondisi'  => $l->kondisi_akhir,
                'hari'     => $l->pengecekan->where('keputusan', 'pengecekan')->count(),
                'petugas'  => $l->disetujuiOleh?->user?->name,
                'tanggal'  => $l->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/SmartHealth/Index', [
            'guru'         => $guru,
            'laporan'      => $laporan,
            'filterStatus' => $statusFilter,
            'ringkasan'    => [
                'petugas'   => count($petugasIds),
                'menunggu'  => SmartHealthLaporan::where('status', 'menunggu')->count(),
                'pengecekan'=> SmartHealthLaporan::where('status', 'dalam_pengecekan')->count(),
            ],
        ]);
    }

    public function simpanPetugas(Request $request)
    {
        $data = $request->validate([
            'petugas_ids'   => 'present|array',
            'petugas_ids.*' => 'integer|exists:tenaga_pendidik,id',
        ]);
        $ids = $data['petugas_ids'];

        foreach ($ids as $tpId) {
            PetugasPeran::updateOrCreate(
                ['tenaga_pendidik_id' => $tpId, 'peran' => self::PERAN],
                ['is_aktif' => true, 'ditunjuk_oleh' => Auth::id()]
            );
        }
        PetugasPeran::where('peran', self::PERAN)
            ->when($ids, fn($q) => $q->whereNotIn('tenaga_pendidik_id', $ids))
            ->update(['is_aktif' => false]);

        return back()->with('success', 'Petugas Kesehatan diperbarui.');
    }
}
