// Service worker Portal Santri — network-first navigasi; TIDAK cache /api & /storage.
const CACHE = 'santri-shell-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.pathname.startsWith('/api') || url.pathname.startsWith('/storage')) return;
    if (e.request.mode === 'navigate') {
        e.respondWith(fetch(e.request).catch(() => caches.match('/santri')));
    }
});
