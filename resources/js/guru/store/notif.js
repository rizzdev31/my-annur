import { reactive } from 'vue'
import api from '../api'

// State notifikasi global: badge lonceng + daftar di halaman Notifikasi.
// Polling ringan tiap 60 dtk selama sesi login.
export const notif = reactive({
    unread: 0,
    items: [],
    loading: false,
    _timer: null,
})

export async function refreshUnread() {
    try {
        const { data } = await api.get('/notifikasi/unread-count')
        notif.unread = data?.data?.count ?? 0
    } catch (_) {/* diamkan */}
}

export async function loadNotif() {
    notif.loading = true
    try {
        const { data } = await api.get('/notifikasi')
        notif.items = data?.data ?? []
        notif.unread = data?.unread ?? notif.unread
    } catch (_) {/* diamkan */} finally {
        notif.loading = false
    }
}

export async function bacaSatu(id) {
    const n = notif.items.find((x) => x.id === id)
    if (n && !n.sudah_dibaca) {
        n.sudah_dibaca = true
        notif.unread = Math.max(0, notif.unread - 1)
        try { await api.patch(`/notifikasi/${id}/baca`) } catch (_) {/* diamkan */}
    }
}

export async function bacaSemua() {
    notif.items.forEach((x) => (x.sudah_dibaca = true))
    notif.unread = 0
    try { await api.post('/notifikasi/baca-semua') } catch (_) {/* diamkan */}
}

// Mulai polling badge (idempoten). Panggil saat app siap & sudah login.
export function startNotifPolling() {
    if (notif._timer) return
    refreshUnread()
    notif._timer = setInterval(refreshUnread, 60000)
}

export function stopNotifPolling() {
    if (notif._timer) { clearInterval(notif._timer); notif._timer = null }
    notif.unread = 0
    notif.items = []
}
