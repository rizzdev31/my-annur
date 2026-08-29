import axios from 'axios'

export const TOKEN_KEY = 'santri_token'

const api = axios.create({
    baseURL: '/api/santri',
    headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY)
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem(TOKEN_KEY)
            if (!location.pathname.startsWith('/santri/login')) location.assign('/santri/login')
        }
        return Promise.reject(err)
    },
)

export default api
