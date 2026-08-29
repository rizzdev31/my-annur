<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenaga_pendidik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('jabatan_id')->constrained('jabatan');
            $table->string('nip')->unique()->comment('Nomor Induk Pegawai pesantren');
            $table->string('nik', 16)->nullable()->comment('NIK KTP');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->enum('pendidikan_terakhir', ['SMA', 'D3', 'S1', 'S2', 'S3', 'Pesantren'])->nullable();
            $table->string('jurusan')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->string('no_rekening', 30)->nullable();
            $table->string('nama_bank', 50)->nullable();
            $table->string('nama_rekening')->nullable();
            $table->enum('jenis_guru', ['mukim', 'non_mukim'])
                  ->default('non_mukim')
                  ->comment('mukim=tinggal di pesantren, non_mukim=dari luar');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jabatan_id', 'is_aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenaga_pendidik');
    }
};