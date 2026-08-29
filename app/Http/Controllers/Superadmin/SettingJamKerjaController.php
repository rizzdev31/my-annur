<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingJamKerja;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingJamKerjaController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Index', [
            'settings' => SettingJamKerja::orderByDesc('is_default')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'                   => 'required|string|max:100',
            'jam_masuk'              => 'required|date_format:H:i',
            'jam_pulang'             => 'required|date_format:H:i',
            'toleransi_terlambat'    => 'required|integer|min:0|max:120',
            'hari_kerja'             => 'required|array|min:1',
            'total_jam_kerja_sehari' => 'required|integer|min:60',
        ]);

        SettingJamKerja::create(array_merge($data, ['is_aktif' => true]));

        return redirect()->route('admin.smart-payroll.setting-gaji.jam-kerja.index')
            ->with('success', 'Setting jam kerja berhasil disimpan.');
    }

    public function edit(SettingJamKerja $jamKerja)
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Form', [
            'setting' => $jamKerja,
        ]);
    }

    public function update(Request $request, SettingJamKerja $jamKerja)
    {
        $data = $request->validate([
            'nama'                   => 'required|string|max:100',
            'jam_masuk'              => 'required|date_format:H:i',
            'jam_pulang'             => 'required|date_format:H:i',
            'toleransi_terlambat'    => 'required|integer|min:0|max:120',
            'hari_kerja'             => 'required|array|min:1',
            'total_jam_kerja_sehari' => 'required|integer|min:60',
        ]);

        $jamKerja->update($data);

        return redirect()->route('admin.smart-payroll.setting-gaji.jam-kerja.index')
            ->with('success', 'Setting jam kerja diperbarui.');
    }

    public function destroy(SettingJamKerja $jamKerja)
    {
        if ($jamKerja->is_default) {
            return back()->with('error', 'Setting default tidak bisa dihapus.');
        }

        $jamKerja->delete();

        return back()->with('success', 'Setting jam kerja dihapus.');
    }

    public function setDefault(SettingJamKerja $jamKerja)
    {
        SettingJamKerja::where('is_default', true)->update(['is_default' => false]);
        $jamKerja->update(['is_default' => true]);

        return back()->with('success', "{$jamKerja->nama} dijadikan setting default.");
    }
}