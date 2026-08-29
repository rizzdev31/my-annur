import axios from 'axios'

// Klien API guru — origin sama dengan Laravel, jadi tanpa CORS.
// Auth via token Sanctum (Bearer), sama seperti aplikasi Flutter.
export const TOKEN_KEY = 'guru_token'

const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
})

// Sisipkan Bearer token pada setiap request.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY)
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

// 401 → token kadaluarsa/tak valid → bersihkan & lempar ke login.
api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem(TOKEN_KEY)
            if (!location.pathname.startsWith('/guru/login')) {
                location.assign('/guru/login')
            }
        }
        return Promise.reject(err)
    },
)

export default api
