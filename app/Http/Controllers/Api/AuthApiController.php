<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // LOGIN — support email atau username
    // ══════════════════════════════════════════════════════════════════════════

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'     => 'required|string',
            'password'  => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        $loginField = $request->login;
        $fieldType  = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $loginField)
            ->where('status', 'aktif')
            ->whereNull('deleted_at')
            ->first();

        // Validasi user dan password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/username atau password salah.',
                'code'    => 401,
            ], 401);
        }

        // Hanya role tenaga_pendidik yang boleh akses app Flutter
        if ($user->role !== 'tenaga_pendidik') {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses ke aplikasi mobile.',
                'code'    => 403,
            ], 403);
        }

        // Hapus token lama jika ada (1 device 1 token)
        $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('flutter-app')->plainTextToken;

        // Simpan FCM token jika dikirim
        if ($request->fcm_token) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        // Ambil data tenaga pendidik
        $tp = $user->tenagaPendidik()->with(['jabatan'])->first();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => $this->formatUser($user, $tp),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ME — ambil data user yang sedang login
    // ══════════════════════════════════════════════════════════════════════════

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik()->with(['jabatan'])->first();

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user, $tp),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════════════════════════════

    public function logout(Request $request): JsonResponse
    {
        // Hapus hanya token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REFRESH — hapus token lama, buat token baru
    // ══════════════════════════════════════════════════════════════════════════

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token diperbarui.',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE FCM TOKEN — dipanggil saat app dibuka / token berubah
    // ══════════════════════════════════════════════════════════════════════════

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'user_id'   => 'required|exists:users,id',
        ]);

        User::where('id', $request->user_id)
            ->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token diperbarui.',
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HELPER
    // ═════════════════════════════════════════════════════════════════════════

    private function formatUser(User $user, ?TenagaPendidik $tp): array
    {
        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'username'         => $user->username,
            'role'             => $user->role,
            'foto'             => $user->foto
                ? asset('storage/' . $user->foto) : null,
            // Data tenaga pendidik
            'nip'              => $tp?->nip,
            'jabatan'          => $tp?->jabatan?->nama_jabatan ?? '—',
            'jabatan_display'  => $tp?->nama_jabatan_display ?? '—',
            'jenis_guru'       => $tp?->jenis_guru,
            'status_kepegawaian' => $tp?->status_kepegawaian ?? 'aktif',
            'tanggal_masuk'    => $tp?->tanggal_masuk?->format('Y-m-d'),
            'no_hp'            => $tp?->no_hp,
            'tenaga_pendidik_id' => $tp?->id,
        ];
    }
}