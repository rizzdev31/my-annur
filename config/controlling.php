<?php

return [
    // Token perangkat scan (web kiosk / ESP32-CAM) — header: X-Device-Token.
    // Diisi di .env (CONTROLLING_DEVICE_TOKEN). Endpoint scan device-agnostic.
    'device_token' => env('CONTROLLING_DEVICE_TOKEN'),

    // Pemetaan jawaban absensi → kode variabel RamahAnak (fleksibel via .env).
    // Hadir TIDAK pernah dikirim. Kosongkan nilai untuk menonaktifkan pengiriman
    // status tsb (mis. CONTROLLING_KODE_ALPHA= kosong → alpha tidak dikirim).
    // Default: telat & alpha sama-sama P002 (disiplin waktu) — bobot setara.
    'absensi_kode' => [
        'telat' => env('CONTROLLING_KODE_TELAT', 'P002'),
        'alpha' => env('CONTROLLING_KODE_ALPHA', 'P002'),
    ],
];
