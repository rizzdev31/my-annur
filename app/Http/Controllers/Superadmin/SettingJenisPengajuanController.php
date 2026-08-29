<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingJenisPengajuan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingJenisPengajuanController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SmartPayroll/SettingPengajuan/Index', [
            'settings' => SettingJenisPengajuan::orderBy('kategori')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/SettingPengajuan/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama'                          => 'required|string|max:100',
            'kode'                          => 'required|string|max:30|unique:setting_jenis_pengajuan,kode|alpha_dash',
            'kategori'                      => 'required|in:sakit,izin,cuti,dinas',
            'deskripsi'                     => 'nullable|string|max:500',
            'max_hari_per_pengajuan'        => 'required|integer|min:1',
            'kuota_per_tahun'               => 'nullable|integer|min:1',
            'min_hari_pengajuan_sebelumnya' => 'required|integer|min:0',
            'butuh_dokumen'                 => 'boolean',
            'keterangan_dokumen'            => 'nullable|string|max:200',
            'auto_approve'                  => 'boolean',
            'pengaruh_gaji'                 => 'required|in:tidak_potong,potong_absensi,potong_sebagian,potong_penuh',
            'persen_potongan'               => 'nullable|numeric|min:0|max:100',
            'update_status_kepegawaian'     => 'boolean',
            'status_kepegawaian_tujuan'     => 'nullable|string',
            'min_hari_untuk_update_status'  => 'nullable|integer|min:0',
        ]);

        SettingJenisPengajuan::create(array_merge($validated, ['is_aktif' => true]));

        return redirect()->route('admin.smart-payroll.setting-pengajuan.index')
            ->with('success', "Jenis pengajuan {$request->nama} berhasil ditambahkan.");
    }

    public function edit(SettingJenisPengajuan $settingPengajuan)
    {
        return Inertia::render('Admin/SmartPayroll/SettingPengajuan/Form', [
            'setting' => $settingPengajuan,
        ]);
    }

    public function update(Request $request, SettingJenisPengajuan $settingPengajuan)
    {
        $validated = $request->validate([
            'nama'                          => 'required|string|max:100',
            'kategori'                      => 'required|in:sakit,izin,cuti,dinas',
            'deskripsi'                     => 'nullable|string|max:500',
            'max_hari_per_pengajuan'        => 'required|integer|min:1',
            'kuota_per_tahun'               => 'nullable|integer|min:1',
            'min_hari_pengajuan_sebelumnya' => 'required|integer|min:0',
            'butuh_dokumen'                 => 'boolean',
            'keterangan_dokumen'            => 'nullable|string|max:200',
            'auto_approve'                  => 'boolean',
            'pengaruh_gaji'                 => 'required|in:tidak_potong,potong_absensi,potong_sebagian,potong_penuh',
            'persen_potongan'               => 'nullable|numeric|min:0|max:100',
            'update_status_kepegawaian'     => 'boolean',
            'status_kepegawaian_tujuan'     => 'nullable|string',
            'min_hari_untuk_update_status'  => 'nullable|integer|min:0',
        ]);

        $settingPengajuan->update($validated);

        return redirect()->route('admin.smart-payroll.setting-pengajuan.index')
            ->with('success', "Setting {$settingPengajuan->nama} berhasil diperbarui.");
    }

    public function destroy(SettingJenisPengajuan $settingPengajuan)
    {
        if ($settingPengajuan->pengajuan()->whereIn('status', ['pending', 'disetujui'])->exists()) {
            return back()->with('error', 'Tidak bisa dihapus, masih ada pengajuan aktif dengan jenis ini.');
        }

        $settingPengajuan->update(['is_aktif' => false]);

        return back()->with('success', "{$settingPengajuan->nama} berhasil dinonaktifkan.");
    }

    public function toggleAktif(SettingJenisPengajuan $settingPengajuan)
    {
        $settingPengajuan->update(['is_aktif' => !$settingPengajuan->is_aktif]);
        $label = $settingPengajuan->is_aktif ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "{$settingPengajuan->nama} berhasil {$label}.");
    }
}