<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan gaji MURNI per-guru (tidak terhubung ke absensi/mengajar).
 *   - jenis_potongan  : daftar item (VOUCHER, SIMPANAN POKOK, LAZISMU, dst) + kategori
 *   - potongan_guru   : nominal tiap guru untuk tiap item (satu baris per guru per item)
 *
 * Payroll menjumlahkan nominal aktif tiap guru → potongan gaji, ditampilkan di slip
 * sesuai kategori. Guru tanpa nominal = tidak kena potongan itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_potongan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');                       // "Voucher An Nur Mart"
            $table->enum('kategori', ['wajib', 'simpanan', 'pinjaman', 'lainnya'])->default('wajib');
            $table->boolean('tampil_di_slip')->default(true);
            $table->integer('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('potongan_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_potongan_id')->constrained('jenis_potongan')->cascadeOnDelete();
            $table->foreignId('tenaga_pendidik_id')->constrained('tenaga_pendidik')->cascadeOnDelete();
            $table->decimal('nominal', 12, 2)->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->unique(['jenis_potongan_id', 'tenaga_pendidik_id']);
        });

        // Seed 5 item awal (bisa ditambah/ubah admin nanti).
        $now = now();
        $items = [
            ['nama' => 'Voucher An Nur Mart',      'kategori' => 'wajib',    'urutan' => 1],
            ['nama' => 'Simpanan Pokok & Wajib',   'kategori' => 'wajib',    'urutan' => 2],
            ['nama' => 'LAZISMU',                  'kategori' => 'wajib',    'urutan' => 3],
            ['nama' => 'Simpanan Sukarela',        'kategori' => 'simpanan', 'urutan' => 4],
            ['nama' => 'Simpan Pinjam',            'kategori' => 'pinjaman', 'urutan' => 5],
        ];
        foreach ($items as $i) {
            DB::table('jenis_potongan')->insert(array_merge($i, [
                'tampil_di_slip' => true, 'is_aktif' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('potongan_guru');
        Schema::dropIfExists('jenis_potongan');
    }
};
