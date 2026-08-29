<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SettingPotongan;

return new class extends Migration
{
    /**
     * Seed setting potongan absensi default (keterlambatan & alfa).
     *
     * Konteks: logika perhitungan potongan keterlambatan/alfa SUDAH ADA di
     * PayrollCalculationService, tapi tidak pernah aktif karena belum ada baris
     * SettingPotongan kategori 'absensi' yang memicunya. Migrasi ini membuat
     * setting default (idempotent — aman dijalankan berulang) agar logika
     * pemotongan ketidakhadiran langsung berfungsi.
     *
     * NOMINAL DI BAWAH ADALAH DEFAULT — admin WAJIB menyesuaikan lewat menu
     * Setting Potongan sesuai kebijakan pesantren.
     */
    public function up(): void
    {
        // Saat migrate:fresh, migrasi ini tersortir SEBELUM tabel setting_potongan
        // di-recreate (file recreate tanpa prefix tanggal → tersortir akhir).
        // Kolom 'kode' belum ada → skip; seeding default sudah ditangani di
        // migrasi recreate_setting_potongan_table.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('setting_potongan', 'kode')) {
            return;
        }

        // Potongan per kejadian terlambat
        SettingPotongan::firstOrCreate(
            ['kode' => 'TERLAMBAT_DEFAULT'],
            [
                'nama'             => 'Potongan Keterlambatan',
                'kategori'         => 'absensi',
                'tipe_pemicu'      => 'per_keterlambatan',
                'tipe_nominal'     => 'nominal',
                'nominal'          => 10000,   // Rp 10.000 / kejadian — DEFAULT, silakan ubah
                'nominal_maksimal' => 100000,  // batas maksimal potongan terlambat / bulan
                'lingkup'          => 'semua',
                'deskripsi'        => 'Potongan otomatis per kejadian keterlambatan. Sesuaikan nominal sesuai kebijakan.',
                'tampil_di_slip'   => true,
                'is_aktif'         => true,
            ]
        );

        // Potongan per hari alfa (tanpa keterangan)
        SettingPotongan::firstOrCreate(
            ['kode' => 'ALFA_DEFAULT'],
            [
                'nama'             => 'Potongan Alfa',
                'kategori'         => 'absensi',
                'tipe_pemicu'      => 'per_alfa',
                'tipe_nominal'     => 'nominal',
                'nominal'          => 50000,   // Rp 50.000 / hari alfa — DEFAULT, silakan ubah
                'nominal_maksimal' => null,
                'lingkup'          => 'semua',
                'deskripsi'        => 'Potongan otomatis per hari alfa (tidak hadir tanpa keterangan). Sesuaikan nominal sesuai kebijakan.',
                'tampil_di_slip'   => true,
                'is_aktif'         => true,
            ]
        );
    }

    public function down(): void
    {
        SettingPotongan::whereIn('kode', ['TERLAMBAT_DEFAULT', 'ALFA_DEFAULT'])->delete();
    }
};
