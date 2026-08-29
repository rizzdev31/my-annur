<?php

/*
| Sumber berita untuk banner Beranda PWA guru.
| Diambil server-side dari CMS pesantren (default: WordPress wp-json) lalu di-cache,
| supaya PWA memanggil origin sendiri (tanpa CORS). Ganti url/endpoint bila CMS beda.
*/

return [
    'url'           => env('BERITA_CMS_URL', 'https://ppmannursidoarjo.com'),
    'enabled'       => (bool) env('BERITA_ENABLED', true),
    'cache_minutes' => (int) env('BERITA_CACHE_MINUTES', 30),
    'limit'         => (int) env('BERITA_LIMIT', 6),
];
