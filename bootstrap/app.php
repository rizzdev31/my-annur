<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',     // ← pastikan ini ada
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Di belakang reverse-proxy (Caddy) → percayai header X-Forwarded-* agar
        // Laravel tahu request datang via HTTPS dan menghasilkan URL https (bukan
        // http). Aman: app hanya dijangkau lewat Caddy di jaringan 'edge'.
        $middleware->trustProxies(at: '*');

        // Inertia middleware — wajib ada di web group agar share() (auth, flash, errors)
        // dikirim ke setiap Inertia response dan validation errors di-handle dengan benar.
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role'      => \App\Http\Middleware\RoleMiddleware::class,
            'role.api'  => \App\Http\Middleware\RoleApiMiddleware::class,
            'device.token' => \App\Http\Middleware\VerifyDeviceToken::class,
            'akses'     => \App\Http\Middleware\AksesModul::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();