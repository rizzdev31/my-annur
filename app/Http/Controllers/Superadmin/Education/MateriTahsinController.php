<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\SettingTahsinMateri;
use App\Services\TahsinService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MateriTahsinController extends Controller
{
    /** Daftar materi tahsin terstruktur per level (1..5 + Persiapan Tahfidz). */
    public function index()
    {
        $materi = SettingTahsinMateri::where('is_aktif', true)
            ->orderBy('level')->orderBy('urutan')->orderBy('id')->get()
            ->map(fn($m) => [
                'id'     => $m->id,
                'level'  => $m->level,
                'urutan' => $m->urutan,
                'nama'   => $m->nama,
            ]);

        return Inertia::render('Admin/SmartEducation/MateriTahsin/Index', [
            'materi'    => $materi,
            'levelOpsi' => collect(range(1, TahsinService::LEVEL_MAX))
                ->map(fn($lv) => ['value' => $lv, 'label' => TahsinService::levelLabel($lv)])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        SettingTahsinMateri::create(array_merge($data, ['is_aktif' => true]));
        return back()->with('success', "Materi tahsin level {$data['level']} ditambahkan.");
    }

    public function update(Request $request, SettingTahsinMateri $materi)
    {
        $materi->update($this->validateData($request));
        return back()->with('success', 'Materi tahsin diperbarui.');
    }

    public function destroy(SettingTahsinMateri $materi)
    {
        $materi->update(['is_aktif' => false]);
        return back()->with('success', 'Materi tahsin dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'level'  => 'required|integer|min:1|max:6',
            'nama'   => 'required|string|max:150',
            'urutan' => 'nullable|integer|min:0',
        ]);
    }
}
