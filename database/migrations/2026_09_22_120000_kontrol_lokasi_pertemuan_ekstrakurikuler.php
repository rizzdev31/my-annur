<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrol pengisian pertemuan ekstrakurikuler (22 Sep 2026).
 *
 * Sebelumnya pembina bisa membuka pertemuan untuk tanggal apa pun, dari mana pun,
 * dan vakasi langsung cair tanpa bukti kehadiran. Kini titik lokasi pembina
 * direkam & divalidasi dengan mesin yang sama dengan absensi harian
 * (LokasiAbsensiService → lokasi global pesantren + lokasi per-guru).
 *
 * `wajib_lokasi` = pintu keluar untuk ekskul yang memang di luar pesantren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekstrakurikuler', function (Blueprint $t) {
            $t->boolean('wajib_lokasi')->default(true)->after('lokasi');
        });

        Schema::table('ekstrakurikuler_pertemuan', function (Blueprint $t) {
            $t->decimal('lat', 10, 8)->nullable()->after('materi');
            $t->decimal('lng', 11, 8)->nullable()->after('lat');
            $t->decimal('jarak_meter', 8, 2)->nullable()->after('lng');
            $t->string('validasi_lokasi', 30)->default('tidak_diperiksa')->after('jarak_meter');
            $t->unsignedBigInteger('setting_lokasi_id')->nullable()->after('validasi_lokasi');
            $t->string('nama_wifi', 100)->nullable()->after('setting_lokasi_id');
        });
    }

    public function down(): void
    {
        Schema::table('ekstrakurikuler', fn (Blueprint $t) => $t->dropColumn('wajib_lokasi'));
        Schema::table('ekstrakurikuler_pertemuan', fn (Blueprint $t) => $t->dropColumn([
            'lat', 'lng', 'jarak_meter', 'validasi_lokasi', 'setting_lokasi_id', 'nama_wifi',
        ]));
    }
};
