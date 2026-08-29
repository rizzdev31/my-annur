import { reactive } from 'vue'

// Deteksi platform & status terpasang.
const mm = typeof window !== 'undefined' ? window.matchMedia?.('(display-mode: standalone)') : null
const standalone = (mm && mm.matches) || (typeof navigator !== 'undefined' && navigator.standalone === true)
const ua = typeof navigator !== 'undefined' ? navigator.userAgent : ''
const isIOS = /iphone|ipad|ipod/i.test(ua)
const isAndroid = /android/i.test(ua)

export const pwa = reactive({
    canInstall: false,          // Android/Chrome sudah kirim beforeinstallprompt
    installed: !!standalone,    // sudah dibuka sebagai app (standalone)
    isIOS,
    isAndroid,
    dismissed: typeof localStorage !== 'undefined' && localStorage.getItem('pwa_install_dismissed') === '1',
    _deferred: null,
})

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault()
        pwa._deferred = e
        pwa.canInstall = true
    })
    window.addEventListener('appinstalled', () => {
        pwa.installed = true
        pwa.canInstall = false
        pwa._deferred = null
    })
}

/** Trigger prompt native (Android). Kembalikan 'accepted' | 'dismissed' | 'no-prompt'. */
export async function promptInstall() {
    if (!pwa._deferred) return 'no-prompt'
    pwa._deferred.prompt()
    let outcome = 'dismissed'
    try { ({ outcome } = await pwa._deferred.userChoice) } catch (_) {}
    pwa._deferred = null
    pwa.canInstall = false
    if (outcome === 'accepted') pwa.installed = true
    return outcome
}

export function dismissInstall() {
    pwa.dismissed = true
    try { localStorage.setItem('pwa_install_dismissed', '1') } catch (_) {}
}

/** Munculkan lagi banner install (mis. dari menu drawer). */
export function resetInstallBanner() {
    pwa.dismissed = false
    try { localStorage.removeItem('pwa_install_dismissed') } catch (_) {}
}

/** Layak tampil banner: belum terpasang, belum di-dismiss, & (Android installable ATAU iOS). */
export function bisaTampilBanner() {
    return !pwa.installed && !pwa.dismissed && (pwa.canInstall || pwa.isIOS)
}
