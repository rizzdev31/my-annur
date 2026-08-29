<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADDITIVE: tambah nilai 'admin' pada enum users.role untuk akun admin berperan.
 * Tidak menghapus/mengubah nilai lama (super_admin/tenaga_pendidik/santri).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','tenaga_pendidik','santri','admin') NOT NULL DEFAULT 'tenaga_pendidik'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','tenaga_pendidik','santri') NOT NULL DEFAULT 'tenaga_pendidik'");
    }
};
