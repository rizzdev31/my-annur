<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->payload($request->user()),
        ]);
    }

    /**
     * Perbarui profil (fleksibel / partial): hanya field yang dikirim yang diubah.
     * Data pribadi & rekening bisa diedit sendiri; data resmi (NIP/NIK/jabatan/
     * jenis guru/status/tanggal masuk) tetap dikelola admin.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Akun
            'name'                => ['sometimes', 'string', 'max:100'],
            'email'               => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            // Data pribadi & kontak (tenaga pendidik)
            'no_hp'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'alamat'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'tempat_lahir'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'tanggal_lahir'       => ['sometimes', 'nullable', 'date'],
            'jenis_kelamin'       => ['sometimes', 'nullable', Rule::in(['L', 'P'])],
            'pendidikan_terakhir' => ['sometimes', 'nullable', Rule::in(['SMA', 'D3', 'S1', 'S2', 'S3', 'Pesantren'])],
            'jurusan'             => ['sometimes', 'nullable', 'string', 'max:100'],
            // Rekening gaji
            'no_rekening'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'nama_bank'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'nama_rekening'       => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $userFields = array_intersect_key($data, array_flip(['name', 'email']));
        $tpFields   = array_intersect_key($data, array_flip([
            'no_hp', 'alamat', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'pendidikan_terakhir', 'jurusan', 'no_rekening', 'nama_bank', 'nama_rekening',
        ]));

        DB::transaction(function () use ($user, $userFields, $tpFields) {
            if ($userFields) {
                $user->update($userFields);
            }
            if ($tpFields && ($tp = $user->tenagaPendidik)) {
                $tp->update($tpFields);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $this->payload($user->fresh()),
        ]);
    }

    public function updateFoto(Request $request): JsonResponse
    {
        $request->validate(['foto' => 'required|image|max:2048']);

        $user = $request->user();
        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
        }
        $path = $request->file('foto')->store('foto-profil', 'public');
        $user->update(['foto' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diperbarui.',
            'data'    => ['foto' => asset('storage/' . $path)],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $user->update(['password' => $request->password_baru]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    /** Susunan data profil (dipakai show & update). */
    private function payload($user): array
    {
        $tp = $user->tenagaPendidik()->with(['jabatan'])->first();

        return [
            // User
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'username'            => $user->username,
            'foto'                => $user->foto ? asset('storage/' . $user->foto) : null,
            // Tenaga Pendidik
            'tenaga_pendidik_id'  => $tp?->id,
            'nip'                 => $tp?->nip,
            'nik'                 => $tp?->nik,
            'no_hp'               => $tp?->no_hp,
            'alamat'              => $tp?->alamat,
            'tempat_lahir'        => $tp?->tempat_lahir,
            'tanggal_lahir'       => $tp?->tanggal_lahir?->format('Y-m-d'),
            'jenis_kelamin'       => $tp?->jenis_kelamin,
            'pendidikan_terakhir' => $tp?->pendidikan_terakhir,
            'jurusan'             => $tp?->jurusan,
            'jabatan'             => $tp?->jabatan?->nama_jabatan ?? '—',
            'jabatan_display'     => $tp?->nama_jabatan_display ?? '—',
            'jenis_guru'          => $tp?->jenis_guru,
            'status_kepegawaian'  => $tp?->status_kepegawaian ?? 'aktif',
            'tanggal_masuk'       => $tp?->tanggal_masuk?->format('Y-m-d'),
            'no_rekening'         => $tp?->no_rekening,
            'nama_bank'           => $tp?->nama_bank,
            'nama_rekening'       => $tp?->nama_rekening,
        ];
    }
}
