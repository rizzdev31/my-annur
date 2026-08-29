<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Services\WaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Auth Portal Santri/Wali (monitoring read-only).
 * Utama: NIS + PASSWORD (login harian, tanpa OTP berulang).
 * Aktivasi/atur password: via tanggal lahir (sederhana) atau OTP WhatsApp (reset).
 * Token Sanctum tokenable=Santri, ability 'santri:read'.
 */
class AuthController extends Controller
{
    private function cariSantri(string $nis)
    {
        return Santri::where('is_aktif', true)->where('nip', trim($nis))->first();
    }

    private function samarkan(?string $wa): string
    {
        $wa = preg_replace('/\D/', '', (string) $wa);
        if (strlen($wa) < 4) return '••••';
        return substr($wa, 0, 3) . str_repeat('•', max(2, strlen($wa) - 5)) . substr($wa, -2);
    }

    private function buatToken(Santri $s): string
    {
        return $s->createToken('santri-portal', ['santri:read'])->plainTextToken;
    }

    private function profil(Santri $s): array
    {
        $kelas = $s->kelas()->first();
        return [
            'id' => $s->id, 'nis' => $s->nip, 'nama' => $s->nama_lengkap,
            'panggilan' => $s->nama_panggilan, 'foto' => $s->foto ? url('storage/' . $s->foto) : null,
            'kelas' => $kelas?->nama, 'program' => $s->program_quran, 'tahsin_level' => $s->tahsin_level,
        ];
    }

    /** POST /auth/login — {nis, password} → token (login harian). */
    public function login(Request $request): JsonResponse
    {
        $request->validate(['nis' => 'required|string|max:50', 'password' => 'required|string']);

        $key = 'santri-login:' . $request->ip() . ':' . $request->nis;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi nanti.'], 429);
        }

        $s = $this->cariSantri($request->nis);
        if (!$s || !$s->password) {
            RateLimiter::hit($key, 300);
            $msg = ($s && !$s->password)
                ? 'Akun belum diaktivasi. Aktivasi dulu untuk membuat password.'
                : 'NIS atau password salah.';
            return response()->json(['success' => false, 'message' => $msg, 'code' => ($s && !$s->password) ? 'BELUM_AKTIVASI' : 'INVALID'], 422);
        }
        if (!Hash::check($request->password, $s->password)) {
            RateLimiter::hit($key, 300);
            return response()->json(['success' => false, 'message' => 'NIS atau password salah.'], 422);
        }

        RateLimiter::clear($key);
        return response()->json(['success' => true, 'message' => 'Berhasil masuk.', 'data' => ['token' => $this->buatToken($s), 'santri' => $this->profil($s)]]);
    }

    /** POST /auth/aktivasi — {nis, tanggal_lahir, password} → set password (verifikasi via tgl lahir) + token. */
    public function aktivasi(Request $request): JsonResponse
    {
        $request->validate([
            'nis' => 'required|string|max:50',
            'tanggal_lahir' => 'required|date_format:Y-m-d',
            'password' => 'required|string|min:6|max:100',
        ]);
        $s = $this->cariSantri($request->nis);
        if (!$s || !$s->tanggal_lahir || $s->tanggal_lahir->format('Y-m-d') !== $request->tanggal_lahir) {
            return response()->json(['success' => false, 'message' => 'NIS atau tanggal lahir tidak cocok.'], 422);
        }
        $s->password = $request->password; // cast 'hashed' → tersimpan ter-hash
        $s->save();
        return response()->json(['success' => true, 'message' => 'Password berhasil dibuat. Silakan masuk.', 'data' => ['token' => $this->buatToken($s), 'santri' => $this->profil($s)]]);
    }

    /** POST /auth/minta-otp — {nis} → kirim OTP WA (untuk reset password). */
    public function mintaOtp(Request $request): JsonResponse
    {
        $request->validate(['nis' => 'required|string|max:50']);
        $key = 'otp-req:' . $request->ip() . ':' . $request->nis;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['success' => false, 'message' => 'Tunggu sebentar sebelum meminta OTP lagi.'], 429);
        }
        RateLimiter::hit($key, 60);

        $s = $this->cariSantri($request->nis);
        if (!$s || !$s->no_whatsapp) {
            return response()->json(['success' => true, 'message' => 'Jika NIS terdaftar & punya nomor WA, kode OTP telah dikirim.', 'data' => ['wa' => null]]);
        }

        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("santri-otp:{$s->id}", ['hash' => Hash::make($kode), 'exp' => now()->addMinutes(10), 'tries' => 0], now()->addMinutes(10));
        app(WaService::class)->enqueue('otp', $s,
            "*An-Nur Smart*\nKode reset password Portal Santri: *{$kode}*\nBerlaku 10 menit. Jangan bagikan kode ini.",
            'WA-OTP-' . $s->id . '-' . now()->timestamp);

        return response()->json(['success' => true, 'message' => 'Kode OTP dikirim ke WhatsApp wali.', 'data' => ['wa' => $this->samarkan($s->no_whatsapp)]]);
    }

    /** POST /auth/reset-password — {nis, kode, password} → verifikasi OTP + set password + token. */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'nis' => 'required|string|max:50', 'kode' => 'required|string|size:6',
            'password' => 'required|string|min:6|max:100',
        ]);
        $s = $this->cariSantri($request->nis);
        if (!$s) return response()->json(['success' => false, 'message' => 'NIS tidak ditemukan.'], 404);

        $data = Cache::get("santri-otp:{$s->id}");
        if (!$data) return response()->json(['success' => false, 'message' => 'OTP kadaluarsa. Minta kode baru.'], 422);
        if (($data['tries'] ?? 0) >= 5) { Cache::forget("santri-otp:{$s->id}"); return response()->json(['success' => false, 'message' => 'Terlalu banyak percobaan. Minta kode baru.'], 429); }
        if (!Hash::check($request->kode, $data['hash'])) {
            $data['tries']++; Cache::put("santri-otp:{$s->id}", $data, $data['exp']);
            return response()->json(['success' => false, 'message' => 'Kode OTP salah.'], 422);
        }

        Cache::forget("santri-otp:{$s->id}");
        $s->password = $request->password;
        $s->save();
        return response()->json(['success' => true, 'message' => 'Password berhasil diperbarui.', 'data' => ['token' => $this->buatToken($s), 'santri' => $this->profil($s)]]);
    }

    /** GET /auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->profil($request->user())]);
    }

    /** POST /auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Keluar.']);
    }
}
