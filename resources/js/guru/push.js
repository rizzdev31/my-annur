import { reactive } from 'vue'
import api from './api'

/**
 * Notifikasi push (Web Push) untuk PWA guru.
 *
 * Catatan perangkat yang menentukan perilaku di sini:
 *  - iPhone/iPad: HANYA bisa setelah PWA di-"Add to Home Screen" (iOS 16.4+).
 *    Di tab Safari, `PushManager` memang tidak tersedia — itulah cara kita
 *    mendeteksinya, lalu menampilkan panduan alih-alih tombol yang pasti gagal.
 *  - Izin hanya boleh diminta dari gestur pengguna (tap tombol), dan bila
 *    ditolak browser TIDAK akan bertanya lagi — jadi jangan pernah memanggil
 *    aktifkan() otomatis saat halaman dimuat.
 */
export const push = reactive({
    didukung: false,       // browser punya SW + PushManager + Notification
    izin: 'default',       // default | granted | denied
    aktif: false,          // perangkat ini sudah terdaftar di server
    siapServer: false,     // VAPID terpasang di server
    perluHomeScreen: false, // iOS tapi belum dipasang ke layar utama
    sibuk: false,
    pesan: '',
})

const iOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent)
const standalone = () =>
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const raw = window.atob(base64)
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)))
}

const b64 = (buf) => btoa(String.fromCharCode.apply(null, new Uint8Array(buf)))

/** Baca status terkini — aman dipanggil kapan saja, tidak memicu prompt izin. */
export async function cekPush() {
    push.didukung = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
    push.perluHomeScreen = iOS() && !standalone()

    if (!push.didukung) return
    push.izin = Notification.permission

    try {
        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.getSubscription()
        push.aktif = !!sub

        const d = (await api.get('/push/kunci')).data.data
        push.siapServer = !!d.siap

        // Langganan ada di browser tapi server tidak punya catatannya (mis. DB
        // dibersihkan) → daftarkan ulang diam-diam supaya tidak "aktif palsu".
        if (sub && d.siap && d.terdaftar === 0) await simpan(sub)
    } catch (_) { /* biarkan status apa adanya */ }
}

async function simpan(sub) {
    await api.post('/push/langganan', {
        endpoint: sub.endpoint,
        p256dh: b64(sub.getKey('p256dh')),
        auth: b64(sub.getKey('auth')),
    })
    push.aktif = true
}

/** Minta izin & daftarkan perangkat. WAJIB dipanggil dari tap pengguna. */
export async function aktifkanPush() {
    push.pesan = ''

    if (push.perluHomeScreen) {
        push.pesan = 'Di iPhone, aplikasi harus dipasang ke Layar Utama dulu lewat tombol Bagikan → Tambah ke Layar Utama, lalu buka dari ikonnya.'
        return false
    }
    if (!push.didukung) {
        push.pesan = 'Browser ini belum mendukung notifikasi HP. Coba Chrome (Android) atau perbarui iOS ke 16.4+.'
        return false
    }

    push.sibuk = true
    try {
        const d = (await api.get('/push/kunci')).data.data
        if (!d.siap || !d.public_key) {
            push.pesan = 'Server belum dikonfigurasi untuk notifikasi. Hubungi admin.'
            return false
        }

        const izin = await Notification.requestPermission()
        push.izin = izin
        if (izin !== 'granted') {
            push.pesan = izin === 'denied'
                ? 'Notifikasi diblokir. Aktifkan lewat setelan situs di browser, lalu coba lagi.'
                : 'Izin notifikasi belum diberikan.'
            return false
        }

        const reg = await navigator.serviceWorker.ready
        const sub = (await reg.pushManager.getSubscription())
            || (await reg.pushManager.subscribe({
                userVisibleOnly: true,   // wajib; push senyap tidak diizinkan
                applicationServerKey: urlBase64ToUint8Array(d.public_key),
            }))

        await simpan(sub)
        push.pesan = 'Notifikasi HP aktif.'
        return true
    } catch (e) {
        push.pesan = 'Gagal mengaktifkan notifikasi. Coba lagi beberapa saat.'
        return false
    } finally {
        push.sibuk = false
    }
}

/** Matikan di perangkat ini (langganan dicabut di browser + server). */
export async function matikanPush() {
    push.sibuk = true; push.pesan = ''
    try {
        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.getSubscription()
        if (sub) {
            await api.delete('/push/langganan', { data: { endpoint: sub.endpoint } })
            await sub.unsubscribe()
        }
        push.aktif = false
        push.pesan = 'Notifikasi HP dimatikan untuk perangkat ini.'
    } catch (_) {
        push.pesan = 'Gagal mematikan notifikasi.'
    } finally {
        push.sibuk = false
    }
}

/** Kirim notifikasi percobaan ke perangkat sendiri. */
export async function ujiPush() {
    push.sibuk = true; push.pesan = ''
    try {
        push.pesan = (await api.post('/push/uji')).data.message
    } catch (_) {
        push.pesan = 'Gagal mengirim percobaan.'
    } finally {
        push.sibuk = false
    }
}
