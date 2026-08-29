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
        User::firstOrCreate(['email' => 'superadmin@annur.sch.id'], [
            'name'     => 'Super Admin An Nur',
            'username' => 'superadmin',
            'password' => Hash::make('annur@2025'),
            'role'     => 'super_admin',
            'status'   => 'aktif',
        ]);
    }
}
