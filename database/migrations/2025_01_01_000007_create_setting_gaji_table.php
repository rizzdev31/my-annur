<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gaji pokok per jabatan (bisa di-override per individu)
        Schema::create('setting_gaji_pokok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_id')->constrained('jabatan');
            $table->decimal('nominal', 12, 2)->comment('Nominal gaji pokok jabatan');
            $table->date('berlaku_mulai');
            $table->date('berlaku_selesai')->nullable()->comment('Null = masih berlaku');
            $table->text('keterangan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['jabatan_id', 'is_aktif']);
        });

        // Override gaji pokok per individu (jika berbeda dari jabatan)
        Schema::create('gaji_pokok_individu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik');
            $table->decimal('nominal', 12, 2);
            $table->date('berlaku_mulai');
            $table->date('berlaku_selesai')->nullable();
            $table->text('alasan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();
        });

        // Komponen potongan tetap (BPJS, dll)
        Schema::create('setting_potongan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Nama potongan, misal: BPJS Kesehatan, BPJS TK');
            $table->enum('tipe_nominal', ['tetap', 'persen'])
                  ->comment('tetap=nominal pasti, persen=persentase dari gaji pokok');
            $table->decimal('nominal', 12, 2)->default(0)
                  ->comment('Jika tipe tetap: nominal. Jika persen: nilai persen (misal 1.5)');
            $table->boolean('berlaku_untuk_semua')->default(true);
            $table->json('jabatan_ids')->nullable()->comment('Jika tidak semua jabatan');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_potongan');
        Schema::dropIfExists('gaji_pokok_individu');
        Schema::dropIfExists('setting_gaji_pokok');
    }
};