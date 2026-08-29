<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>An-Nur Smart · Guru</title>

    {{-- PWA --}}
    <meta name="theme-color" content="#06346B">
    <link rel="manifest" href="/guru-manifest.json">
    <link rel="icon" href="/guru-icon-192.png">

    {{-- iOS: installable & tampil seperti app --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="An-Nur Guru">
    <link rel="apple-touch-icon" href="/guru-icon-192.png">

    {{-- Preloader boot (kritis, inline — tampil sebelum bundle JS termuat) --}}
    <style>
        #guru-splash{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;
            background:radial-gradient(130% 130% at 50% -10%,#0C78FF 0%,#0A4C9E 42%,#06346B 100%);
            transition:opacity .4s ease,visibility .4s ease}
        #guru-splash.gs-hide{opacity:0;visibility:hidden}
        #guru-splash .gs-in{text-align:center;color:#fff;padding:24px}
        #guru-splash .gs-logo{width:92px;height:92px;margin:0 auto;border-radius:26px;overflow:hidden;
            background:rgba(255,255,255,.12);box-shadow:0 14px 40px rgba(0,0,0,.28),inset 0 0 0 1px rgba(255,255,255,.18);
            display:grid;place-items:center;animation:gsPulse 1.9s ease-in-out infinite;will-change:transform}
        #guru-splash .gs-logo img{width:100%;height:100%;object-fit:cover;display:block}
        #guru-splash .gs-title{margin:18px 0 0;font:800 20px/1.15 'Plus Jakarta Sans',system-ui,-apple-system,sans-serif;letter-spacing:.2px}
        #guru-splash .gs-sub{margin:5px 0 0;font:500 12px/1 system-ui,-apple-system,sans-serif;color:rgba(255,255,255,.62)}
        #guru-splash .gs-bar{width:124px;height:4px;margin:22px auto 0;border-radius:99px;background:rgba(255,255,255,.16);overflow:hidden}
        #guru-splash .gs-bar>i{display:block;height:100%;width:40%;border-radius:99px;background:#fff;animation:gsBar 1.15s cubic-bezier(.65,0,.35,1) infinite;will-change:transform}
        @keyframes gsPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.07)}}
        @keyframes gsBar{0%{transform:translateX(-140%)}100%{transform:translateX(360%)}}
        @media (prefers-reduced-motion:reduce){#guru-splash .gs-logo{animation:none}#guru-splash .gs-bar>i{animation:none;width:100%;opacity:.8}}
    </style>

    @vite(['resources/js/guru/main.js'])
</head>
<body>
    <div id="guru-splash" role="status" aria-label="Memuat aplikasi">
        <div class="gs-in">
            <div class="gs-logo"><img src="/guru-icon-192.png" alt="An-Nur" width="92" height="92"></div>
            <p class="gs-title">#Intinya Rajin</p>
            <p class="gs-sub">Smart System Pesantren</p>
            <div class="gs-bar"><i></i></div>
        </div>
    </div>
    <div id="guru-app"></div>
</body>
</html>
