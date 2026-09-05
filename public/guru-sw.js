// Service worker PWA guru: installable + shell cache ringan + Web Push.
// Strategi: network-first untuk navigasi; jangan cache API (/api/*) agar data live.
const CACHE = 'guru-shell-v3'
const SHELL = ['/guru', '/logo.png', '/guru-manifest.json']

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()))
})

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    )
})

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url)
    if (e.request.method !== 'GET') return
    // Jangan intersepsi API/storage — biarkan langsung ke jaringan (data live).
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/storage/')) return

    // Navigasi (SPA) → network-first, fallback ke shell /guru saat offline.
    if (e.request.mode === 'navigate') {
        e.respondWith(fetch(e.request).catch(() => caches.match('/guru')))
        return
    }

    // Aset lain → cache-first sederhana.
    e.respondWith(
        caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
            const copy = res.clone()
            caches.open(CACHE).then((c) => c.put(e.request, copy)).catch(() => {})
            return res
        }).catch(() => hit))
    )
})

// ── Web Push ─────────────────────────────────────────────────────────────
// Handler inilah yang membuat notifikasi tetap muncul di layar HP walaupun
// PWA sudah ditutup: service worker dibangunkan sistem saat push tiba.

self.addEventListener('push', (e) => {
    let d = {}
    try { d = e.data ? e.data.json() : {} } catch (_) { d = { pesan: e.data && e.data.text() } }

    const judul = d.judul || 'An-Nur Smart'
    const opsi = {
        body: d.pesan || '',
        icon: '/guru-icon-192.png',
        badge: '/guru-icon-192.png',
        // tag per jenis event: notifikasi sejenis saling menimpa, jadi layar HP
        // tidak dibanjiri pengingat yang sama.
        tag: d.tag || 'annur',
        renotify: true,
        data: { route: d.route || '/notifikasi' },
        vibrate: [100, 50, 100],
    }

    // waitUntil wajib — tanpa ini SW bisa dimatikan sebelum notifikasi tampil.
    e.waitUntil(self.registration.showNotification(judul, opsi))
})

self.addEventListener('notificationclick', (e) => {
    e.notification.close()
    const tujuan = '/guru' + ((e.notification.data && e.notification.data.route) || '/notifikasi')

    // Kalau PWA sudah terbuka, fokuskan tab itu dan arahkan; jangan buka jendela
    // baru (guru bisa berakhir dengan banyak salinan aplikasi).
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if (c.url.includes('/guru') && 'focus' in c) {
                    c.navigate(tujuan).catch(() => {})
                    return c.focus()
                }
            }
            return clients.openWindow(tujuan)
        })
    )
})

// Browser dapat memperbarui endpoint langganan sewaktu-waktu. Bila itu terjadi
// tanpa dilaporkan, kiriman berikutnya gagal diam-diam — jadi beri tahu klien
// agar mendaftar ulang saat aplikasi dibuka lagi.
self.addEventListener('pushsubscriptionchange', (e) => {
    e.waitUntil(
        self.registration.pushManager.getSubscription().then((sub) => {
            if (!sub) return
            return fetch('/api/v1/push/langganan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: sub.endpoint,
                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('p256dh')))),
                    auth: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey('auth')))),
                }),
            }).catch(() => {})
        })
    )
})
