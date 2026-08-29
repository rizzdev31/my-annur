<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Tahfidz — Setting penilaian (berlaku tahfidz & tahsin).
 * Singleton (1 baris). Ambang lulus default 7, rentang 1..10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('nilai_min')->default(1);
            $table->unsignedTinyInteger('nilai_maks')->default(10);
            $table->decimal('nilai_lulus', 4, 1)->default(7);
            $table->timestamps();
        });

        DB::table('setting_tahfidz')->insert([
            'nilai_min' => 1, 'nilai_maks' => 10, 'nilai_lulus' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_tahfidz');
    }
};
