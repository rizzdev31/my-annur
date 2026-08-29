<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\PetugasPeran;
use App\Models\IzinSantri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Admin Perizinan Santri: menunjuk Petugas Perizinan (delegasi) + monitor izin.
 * Persetujuan izin dilakukan petugas via aplikasi guru (Flutter).
 */
class PerizinanController extends Controller
{
    private const PERAN = 'perizinan';

    public function index(Request $request)
    {
        $statusFilter = $request->status ?: 'all';

        $petugasIds = PetugasPeran::where('peran', self::PERAN)->where('is_aktif', true)
            ->pluck('tenaga_pendidik_id')->all();

        $guru = TenagaPendidik::aktif()->with('user:id,name')->get()
            ->map(fn($g) => [
                'id'         => $g->id,
                'nama'       => $g->user?->name ?? '—',
                'is_petugas' => in_array($g->id, $petugasIds, true),
            ])->filter(fn($g) => $g['nama'] !== '—')->sortBy('nama')->values();

        $izin = IzinSantri::with(['santri:id,nama_lengkap', 'diajukanOleh.user:id,name', 'disetujuiOleh.user:id,name'])
            ->when($statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn($i) => [
                'id'        => $i->id,
                'santri'      => $i->santri?->nama_lengkap ?? '—',
                'jenis'       => $i->jenis,           // syari | non_syari
                'jenis_label' => $i->jenis_label,
                'alasan'      => $i->alasan,
                'tanggal'   => $i->tanggal_mulai?->toDateString() . ' s/d ' . $i->tanggal_selesai?->toDateString(),
                'status'    => $i->status,
                'diajukan'  => $i->diajukanOleh?->user?->name,
                'petugas'   => $i->disetujuiOleh?->user?->name,
                'catatan'   => $i->catatan_petugas,
            ]);

        return Inertia::render('Admin/Perizinan/Index', [
            'guru'         => $guru,
            'izin'         => $izin,
            'filterStatus' => $statusFilter,
            'ringkasan'    => [
                'petugas'  => count($petugasIds),
                'diajukan' => IzinSantri::where('status', 'diajukan')->count(),
                'disetujui'=> IzinSantri::where('status', 'disetujui')->count(),
            ],
        ]);
    }

    /** Simpan daftar Petugas Perizinan (aktifkan yang dipilih, nonaktifkan sisanya). */
    public function simpanPetugas(Request $request)
    {
        $data = $request->validate([
            'petugas_ids'   => 'present|array',
            'petugas_ids.*' => 'integer|exists:tenaga_pendidik,id',
        ]);
        $ids = $data['petugas_ids'];

        // Aktifkan/insert yang dipilih.
        foreach ($ids as $tpId) {
            PetugasPeran::updateOrCreate(
                ['tenaga_pendidik_id' => $tpId, 'peran' => self::PERAN],
                ['is_aktif' => true, 'ditunjuk_oleh' => Auth::id()]
            );
        }
        // Nonaktifkan yang tidak dipilih lagi.
        PetugasPeran::where('peran', self::PERAN)
            ->when($ids, fn($q) => $q->whereNotIn('tenaga_pendidik_id', $ids))
            ->update(['is_aktif' => false]);

        return back()->with('success', 'Petugas Perizinan diperbarui.');
    }
}
