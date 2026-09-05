<?php

/*
|--------------------------------------------------------------------------
| Web Push (VAPID)
|--------------------------------------------------------------------------
| Kunci VAPID mengidentifikasi server ini ke layanan push browser (FCM untuk
| Chrome/Android, Mozilla untuk Firefox, Apple untuk iOS 16.4+).
|
| Dibuat SEKALI lalu disimpan di .env server:
|     php artisan webpush:vapid
|
| PENTING: bila kunci publik berubah, SEMUA langganan lama menjadi tidak
| berlaku dan setiap guru harus mengaktifkan ulang notifikasi. Jangan
| membuat ulang kunci kecuali memang terpaksa.
*/

return [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),

    // Wajib berupa URL atau mailto: — dipakai layanan push untuk menghubungi
    // pemilik server bila terjadi masalah pengiriman.
    'subject'     => env('VAPID_SUBJECT', 'https://myannur.id'),
];
