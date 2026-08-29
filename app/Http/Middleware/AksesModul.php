<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penegakan RBAC web admin (per-modul).
 *
 * - super_admin  → lolos semua (bypass).
 * - admin        → hanya route yang modulnya ia miliki; selain itu halaman ramah.
 * - lainnya      → ditolak (panel web bukan untuk guru/santri).
 *
 * Route yang tak terpetakan modul mana pun (master/setting) → super_admin only.
 */
class AksesModul
{
    /** Prefix route yang selalu boleh untuk admin mana pun (mis. lonceng notifikasi). */
    private array $selaluBoleh = ['admin.notifikasi'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }
        if ($user->isSuperAdmin()) {
            return $next($request);
        }
        if (!$user->isAdmin()) {
            abort(403);
        }

        $nama = $request->route()?->getName() ?? '';

        foreach ($this->selaluBoleh as $pre) {
            if ($nama === $pre || str_starts_with($nama, $pre . '.')) {
                return $next($request);
            }
        }

        if ($user->bolehRoute($nama)) {
            return $next($request);
        }

        // Ditolak → halaman ramah (status 200 agar Inertia menukar halaman dengan mulus).
        $modulSaya = collect(config('modul.daftar', []))
            ->only($user->modulSaya())
            ->map(fn($d, $k) => ['kode' => $k, 'nama' => $d['nama'] ?? $k, 'beranda' => $d['beranda'] ?? null])
            ->values();

        return Inertia::render('Admin/AksesDitolak', [
            'modulSaya' => $modulSaya,
        ])->toResponse($request);
    }
}
