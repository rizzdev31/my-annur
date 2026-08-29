<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function showLogin()
    {
        // Kalau sudah login, arahkan ke beranda sesuai peran
        if (Auth::check()) {
            return redirect()->route(Auth::user()->berandaRoute() ?? 'admin.dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Proses login — support email atau username.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required'    => 'Email atau username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $loginField = $request->login;

        // Deteksi apakah input adalah email atau username
        $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        $credentials = [
            $fieldType   => $loginField,
            'password'   => $request->password,
            'status'     => 'aktif',     // hanya user aktif yang bisa login
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Hanya superadmin & akun admin berperan yang boleh panel web.
            // Tenaga pendidik & santri → gunakan aplikasi mobile.
            if (!in_array($user->role, ['super_admin', 'admin'], true)) {
                Auth::logout();
                $request->session()->invalidate();
                return back()->withErrors([
                    'login' => 'Akun ini tidak memiliki akses ke panel admin. Gunakan aplikasi mobile.',
                ])->onlyInput('login');
            }

            // Landing sesuai peran (admin → menu modul pertamanya; tanpa peran → dashboard→"Akses Ditolak").
            return redirect()->intended(route($user->berandaRoute() ?? 'admin.dashboard'));
        }

        // Login gagal
        return back()->withErrors([
            'login' => 'Email/username atau password salah.',
        ])->onlyInput('login');
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}