<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Tabel utama setting lokasi/wifi absensi ──────────────────────────
        Schema::create('setting_lokasi_absensi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');                       // "Masjid Utama", "Kantor Guru", "WiFi Pesantren"
            $table->enum('tipe', ['koordinat', 'wifi'])
                ->default('koordinat')
                ->comment('koordinat=radius GPS, wifi=SSID/BSSID pesantren');

            // ── Koordinat (dipakai jika tipe = koordinat) ───────────────────
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('radius_meter')->default(100)
                ->comment('Radius toleransi dalam meter');

            // ── WiFi (dipakai jika tipe = wifi) ─────────────────────────────
            $table->string('wifi_ssid')->nullable()
                ->comment('Nama WiFi pesantren, cth: PonpesAnNur');
            $table->string('wifi_bssid')->nullable()
                ->comment('MAC address access point, lebih aman dari SSID');

            // ── Konfigurasi umum ─────────────────────────────────────────────
            $table->boolean('is_aktif')->default(true);
            $table->boolean('izinkan_dinas_luar')->default(true)
                ->comment('Jika true, status dinas_luar boleh absen dari mana saja');
            $table->text('keterangan')->nullable();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Tambah kolom validasi lokasi ke absensi_harian ──────────────────
        Schema::table('absensi_harian', function (Blueprint $table) {
            // Hasil validasi saat absen masuk
            $table->enum('validasi_lokasi', [
                'valid_koordinat',  // dalam radius GPS
                'valid_wifi',       // terhubung WiFi pesantren
                'valid_dinas_luar', // status dinas luar — boleh dari mana saja
                'invalid',          // di luar radius & bukan WiFi pesantren
                'bypass_admin',     // diinput manual oleh admin
                'tidak_diperiksa',  // versi lama / data lama
            ])->default('tidak_diperiksa')->after('keterangan');

            $table->string('nama_wifi')->nullable()->after('validasi_lokasi')
                ->comment('SSID WiFi saat absen');
            $table->string('bssid_wifi')->nullable()->after('nama_wifi')
                ->comment('BSSID WiFi saat absen');
            $table->decimal('jarak_meter', 8, 2)->nullable()->after('bssid_wifi')
                ->comment('Jarak dari titik referensi dalam meter');
            $table->unsignedBigInteger('setting_lokasi_id')->nullable()->after('jarak_meter')
                ->comment('Titik referensi yang dipakai untuk validasi');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_harian', function (Blueprint $table) {
            $table->dropColumn([
                'validasi_lokasi', 'nama_wifi', 'bssid_wifi',
                'jarak_meter', 'setting_lokasi_id',
            ]);
        });
        Schema::dropIfExists('setting_lokasi_absensi');
    }
};