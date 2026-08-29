import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import '../../css/guru.css'

const app = createApp(App).use(router)

// Sembunyikan preloader setelah router siap & app ter-mount (paint pertama).
// Minimum tampil splash (dari halaman dibuka) agar loader enak dilihat.
const SPLASH_MIN_MS = 1200

// Buang splash dengan aman. Idempoten — aman dipanggil berkali-kali.
// pointer-events:none (di .gs-hide) langsung berlaku agar splash TIDAK
// menjebak sentuhan (input/keyboard) meski elemen belum ter-remove.
function hideSplash() {
    const splash = document.getElementById('guru-splash')
    if (!splash || splash.classList.contains('gs-hide')) return
    splash.classList.add('gs-hide')
    setTimeout(() => splash.remove(), 500)
}

router.isReady().finally(() => {
    app.mount('#guru-app')
    // performance.now() ≈ waktu sejak navigasi; sisakan hingga genap SPLASH_MIN_MS.
    const sisa = Math.max(0, SPLASH_MIN_MS - performance.now())
    setTimeout(hideSplash, sisa)
})

// Jaring pengaman: apa pun yang terjadi (mis. router.isReady menggantung di
// mode PWA standalone), splash WAJIB hilang — jangan sampai membekukan input.
setTimeout(hideSplash, 5000)
// Impatient tap saat splash masih tampil → langsung lewati.
document.getElementById('guru-splash')?.addEventListener('pointerdown', hideSplash, { once: true })

// Daftarkan service worker PWA (installable + cache shell).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/guru-sw.js', { scope: '/guru/' }).catch(() => {})
    })
}
