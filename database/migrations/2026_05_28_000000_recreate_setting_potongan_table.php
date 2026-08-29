<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rebuild tabel setting_potongan dengan struktur yang lebih lengkap
        Schema::dropIfExists('setting_potongan');

        Schema::create('setting_potongan', function (Blueprint $table) {
            $table->id();

            // Identitas
            $table->string('nama');                    // "Potongan BPJS Ketenagakerjaan"
            $table->string('kode', 20)->unique();      // "BPJS_TK", "ALFA_1", "TABUNGAN" — untuk referensi di slip

            // Kategori potongan — menentukan kelompok di slip gaji
            $table->enum('kategori', [
                'absensi',    // Potongan karena keterlambatan/alfa — otomatis dari absensi
                'wajib',      // Potongan wajib tetap per bulan (BPJS, dll)
                'simpanan',   // Simpanan/tabungan (koperasi, dll)
                'pinjaman',   // Cicilan pinjaman
                'lainnya',    // Lainnya/custom
            ])->default('lainnya');

            // Tipe pemicu — menentukan kapan potongan ini dihitung
            $table->enum('tipe_pemicu', [
                'per_keterlambatan',  // Per kejadian terlambat
                'per_alfa',           // Per hari alfa/tidak hadir
                'per_bulan',          // Flat per bulan (wajib)
                'persen_gaji',        // Persentase dari gaji pokok
                'manual',             // Input manual per bulan
            ])->default('per_bulan');

            // Nominal
            $table->enum('tipe_nominal', ['nominal', 'persen'])->default('nominal');
            $table->decimal('nominal', 12, 2)->default(0);
            $table->decimal('nominal_maksimal', 12, 2)->nullable()
                ->comment('Batas maksimal jika tipe persen');

            // Lingkup berlaku
            $table->enum('lingkup', ['semua', 'per_jabatan', 'per_individu', 'custom'])
                ->default('semua');
            $table->json('jabatan_ids')->nullable();
            $table->json('tenaga_pendidik_ids')->nullable();

            // Keterangan
            $table->text('deskripsi')->nullable();
            $table->boolean('tampil_di_slip')->default(true)
                ->comment('Apakah tampil di slip gaji?');
            $table->boolean('is_aktif')->default(true);

            // Audit
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kategori', 'is_aktif']);
            $table->index('tipe_pemicu');
        });

        // Seed default potongan absensi (idempotent). Dipindah ke sini agar saat
        // migrate:fresh — di mana migrasi ini tersortir setelah seed 2026_05_29_170000
        // (yang di-skip karena kolom 'kode' belum ada) — default tetap terbentuk.
        $now = now();
        foreach ([
            [
                'kode' => 'TERLAMBAT_DEFAULT', 'nama' => 'Potongan Keterlambatan',
                'kategori' => 'absensi', 'tipe_pemicu' => 'per_keterlambatan',
                'tipe_nominal' => 'nominal', 'nominal' => 10000, 'nominal_maksimal' => 100000,
                'lingkup' => 'semua',
                'deskripsi' => 'Potongan otomatis per kejadian keterlambatan. Sesuaikan nominal sesuai kebijakan.',
                'tampil_di_slip' => true, 'is_aktif' => true,
            ],
            [
                'kode' => 'ALFA_DEFAULT', 'nama' => 'Potongan Alfa',
                'kategori' => 'absensi', 'tipe_pemicu' => 'per_alfa',
                'tipe_nominal' => 'nominal', 'nominal' => 50000, 'nominal_maksimal' => null,
                'lingkup' => 'semua',
                'deskripsi' => 'Potongan otomatis per hari alfa (tidak hadir tanpa keterangan). Sesuaikan nominal sesuai kebijakan.',
                'tampil_di_slip' => true, 'is_aktif' => true,
            ],
        ] as $row) {
            $exists = \Illuminate\Support\Facades\DB::table('setting_potongan')
                ->where('kode', $row['kode'])->exists();
            if (!$exists) {
                \Illuminate\Support\Facades\DB::table('setting_potongan')
                    ->insert($row + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_potongan');
    }
};