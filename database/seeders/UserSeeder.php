<?php
// ══════════════════════════════════════════════════════════════════════════════
// database/seeders/UserSeeder.php
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin — password dari env (repo publik), bukan hardcode.
        User::firstOrCreate(['email' => 'superadmin@annur.sch.id'], [
            'name'     => 'Super Admin An Nur',
            'username' => 'superadmin',
            'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'ubah-password-ini-segera')),
            'role'     => 'super_admin',
            'status'   => 'aktif',
        ]);

        // Demo tenaga pendidik
        $guruDemo = [
            ['name' => 'Ust. Ahmad Fauzi',    'username' => 'ahmad.fauzi',    'email' => 'ahmad@annur.sch.id'],
            ['name' => 'Ust. Muhammad Ridwan', 'username' => 'muh.ridwan',     'email' => 'ridwan@annur.sch.id'],
            ['name' => 'Usth. Siti Fatimah',  'username' => 'siti.fatimah',   'email' => 'siti@annur.sch.id'],
            ['name' => 'Ust. Abdul Aziz',     'username' => 'abdul.aziz',     'email' => 'aziz@annur.sch.id'],
            ['name' => 'Ust. Hasan Basri',    'username' => 'hasan.basri',    'email' => 'hasan@annur.sch.id'],
        ];

        foreach ($guruDemo as $g) {
            User::firstOrCreate(['email' => $g['email']], array_merge($g, [
                'password' => Hash::make('password'),
                'role'     => 'tenaga_pendidik',
                'status'   => 'aktif',
            ]));
        }
    }
}