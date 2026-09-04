<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pengawas;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Penunjukan PENGAWAS (monitoring pimpinan) oleh superadmin.
 * Pengawas memantau guru lain langsung dari PWA — tanpa akun admin kedua.
 */
class PengawasController extends Controller
{
    public function index()
    {
        $pengawas = Pengawas::with(['tenagaPendidik.user:id,name', 'tenagaPendidik.jabatan:id,nama_jabatan', 'guruDiawasi.user:id,name'])
            ->get()
            ->map(fn($p) => [
                'id'                 => $p->id,
                'tenaga_pendidik_id' => $p->tenaga_pendidik_id,
                'nama'               => $p->tenagaPendidik?->user?->name ?? '—',
                'jabatan'            => $p->tenagaPendidik?->jabatan?->nama_jabatan ?? '—',
                'modul'              => array_values((array) ($p->modul ?? [])),
                'cakupan'            => $p->cakupan,
                'boleh_setujui_izin' => $p->boleh_setujui_izin,
                'is_aktif'           => $p->is_aktif,
                'catatan'            => $p->catatan,
                'guru_ids'           => $p->guruDiawasi->pluck('id')->values(),
                'jumlah_guru'        => $p->cakupan === 'semua' ? null : $p->guruDiawasi->count(),
            ])->sortBy('nama')->values();

        return Inertia::render('Admin/Monitoring/Pengawas/Index', [
            'pengawas'   => $pengawas,
            'modulOpsi'  => Pengawas::MODUL,
            'guruOpsi'   => TenagaPendidik::aktif()->with(['user:id,name', 'jabatan:id,nama_jabatan'])->get()
                ->map(fn($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user?->name ?? '—',
                    'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                ])->filter(fn($g) => $g['nama'] !== '—')->sortBy('nama')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);

        if (Pengawas::where('tenaga_pendidik_id', $d['tenaga_pendidik_id'])->exists()) {
            return back()->with('error', 'Guru ini sudah terdaftar sebagai pengawas — gunakan Edit.');
        }

        $p = Pengawas::create([
            'tenaga_pendidik_id' => $d['tenaga_pendidik_id'],
            'modul'              => $d['modul'] ?? [],
            'cakupan'            => $d['cakupan'],
            'boleh_setujui_izin' => (bool) ($d['boleh_setujui_izin'] ?? false),
            'catatan'            => $d['catatan'] ?? null,
            'is_aktif'           => true,
            'ditunjuk_oleh'      => Auth::id(),
        ]);
        $this->syncGuru($p, $d);

        return back()->with('success', 'Pengawas ditunjuk.');
    }

    public function update(Request $request, Pengawas $pengawas)
    {
        $d = $this->validasi($request, $pengawas->id);

        $pengawas->update([
            'tenaga_pendidik_id' => $d['tenaga_pendidik_id'],
            'modul'              => $d['modul'] ?? [],
            'cakupan'            => $d['cakupan'],
            'boleh_setujui_izin' => (bool) ($d['boleh_setujui_izin'] ?? false),
            'catatan'            => $d['catatan'] ?? null,
        ]);
        $this->syncGuru($pengawas, $d);

        return back()->with('success', 'Pengawas diperbarui.');
    }

    public function toggle(Pengawas $pengawas)
    {
        $pengawas->update(['is_aktif' => !$pengawas->is_aktif]);
        return back()->with('success', $pengawas->is_aktif ? 'Pengawas diaktifkan.' : 'Pengawas dinonaktifkan.');
    }

    public function destroy(Pengawas $pengawas)
    {
        $pengawas->delete();
        return back()->with('success', 'Pengawas dihapus.');
    }

    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        return $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'modul'              => 'nullable|array',
            'modul.*'            => 'in:' . implode(',', array_keys(Pengawas::MODUL)),
            'cakupan'            => 'required|in:semua,pilih',
            'boleh_setujui_izin' => 'nullable|boolean',
            'catatan'            => 'nullable|string|max:200',
            'guru_ids'           => 'nullable|array',
            'guru_ids.*'         => 'integer|exists:tenaga_pendidik,id',
        ]);
    }

    /** Sinkron daftar guru dipantau; diri sendiri selalu dikeluarkan. */
    private function syncGuru(Pengawas $p, array $d): void
    {
        if ($p->cakupan !== 'pilih') {
            $p->guruDiawasi()->sync([]);
            return;
        }
        $ids = collect($d['guru_ids'] ?? [])
            ->map(fn($i) => (int) $i)
            ->reject(fn($i) => $i === (int) $p->tenaga_pendidik_id)   // anti pantau diri sendiri
            ->unique()->values()->all();
        $p->guruDiawasi()->sync($ids);
    }
}
