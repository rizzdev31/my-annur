<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Password Portal Santri/Wali — login harian tanpa OTP berulang. Additive & nullable
 *  (santri lama belum aktivasi → null → wajib aktivasi via tanggal lahir / OTP). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santri', function (Blueprint $t) {
            $t->string('password')->nullable()->after('no_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('santri', fn(Blueprint $t) => $t->dropColumn('password'));
    }
};
