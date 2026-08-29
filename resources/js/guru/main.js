import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import '../../css/guru.css'

const app = createApp(App).use(router)

// Sembunyikan preloader setelah router siap & app ter-mount (paint pertama).
// Minimum tampil splash (dari halaman dibuka) agar loader enak dilihat.
const SPLASH_MIN_MS = 3500

router.isReady().finally(() => {
    app.mount('#guru-app')
    const splash = document.getElementById('guru-splash')
    if (!splash) return
    // performance.now() ≈ waktu sejak navigasi; sisakan hingga genap SPLASH_MIN_MS.
    const sisa = Math.max(0, SPLASH_MIN_MS - performance.now())
    setTimeout(() => {
        splash.classList.add('gs-hide')        // mulai fade-out (CSS transition)
        setTimeout(() => splash.remove(), 500)  // bersihkan setelah transisi
    }, sisa)
})

// Daftarkan service worker PWA (installable + cache shell).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/guru-sw.js', { scope: '/guru/' }).catch(() => {})
    })
}
