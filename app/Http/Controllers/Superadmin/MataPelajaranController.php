<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $mapel = MataPelajaran::orderBy('tingkat')->orderBy('nama')->get()
            ->map(fn($m) => [
                'id'        => $m->id,
                'nama'      => $m->nama,
                'kode'      => $m->kode,
                'kategori'  => $m->kategori,
                'tingkat'   => $m->tingkat,
                'tipe'      => $m->tipe ?? 'reguler',
                'is_aktif'  => $m->is_aktif,
                'jumlah_jadwal' => $m->jadwalMengajar()->aktif()->count(),
            ]);

        return Inertia::render('Admin/Master/MataPelajaran/Index', [
            'mapel' => $mapel,
            'summary' => [
                'total'  => MataPelajaran::count(),
                'aktif'  => MataPelajaran::aktif()->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'kode'      => 'required|string|max:20|unique:mata_pelajaran,kode',
            'kategori'  => 'nullable|string|max:50',
            'tingkat'   => 'nullable|string|max:20',
            'tipe'      => 'nullable|in:reguler,tahfidz,tahsin',
        ]);

        MataPelajaran::create(array_merge($data, ['is_aktif' => true]));

        return back()->with('success', "Mata pelajaran {$data['nama']} berhasil ditambahkan.");
    }

    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'kode'      => 'required|string|max:20|unique:mata_pelajaran,kode,' . $mataPelajaran->id,
            'kategori'  => 'nullable|string|max:50',
            'tingkat'   => 'nullable|string|max:20',
            'tipe'      => 'nullable|in:reguler,tahfidz,tahsin',
        ]);

        $mataPelajaran->update($data);

        return back()->with('success', "Mata pelajaran {$mataPelajaran->nama} berhasil diperbarui.");
    }

    public function destroy(MataPelajaran $mataPelajaran)
    {
        if ($mataPelajaran->jadwalMengajar()->aktif()->count() > 0) {
            return back()->with('error',
                "{$mataPelajaran->nama} masih digunakan di " .
                $mataPelajaran->jadwalMengajar()->aktif()->count() . " jadwal aktif."
            );
        }

        $mataPelajaran->update(['is_aktif' => false]);
        return back()->with('success', "{$mataPelajaran->nama} dinonaktifkan.");
    }

    /** Download template Excel import mata pelajaran. */
    public function templateImport()
    {
        $headings = ['nama', 'kode', 'kategori', 'tingkat', 'tipe'];
        $contoh   = [['Tahfidz Pagi', 'THF-01', 'Quran', '7', 'tahfidz'], ['Matematika', 'MTK-07', 'Umum', '7', 'reguler']];
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TemplateExport($headings, $contoh), 'template-import-mata-pelajaran.xlsx');
    }

    /** Import mata pelajaran dari Excel. */
    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);
        $imp = new \App\Imports\MataPelajaranImport();
        try {
            \Maatwebsite\Excel\Facades\Excel::import($imp, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }
        $msg = "Import mapel: {$imp->berhasil} berhasil, {$imp->gagal} gagal."
            . (!empty($imp->errors) ? ' — ' . implode(' | ', array_slice($imp->errors, 0, 3)) : '');
        return back()->with($imp->berhasil > 0 ? 'success' : 'error', $msg);
    }
}