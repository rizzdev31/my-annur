<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Hanya akun Super Admin (tanpa demo guru/santri).
 * Dipakai untuk reset bersih: data guru & santri diisi dari UI lalu disinkron ke RamahAnak.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        // Password TIDAK di-hardcode (repo publik). Diambil dari env
        // SUPERADMIN_PASSWORD; fallback hanya penanda agar wajib diganti.
        // firstOrCreate → tidak menimpa akun yang sudah ada (lokal aman).
        User::firstOrCreate(['email' => 'superadmin@annur.sch.id'], [
            'name'     => 'Super Admin An Nur',
            'username' => 'superadmin',
            'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'ubah-password-ini-segera')),
            'role'     => 'super_admin',
            'status'   => 'aktif',
        ]);
    }
}
