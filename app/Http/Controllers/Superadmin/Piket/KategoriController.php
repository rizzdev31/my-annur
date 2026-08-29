<?php

namespace App\Http\Controllers\Superadmin\Piket;

use App\Http\Controllers\Controller;
use App\Models\PiketKategori;
use App\Models\PiketPenilaian;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Rubrik kategori penilaian Guru Piket (master) — apresiasi/catatan + dimensi + poin baku.
 * Poin diatur admin di sini agar konsisten antar-penilai (bukan ketik bebas oleh piket).
 */
class KategoriController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Piket/Kategori/Index', [
            'kategori' => PiketKategori::orderBy('jenis')->orderBy('dimensi')->orderBy('nama')
                ->get(['id', 'nama', 'jenis', 'dimensi', 'poin', 'is_aktif']),
        ]);
    }

    public function store(Request $request)
    {
        PiketKategori::create($this->validated($request));
        return back()->with('success', 'Kategori penilaian ditambahkan.');
    }

    public function update(Request $request, PiketKategori $kategori)
    {
        $kategori->update($this->validated($request));
        return back()->with('success', 'Kategori penilaian diperbarui.');
    }

    public function destroy(PiketKategori $kategori)
    {
        if (PiketPenilaian::where('kategori_id', $kategori->id)->exists()) {
            // Sudah dipakai → nonaktifkan saja agar riwayat penilaian tetap valid.
            $kategori->update(['is_aktif' => false]);
            return back()->with('success', 'Kategori sudah dipakai penilaian — dinonaktifkan (tidak dihapus).');
        }
        $kategori->delete();
        return back()->with('success', 'Kategori penilaian dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama'     => 'required|string|max:100',
            'jenis'    => 'required|in:apresiasi,catatan',
            'dimensi'  => 'required|in:disiplin,tugas,administrasi',
            // Skala baku 1–10 (bobot dampak) agar sebanding antar kategori & sinkron dgn kinerja.
            'poin'     => 'required|numeric|min:1|max:10',
            'is_aktif' => 'boolean',
        ]);
    }
}
