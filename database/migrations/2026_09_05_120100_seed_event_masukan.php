<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Event notifikasi untuk kanal Saran & Masukan (dua arah). */
return new class extends Migration
{
    private array $events = [
        [
            'event_kode' => 'masukan.baru',
            'nama'       => 'Masukan Baru dari Pengguna',
            'kategori'   => 'Saran & Masukan',
            'deskripsi'  => 'Ada saran / laporan bug baru dari pengguna sistem.',
            // Token yang dikenal NotifikasiService::resolvePenerima() adalah
            // 'admin' (= user ber-role super_admin). Bukan 'super_admin'.
            'penerima'   => ['admin'],
            'maks'       => 30,
        ],
        [
            'event_kode' => 'masukan.balasan',
            'nama'       => 'Balasan Masukan',
            'kategori'   => 'Saran & Masukan',
            'deskripsi'  => 'Admin membalas atau mengubah status saran/laporan Anda.',
            'penerima'   => [],   // diisi service: pemilik utas
            'maks'       => 30,
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;

        foreach ($this->events as $e) {
            if (DB::table('setting_notifikasi')->where('event_kode', $e['event_kode'])->exists()) continue;

            DB::table('setting_notifikasi')->insert([
                'event_kode'    => $e['event_kode'],
                'nama'          => $e['nama'],
                'kategori'      => $e['kategori'],
                'deskripsi'     => $e['deskripsi'],
                'wajib'         => false,
                'aktif'         => true,
                'penerima'      => json_encode($e['penerima']),
                'kanal'         => json_encode(['in_app' => true]),
                'reminder'      => null,
                'eskalasi'      => null,
                'maks_per_hari' => $e['maks'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;

        DB::table('setting_notifikasi')
            ->whereIn('event_kode', array_column($this->events, 'event_kode'))->delete();
    }
};
