import { reactive } from 'vue'
import api, { TOKEN_KEY } from '../api'
import { stopNotifPolling } from './notif'

// Store auth ringan (reactive) — token disimpan di localStorage.
export const auth = reactive({
    user: null,
    token: localStorage.getItem(TOKEN_KEY) || null,
    loading: false,

    get isLoggedIn() {
        return !!this.token
    },

    async login(login, password) {
        this.loading = true
        try {
            // Backend menerima field 'login' (bisa username ATAU email).
            const { data } = await api.post('/auth/login', { login, password })
            const token = data.data?.token ?? data.token
            if (!token) throw new Error('Token tidak diterima dari server.')
            localStorage.setItem(TOKEN_KEY, token)
            this.token = token
            this.user = data.data?.user ?? data.user ?? null
            return { ok: true }
        } catch (e) {
            const msg = e.response?.data?.message || 'Gagal masuk. Periksa akun & sandi.'
            return { ok: false, message: msg }
        } finally {
            this.loading = false
        }
    },

    async fetchMe() {
        try {
            const { data } = await api.get('/auth/me')
            this.user = data.data ?? data
        } catch (_) {/* diamkan */}
    },

    async logout() {
        try { await api.post('/auth/logout') } catch (_) {/* abaikan */}
        stopNotifPolling()
        localStorage.removeItem(TOKEN_KEY)
        this.token = null
        this.user = null
    },
})
