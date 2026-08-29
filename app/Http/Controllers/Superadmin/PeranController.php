<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Peran;
use App\Models\PeranModul;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Kelola Peran (RBAC) — superadmin only. Peran = bundel modul yang diberi nama.
 */
class PeranController extends Controller
{
    public function index()
    {
        $peran = Peran::withCount('users')->with('modul')->orderByDesc('is_bawaan')->orderBy('nama')->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'kode'       => $p->kode,
                'nama'       => $p->nama,
                'deskripsi'  => $p->deskripsi,
                'is_bawaan'  => $p->is_bawaan,
                'is_aktif'   => $p->is_aktif,
                'modul'      => $p->daftarModul(),
                'jumlah_akun'=> $p->users_count,
            ]);

        return Inertia::render('Admin/Pengaturan/Peran/Index', [
            'peran'     => $peran,
            'modulOpsi' => collect(config('modul.daftar', []))
                ->map(fn($d, $k) => [
                    'kode'     => $k,
                    'nama'     => $d['nama'] ?? $k,
                    'kategori' => $d['kategori'] ?? 'Lainnya',
                ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'nama'      => 'required|string|max:60',
            'deskripsi' => 'nullable|string|max:255',
            'modul'     => 'array',
            'modul.*'   => 'string',
        ]);

        $kode = $this->kodeUnik($d['nama']);
        DB::transaction(function () use ($d, $kode) {
            $peran = Peran::create([
                'kode' => $kode, 'nama' => $d['nama'], 'deskripsi' => $d['deskripsi'] ?? null,
                'is_bawaan' => false, 'is_aktif' => true,
            ]);
            $this->syncModul($peran, $d['modul'] ?? []);
        });

        return back()->with('success', "Peran \"{$d['nama']}\" berhasil dibuat.");
    }

    public function update(Request $request, Peran $peran)
    {
        $d = $request->validate([
            'nama'      => 'required|string|max:60',
            'deskripsi' => 'nullable|string|max:255',
            'modul'     => 'array',
            'modul.*'   => 'string',
        ]);

        DB::transaction(function () use ($d, $peran) {
            $peran->update(['nama' => $d['nama'], 'deskripsi' => $d['deskripsi'] ?? null]);
            $this->syncModul($peran, $d['modul'] ?? []);
        });

        return back()->with('success', "Peran \"{$peran->nama}\" berhasil diperbarui.");
    }

    public function toggle(Peran $peran)
    {
        $peran->update(['is_aktif' => !$peran->is_aktif]);
        return back()->with('success', 'Status peran diperbarui.');
    }

    public function destroy(Peran $peran)
    {
        if ($peran->is_bawaan) {
            return back()->with('error', 'Peran bawaan tidak bisa dihapus (boleh diubah modulnya).');
        }
        $nama = $peran->nama;
        $peran->delete(); // user_peran & peran_modul ikut (cascade)
        return back()->with('success', "Peran \"{$nama}\" dihapus.");
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function kodeUnik(string $nama): string
    {
        $base = Str::slug($nama, '_') ?: 'peran';
        $kode = $base;
        $i = 1;
        while (Peran::where('kode', $kode)->exists()) {
            $kode = $base . '_' . (++$i);
        }
        return $kode;
    }

    /** Simpan hanya modul yang valid (ada di config). */
    private function syncModul(Peran $peran, array $modul): void
    {
        $valid = array_keys(config('modul.daftar', []));
        $bersih = array_values(array_unique(array_intersect($modul, $valid)));

        $peran->modul()->delete();
        foreach ($bersih as $m) {
            PeranModul::create(['peran_id' => $peran->id, 'modul' => $m]);
        }
    }
}
