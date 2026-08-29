<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi endpoint scan Smart Controlling (device-agnostic: web kiosk / ESP32-CAM).
 * Header: X-Device-Token: <token> == config('controlling.device_token').
 */
class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('controlling.device_token');
        $given    = (string) ($request->header('X-Device-Token') ?? $request->input('device_token', ''));

        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['success' => false, 'message' => 'Device token tidak valid.'], 401);
        }
        return $next($request);
    }
}
