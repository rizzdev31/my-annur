<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            'auth' => [
                'user' => $request->user() ? [
                    'id'       => $request->user()->id,
                    'name'     => $request->user()->name,
                    'email'    => $request->user()->email,
                    'username' => $request->user()->username,
                    'role'     => $request->user()->role,
                    'foto'     => $request->user()->foto
                                    ? asset('storage/' . $request->user()->foto)
                                    : null,
                ] : null,

                // Notifikasi belum dibaca — aman dari error tabel belum ada
                'unread_notif' => fn () => $this->safeCount(fn () =>
                    $request->user()?->notifikasiBelumDibaca()->count() ?? 0
                ),

                // Pengajuan izin pending — untuk badge sidebar superadmin
                'pending_pengajuan' => fn () => $this->safeCount(fn () =>
                    $request->user()?->role === 'super_admin'
                        ? \App\Models\PengajuanIzin::where('status', 'pending')->count()
                        : 0
                ),

                // Saran & masukan yang belum dibaca admin — badge sidebar.
                // Dibatasi ke pemegang modul 'masukan' agar tidak membocorkan
                // adanya keluhan kepada pengelola yang tak berhak membacanya.
                'masukan_baru' => fn () => $this->safeCount(function () use ($request) {
                    $u = $request->user();
                    if (!$u) return 0;

                    $boleh = $u->isSuperAdmin()
                        || in_array('masukan', (array) $u->modulSaya(), true);

                    return $boleh
                        ? \App\Models\Masukan::where('belum_dibaca_admin', true)->count()
                        : 0;
                }),

                // RBAC: superadmin? + daftar kode modul yang boleh diakses (untuk filter sidebar)
                'is_superadmin' => fn () => (bool) $request->user()?->isSuperAdmin(),
                'modul' => function () use ($request) {
                    try {
                        return $request->user() ? $request->user()->modulSaya() : [];
                    } catch (\Throwable $e) {
                        return [];
                    }
                },
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ]);
    }

    /**
     * Eksekusi callback dengan aman — return 0 jika tabel/model belum siap.
     */
    private function safeCount(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}