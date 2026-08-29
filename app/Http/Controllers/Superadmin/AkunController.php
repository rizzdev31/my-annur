<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Peran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Kelola Akun Admin (RBAC) — superadmin only. Akun role='admin' + assign peran.
 */
class AkunController extends Controller
{
    public function index()
    {
        $akun = User::where('role', 'admin')->with('peran:id,nama')->orderBy('name')->get()
            ->map(fn($u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'email'    => $u->email,
                'username' => $u->username,
                'status'   => $u->status,
                'peran'    => $u->peran->map(fn($p) => ['id' => $p->id, 'nama' => $p->nama]),
                'peran_ids'=> $u->peran->pluck('id'),
            ]);

        return Inertia::render('Admin/Pengaturan/Akun/Index', [
            'akun'      => $akun,
            'peranOpsi' => Peran::aktif()->orderByDesc('is_bawaan')->orderBy('nama')->get(['id', 'nama'])
                ->map(fn($p) => ['id' => $p->id, 'nama' => $p->nama]),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|max:150|unique:users,email',
            'username'  => 'required|string|max:50|alpha_dash|unique:users,username',
            'password'  => 'required|string|min:8',
            'peran'     => 'array',
            'peran.*'   => 'integer|exists:peran,id',
        ]);

        DB::transaction(function () use ($d, $request) {
            $user = User::create([
                'name' => $d['name'], 'email' => $d['email'], 'username' => $d['username'],
                'password' => $d['password'], 'role' => 'admin', 'status' => 'aktif',
            ]);
            $this->syncPeran($user, $d['peran'] ?? [], $request->user()->id);
        });

        return back()->with('success', "Akun \"{$d['name']}\" berhasil dibuat.");
    }

    public function update(Request $request, User $akun)
    {
        $this->pastikanAdmin($akun);

        $d = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($akun->id)],
            'username'  => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($akun->id)],
            'password'  => 'nullable|string|min:8',
            'peran'     => 'array',
            'peran.*'   => 'integer|exists:peran,id',
        ]);

        DB::transaction(function () use ($d, $akun, $request) {
            $upd = ['name' => $d['name'], 'email' => $d['email'], 'username' => $d['username']];
            if (!empty($d['password'])) {
                $upd['password'] = $d['password'];
            }
            $akun->update($upd);
            $this->syncPeran($akun, $d['peran'] ?? [], $request->user()->id);
        });

        return back()->with('success', "Akun \"{$akun->name}\" diperbarui.");
    }

    public function toggle(User $akun)
    {
        $this->pastikanAdmin($akun);
        $akun->update(['status' => $akun->status === 'aktif' ? 'nonaktif' : 'aktif']);
        return back()->with('success', 'Status akun diperbarui.');
    }

    public function resetPassword(Request $request, User $akun)
    {
        $this->pastikanAdmin($akun);
        $d = $request->validate(['password' => 'required|string|min:8']);
        $akun->update(['password' => $d['password']]);
        return back()->with('success', 'Password akun berhasil direset.');
    }

    public function destroy(User $akun)
    {
        $this->pastikanAdmin($akun);
        $nama = $akun->name;
        $akun->peran()->detach();
        $akun->delete();
        return back()->with('success', "Akun \"{$nama}\" dihapus.");
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** Cegah mengelola akun non-admin (mis. super_admin/guru) dari sini. */
    private function pastikanAdmin(User $akun): void
    {
        abort_unless($akun->role === 'admin', 403, 'Hanya akun admin yang bisa dikelola di sini.');
    }

    private function syncPeran(User $user, array $peranIds, int $olehId): void
    {
        $data = [];
        foreach (array_unique($peranIds) as $pid) {
            $data[$pid] = ['ditetapkan_oleh' => $olehId];
        }
        $user->peran()->sync($data);
    }
}
