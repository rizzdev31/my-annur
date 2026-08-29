<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Pivot table jabatan_guru ──────────────────────────────────────────
        // Menggantikan kolom jabatan_id di tenaga_pendidik
        // Mendukung rangkap jabatan (1 guru → banyak jabatan)
        Schema::create('jabatan_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenaga_pendidik_id')
                  ->constrained('tenaga_pendidik')
                  ->cascadeOnDelete();
            $table->foreignId('jabatan_id')
                  ->constrained('jabatan');
            $table->boolean('adalah_utama')->default(false)
                  ->comment('True = jabatan utama/primer, False = jabatan rangkap');
            $table->date('berlaku_mulai');
            $table->date('berlaku_selesai')->nullable()
                  ->comment('Null = masih aktif');
            $table->text('keterangan')->nullable()
                  ->comment('Catatan SK atau alasan penugasan');
            $table->foreignId('ditetapkan_oleh')
                  ->nullable()
                  ->constrained('users');
            $table->timestamps();

            // Satu guru tidak boleh memegang jabatan yang sama 2x di waktu bersamaan
            $table->unique(['tenaga_pendidik_id', 'jabatan_id', 'berlaku_mulai'],
                'unique_jabatan_guru_aktif');

            $table->index(['tenaga_pendidik_id', 'adalah_utama']);
            $table->index(['jabatan_id']);
        });

        // ── Migrasi data existing: jabatan_id → jabatan_guru ─────────────────
        // Ambil semua guru yang punya jabatan_id, pindahkan ke pivot
        $guru = DB::table('tenaga_pendidik')
            ->whereNotNull('jabatan_id')
            ->get(['id', 'jabatan_id', 'tanggal_masuk', 'created_at']);

        foreach ($guru as $g) {
            DB::table('jabatan_guru')->insert([
                'tenaga_pendidik_id' => $g->id,
                'jabatan_id'         => $g->jabatan_id,
                'adalah_utama'       => true,
                'berlaku_mulai'      => $g->tanggal_masuk ?? now()->toDateString(),
                'berlaku_selesai'    => null,
                'keterangan'         => 'Migrasi dari data jabatan_id existing',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_guru');
    }
};