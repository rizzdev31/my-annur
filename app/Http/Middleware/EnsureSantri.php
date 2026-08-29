<?php

namespace App\Http\Middleware;

use App\Models\Santri;
use Closure;
use Illuminate\Http\Request;

/**
 * Pastikan token yang dipakai adalah milik SANTRI (portal wali), bukan token guru.
 * Dipasang setelah auth:sanctum pada grup /api/santri.
 */
class EnsureSantri
{
    public function handle(Request $request, Closure $next)
    {
        if (!($request->user() instanceof Santri)) {
            return response()->json(['success' => false, 'message' => 'Akses khusus akun santri.'], 403);
        }
        return $next($request);
    }
}
