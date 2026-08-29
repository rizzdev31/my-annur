import { reactive } from 'vue'
import api, { TOKEN_KEY } from '../api'

export const auth = reactive({
    santri: null,
    token: localStorage.getItem(TOKEN_KEY) || null,

    get isLoggedIn() { return !!this.token },

    setToken(token, santri) {
        localStorage.setItem(TOKEN_KEY, token)
        this.token = token
        this.santri = santri ?? null
    },

    async fetchMe() {
        try { this.santri = (await api.get('/auth/me')).data.data } catch (_) {/* diamkan */}
    },

    async logout() {
        try { await api.post('/auth/logout') } catch (_) {/* abaikan */}
        localStorage.removeItem(TOKEN_KEY)
        this.token = null
        this.santri = null
    },
})
