<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roster shift (overlay jam kerja per rentang tanggal) — untuk satpam yang shift-nya
 * berganti tiap minggu/bulan. TIDAK mengubah setting_jam_kerja_id asli guru; hanya
 * menimpa jam kerja untuk rentang tanggal tertentu (dibaca jamKerjaAktif($tanggal)).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jadwal_shift')) return;

        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
            $table->foreignId('setting_jam_kerja_id')->constrained('setting_jam_kerja')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenaga_pendidik_id', 'tanggal_mulai', 'tanggal_selesai'], 'idx_shift_guru_rentang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_shift');
    }
};
