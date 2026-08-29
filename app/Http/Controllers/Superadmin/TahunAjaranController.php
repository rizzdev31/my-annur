<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TahunAjaranController extends Controller
{
    public function index()
    {
        $data = TahunAjaran::withCount('jadwalMengajar')
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'nama'            => $t->nama,
                'semester'        => $t->semester,
                'tanggal_mulai'        => $t->tanggal_mulai?->format('d M Y'),
                'tanggal_mulai_raw'    => $t->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai'      => $t->tanggal_selesai?->format('d M Y'),
                'tanggal_selesai_raw'  => $t->tanggal_selesai?->format('Y-m-d'),
                'is_aktif'        => $t->is_aktif,
                'jumlah_jadwal'   => $t->jadwal_mengajar_count,
                'label'           => $t->label,
            ]);

        return Inertia::render('Admin/Master/TahunAjaran/Index', [
            'tahunAjaran' => $data,
            'aktif'       => TahunAjaran::aktif()?->id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:50',
            'semester'        => 'required|in:ganjil,genap',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        TahunAjaran::create(array_merge($data, ['is_aktif' => false]));

        return back()->with('success', "Tahun ajaran {$data['nama']} berhasil ditambahkan.");
    }

    public function update(Request $request, TahunAjaran $tahunAjaran)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:50',
            'semester'        => 'required|in:ganjil,genap',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $tahunAjaran->update($data);

        return back()->with('success', "Tahun ajaran {$tahunAjaran->nama} diperbarui.");
    }

    public function setAktif(TahunAjaran $tahunAjaran)
    {
        // Nonaktifkan semua dulu
        TahunAjaran::where('is_aktif', true)->update(['is_aktif' => false]);
        // Aktifkan yang dipilih
        $tahunAjaran->update(['is_aktif' => true]);

        return back()->with('success',
            "{$tahunAjaran->nama} — ".ucfirst($tahunAjaran->semester)." dijadikan tahun ajaran aktif."
        );
    }

    public function destroy(TahunAjaran $tahunAjaran)
    {
        if ($tahunAjaran->jadwalMengajar()->count() > 0) {
            return back()->with('error',
                "{$tahunAjaran->nama} tidak bisa dihapus karena masih memiliki " .
                $tahunAjaran->jadwalMengajar()->count() . " jadwal mengajar."
            );
        }

        if ($tahunAjaran->is_aktif) {
            return back()->with('error', 'Tahun ajaran aktif tidak bisa dihapus. Nonaktifkan terlebih dahulu.');
        }

        $tahunAjaran->delete();

        return back()->with('success', "Tahun ajaran {$tahunAjaran->nama} dihapus.");
    }
}