<?php

return [
    // Token DEVICE Fonnte (dari dashboard Fonnte). HANYA di .env, jangan di repo.
    'token'   => env('FONNTE_TOKEN'),

    // Endpoint kirim pesan Fonnte.
    'url'     => env('FONNTE_URL', 'https://api.fonnte.com/send'),

    // Master switch — bila false, WA TIDAK dikirim (outbox tetap tercatat).
    'enabled' => (bool) env('FONNTE_ENABLED', false),

    // Kode negara default untuk normalisasi nomor (08xxx → 62xxx).
    'country' => env('FONNTE_COUNTRY', '62'),

    // ── Identitas pesan (branding "bot") ──
    // Nama instansi yang tampil sebagai header tiap pesan WA.
    'nama'    => env('WA_NAMA_BOT', 'PPM An-Nur'),
    // Baris penutup/footer tiap pesan.
    'footer'  => env('WA_FOOTER', 'Pesan otomatis Sistem Informasi Santri — mohon tidak membalas.'),

    // Secret untuk memverifikasi webhook incoming Fonnte (dicek via ?secret= atau header).
    'webhook_secret' => env('WA_WEBHOOK_SECRET'),
];
