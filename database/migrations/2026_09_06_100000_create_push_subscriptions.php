<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langganan Web Push per PERANGKAT (bukan per user) — satu guru bisa memasang
 * PWA di beberapa HP, masing-masing punya endpoint sendiri.
 *
 * `endpoint` unik: browser menerbitkan URL berbeda tiap langganan, dan URL itu
 * yang jadi alamat kirim. Bila guru mencabut izin lalu memberi lagi, endpoint
 * baru terbit → baris baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Endpoint bisa panjang (FCM/Mozilla) → pakai text + indeks hash pendek.
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();

            // Kunci enkripsi payload milik perangkat (dari PushSubscription browser).
            $table->string('p256dh', 255);
            $table->string('auth', 255);

            $table->string('perangkat', 255)->nullable();   // ringkasan user-agent
            $table->timestamp('terakhir_dipakai')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
