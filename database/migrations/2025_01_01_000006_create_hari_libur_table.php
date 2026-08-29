<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tanggal');
            $table->date('tanggal_selesai')->nullable()->comment('Jika libur lebih dari 1 hari');
            $table->enum('tipe', ['nasional', 'pesantren', 'darurat'])
                  ->comment('nasional=libur negara, pesantren=kebijakan pesantren, darurat=mendadak');
            $table->text('keterangan')->nullable();
            $table->boolean('pengaruh_gaji')->default(true)
                  ->comment('false = tetap dihitung hadir meski libur (misal piket)');
            $table->foreignId('dibuat_oleh')->constrained('users')
                  ->comment('Siapa superadmin yang input');
            $table->timestamps();

            $table->index(['tanggal', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};