<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Education — Fase 1: Fondasi Data
 * - santri (master data, tanpa akun login)
 * - kelas (satu tabel, jenis sekolah|tahfidz)
 * - kelas_santri (pivot many-to-many; santri bisa di kelas sekolah & tahfidz)
 * - jadwal_mengajar.kelas_id (ADITIF; kolom string `kelas` lama DIPERTAHANKAN)
 * - backfill kelas_id dari string `kelas` yang sudah ada → payroll/absen tidak terganggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Santri ─────────────────────────────────────────────────────────
        Schema::create('santri', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique()->comment('Nomor Induk Santri — kunci tarik data ramahanak');
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('email')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('no_whatsapp', 20)->nullable()->comment('Nomor WA ortu/wali untuk notifikasi');
            $table->string('foto')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_aktif');
        });

        // ── 2. Kelas (sekolah & tahfidz dalam satu tabel) ─────────────────────
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->comment('Nama kelas/rombel, misal: VII-A, Tahfidz Ula-1');
            $table->enum('jenis', ['sekolah', 'tahfidz'])->default('sekolah');
            $table->string('tingkat')->nullable();
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained('tahun_ajaran')->nullOnDelete();
            $table->foreignId('wali_kelas_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['jenis', 'is_aktif']);
        });

        // ── 3. Pivot kelas_santri ─────────────────────────────────────────────
        Schema::create('kelas_santri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->date('tanggal_masuk')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['kelas_id', 'santri_id']);
        });

        // ── 4. jadwal_mengajar.kelas_id (ADITIF) ──────────────────────────────
        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('mata_pelajaran_id')
                  ->constrained('kelas')->nullOnDelete();
        });

        // ── 5. Backfill: string `kelas` → master kelas + kelas_id ─────────────
        $rows = DB::table('jadwal_mengajar')
            ->select('id', 'kelas', 'tahun_ajaran_id')
            ->whereNotNull('kelas')->where('kelas', '!=', '')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $nama = trim($r->kelas);
            if ($nama === '') continue;
            $key = $nama . '|' . ($r->tahun_ajaran_id ?? '0');

            if (!isset($map[$key])) {
                $map[$key] = DB::table('kelas')->insertGetId([
                    'nama'            => $nama,
                    'jenis'           => 'sekolah',
                    'tahun_ajaran_id' => $r->tahun_ajaran_id,
                    'is_aktif'        => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            DB::table('jadwal_mengajar')->where('id', $r->id)->update(['kelas_id' => $map[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->dropColumn('kelas_id');
        });

        Schema::dropIfExists('kelas_santri');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('santri');
    }
};
