import { createRouter, createWebHistory } from 'vue-router'
import { auth } from './store/auth'
import Login from './pages/Login.vue'
import Beranda from './pages/Beranda.vue'

const routes = [
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/', name: 'beranda', component: Beranda, meta: { title: 'Beranda' } },
    { path: '/absensi', name: 'absensi', component: () => import('./pages/Absensi.vue'), meta: { title: 'Absen Sekolah' } },
    { path: '/tahfidz', name: 'tahfidz', component: () => import('./pages/Tahfidz.vue'), meta: { title: 'Tahfidz' } },
    { path: '/tahsin', name: 'tahsin', component: () => import('./pages/Tahsin.vue'), meta: { title: 'Tahsin' } },
    { path: '/controlling', name: 'controlling', component: () => import('./pages/Controlling.vue'), meta: { title: 'Smart Controlling' } },
    { path: '/izin', name: 'izin', component: () => import('./pages/Izin.vue'), meta: { title: 'Izin' } },
    { path: '/kesehatan', name: 'kesehatan', component: () => import('./pages/Kesehatan.vue'), meta: { title: 'Kesehatan' } },
    { path: '/sertifikat/:jenis/:id', name: 'sertifikat', component: () => import('./pages/Sertifikat.vue'), meta: { title: 'Sertifikat', bare: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory('/santri'), routes, scrollBehavior: () => ({ top: 0 }) })

router.beforeEach((to) => {
    if (!to.meta.guest && !auth.isLoggedIn) return { name: 'login' }
    if (to.meta.guest && auth.isLoggedIn) return { name: 'beranda' }
    return true
})

export default router
