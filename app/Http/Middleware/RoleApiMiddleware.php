<?php
// ══════════════════════════════════════════════════════════════════════════════
// app/Http/Middleware/RoleApiMiddleware.php  (untuk API routes — Flutter)
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleApiMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.',
                'code'    => 403,
            ], 403);
        }

        return $next($request);
    }
}