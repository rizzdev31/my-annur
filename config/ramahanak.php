<?php

return [
    // Base URL API RamahAnak (penerima). Lihat PRD-04/05.
    'url'   => env('RAMAHANAK_API_URL', 'https://ramahanak.ppmannursidoarjo.com/api/v1'),
    // Token integrasi (Bearer) — diisi di .env, JANGAN di repo.
    'token' => env('RAMAHANAK_API_TOKEN'),
    // Identitas aplikasi pengirim (jejak audit di RamahAnak).
    'app'   => env('RAMAHANAK_APP_NAME', 'annur-smart-habbit'),
    // Aktif/nonaktif pengiriman (mis. matikan saat dev tanpa token).
    'enabled' => (bool) env('RAMAHANAK_ENABLED', false),
];
