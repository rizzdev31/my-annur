// Service worker minimal untuk PWA guru (installable + shell cache ringan).
// Strategi: network-first untuk navigasi; jangan cache API (/api/*) agar data live.
const CACHE = 'guru-shell-v1'
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
