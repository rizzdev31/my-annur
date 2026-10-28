<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SETTING SLIP GAJI — penanda tangan slip beserta gambar tanda tangannya.
 *
 * Latar 28 Okt 2026: nama bendahara di slip DITULIS KERAS di
 * SlipGajiBuilder::instansi() ("L'ANATUS SHOLIHAH, S.Pd."), jadi ketika
 * bendaharanya berganti ke Riska Nurdiyanti slipnya tetap mencantumkan nama
 * lama dan tidak ada yang bisa memperbaikinya tanpa mengubah kode.
 *
 * ══ MENGAPA NAMA BOLEH KOSONG ══
 * `nama_bendahara` bersifat OPSIONAL. Bila kosong, nama diambil dari pemegang
 * jabatan "Bendahara" yang sedang aktif — sehingga pergantian bendahara di data
 * kepegawaian otomatis tercermin di slip, tanpa ada yang perlu ingat menyunting
 * pengaturan ini. Nama diisi hanya bila memang ingin menimpa (mis. ejaan resmi
 * berbeda dari nama akun, atau penanda tangannya bukan pemegang jabatan itu).
 *
 * `ttd_path` menunjuk berkas PNG di disk publik. Gambar disimpan sebagai berkas,
 * bukan base64 di kolom, agar baris pengaturan tetap ringan dan gambarnya bisa
 * dilayani langsung oleh web server saat slip dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_slip_gaji', function (Blueprint $t) {
            $t->id();
            $t->string('nama_bendahara', 150)->nullable()
              ->comment('Kosong = ambil dari pemegang jabatan Bendahara yang aktif');
            $t->string('jabatan_penanda_tangan', 100)->default('Bendahara')
              ->comment('Label yang tercetak di atas nama, dan jabatan yang dicari bila nama kosong');
            $t->string('ttd_path', 255)->nullable()->comment('PNG di disk publik');
            $t->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Satu baris saja; selalu dibaca/ditulis lewat SettingSlipGaji::ambil().
        DB::table('setting_slip_gaji')->insert([
            'nama_bendahara'         => null,
            'jabatan_penanda_tangan' => 'Bendahara',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_slip_gaji');
    }
};
