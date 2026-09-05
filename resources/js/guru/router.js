import { createRouter, createWebHistory } from 'vue-router'
import { auth } from './store/auth'

import Login from './pages/Login.vue'
import Beranda from './pages/Beranda.vue'
import Absensi from './pages/Absensi.vue'
import Tugas from './pages/Tugas.vue'
import Profil from './pages/Profil.vue'
import Izin from './pages/Izin.vue'
import SlipGaji from './pages/SlipGaji.vue'
import SlipDetail from './pages/SlipDetail.vue'
import Riwayat from './pages/Riwayat.vue'
import AbsenMengajar from './pages/AbsenMengajar.vue'
import Kinerja from './pages/Kinerja.vue'
import Lembur from './pages/Lembur.vue'
import Inventaris from './pages/Inventaris.vue'
import SmartHealth from './pages/SmartHealth.vue'
import KelasPengganti from './pages/KelasPengganti.vue'
import PerizinanSantri from './pages/PerizinanSantri.vue'
import Tahfidz from './pages/Tahfidz.vue'
import TahfidzRoster from './pages/TahfidzRoster.vue'
import Tahsin from './pages/Tahsin.vue'
import TahsinRoster from './pages/TahsinRoster.vue'
import TasmiSaya from './pages/TasmiSaya.vue'
import LaporanPembelajaran from './pages/LaporanPembelajaran.vue'
import LaporanHub from './pages/LaporanHub.vue'
import LaporanPencapaian from './pages/LaporanPencapaian.vue'
import Piket from './pages/Piket.vue'
import SmartControlling from './pages/SmartControlling.vue'
import SmartEksekusi from './pages/SmartEksekusi.vue'
import Punishment from './pages/Punishment.vue'

const routes = [
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/', name: 'beranda', component: Beranda, meta: { title: 'Beranda' } },
    { path: '/absensi', name: 'absensi', component: Absensi, meta: { title: 'Absensi' } },
    { path: '/tugas', name: 'tugas', component: Tugas, meta: { title: 'Tugas' } },
    { path: '/tugas/tambahan/:id', name: 'tugas-detail', component: () => import('./pages/TugasDetail.vue'), meta: { title: 'Detail Tugas' } },
    { path: '/tugas/kegiatan/:id', name: 'kegiatan', component: () => import('./pages/Kegiatan.vue'), meta: { title: 'Detail Kegiatan' } },
    { path: '/notifikasi', name: 'notifikasi', component: () => import('./pages/Notifikasi.vue'), meta: { title: 'Notifikasi' } },
    { path: '/profil', name: 'profil', component: Profil, meta: { title: 'Profil' } },
    { path: '/profil/edit', name: 'profil-edit', component: () => import('./pages/EditProfil.vue'), meta: { title: 'Edit Profil' } },
    { path: '/izin', name: 'izin', component: Izin, meta: { title: 'Pengajuan Izin' } },
    { path: '/slip-gaji', name: 'slip-gaji', component: SlipGaji, meta: { title: 'Slip Gaji' } },
    { path: '/slip-gaji/:id', name: 'slip-detail', component: SlipDetail, meta: { title: 'Slip Gaji' } },
    { path: '/riwayat', name: 'riwayat', component: Riwayat, meta: { title: 'Riwayat Absensi' } },
    { path: '/absen-mengajar', name: 'absen-mengajar', component: AbsenMengajar, meta: { title: 'Absen Mengajar' } },
    { path: '/absen-santri/:jadwalId', name: 'absen-santri', component: () => import('./pages/AbsenSantri.vue'), meta: { title: 'Absen Santri' } },
    { path: '/kinerja', name: 'kinerja', component: Kinerja, meta: { title: 'Kinerja Saya' } },
    { path: '/lembur', name: 'lembur', component: Lembur, meta: { title: 'Lembur' } },
    { path: '/inventaris', name: 'inventaris', component: Inventaris, meta: { title: 'Inventaris' } },
    { path: '/smart-health', name: 'smart-health', component: SmartHealth, meta: { title: 'Smart Health' } },
    { path: '/kelas-pengganti', name: 'kelas-pengganti', component: KelasPengganti, meta: { title: 'Kelas Pengganti' } },
    { path: '/perizinan-santri', name: 'perizinan-santri', component: PerizinanSantri, meta: { title: 'Perizinan Santri' } },
    { path: '/tahfidz', name: 'tahfidz', component: Tahfidz, meta: { title: 'Kelas Tahfidz' } },
    { path: '/tahfidz/:jadwalId', name: 'tahfidz-roster', component: TahfidzRoster, meta: { title: 'Roster Tahfidz' } },
    { path: '/tahsin', name: 'tahsin', component: Tahsin, meta: { title: 'Kelas Tahsin' } },
    { path: '/tahsin/:jadwalId', name: 'tahsin-roster', component: TahsinRoster, meta: { title: 'Roster Tahsin' } },
    { path: '/tasmi', name: 'tasmi', component: TasmiSaya, meta: { title: "Tasmi' Saya" } },
    { path: '/laporan', name: 'laporan', component: LaporanHub, meta: { title: 'Laporan' } },
    { path: '/laporan-pembelajaran', name: 'laporan-pembelajaran', component: LaporanPembelajaran, meta: { title: 'Laporan Pembelajaran' } },
    { path: '/laporan-pencapaian/:jenis', name: 'laporan-pencapaian', component: LaporanPencapaian, meta: { title: 'Laporan Pencapaian' } },
    { path: '/sertifikat-tasmi/:id', name: 'sertifikat-tasmi', component: () => import('./pages/SertifikatTasmi.vue'), meta: { title: 'Sertifikat Tasmi', bare: true } },
    { path: '/sertifikat-tasnif/:id', name: 'sertifikat-tasnif', component: () => import('./pages/SertifikatTasnif.vue'), meta: { title: 'Sertifikat Tasnif', bare: true } },
    { path: '/monitoring', name: 'monitoring', component: () => import('./pages/Monitoring.vue'), meta: { title: 'Monitoring' } },
    { path: '/masukan', name: 'masukan', component: () => import('./pages/Masukan.vue'), meta: { title: 'Saran & Masukan' } },
    { path: '/masukan/:id', name: 'masukan-detail', component: () => import('./pages/MasukanDetail.vue'), meta: { title: 'Masukan' } },
    { path: '/piket', name: 'piket', component: Piket, meta: { title: 'Guru Piket' } },
    { path: '/piket/kegiatan', name: 'piket-kegiatan', component: () => import('./pages/PiketKegiatan.vue'), meta: { title: 'Absen Kegiatan' } },
    { path: '/smart-controlling', name: 'smart-controlling', component: SmartControlling, meta: { title: 'Smart Controlling' } },
    { path: '/smart-eksekusi', name: 'smart-eksekusi', component: SmartEksekusi, meta: { title: 'Smart Eksekusi' } },
    { path: '/punishment', name: 'punishment', component: Punishment, meta: { title: 'Evaluasi & Teguran' } },
    { path: '/ekstrakurikuler', name: 'ekstrakurikuler', component: () => import('./pages/Ekstrakurikuler.vue'), meta: { title: 'Ekstrakurikuler' } },
    { path: '/ekstrakurikuler/:id', name: 'ekstra-detail', component: () => import('./pages/EkstrakurikulerDetail.vue'), meta: { title: 'Detail Ekskul' } },
    { path: '/ekstrakurikuler/pertemuan/:id', name: 'ekstra-pertemuan', component: () => import('./pages/EkstraPertemuan.vue'), meta: { title: 'Absensi Ekskul' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

// base '/guru' → path '/' = /guru, '/login' = /guru/login, dst.
const router = createRouter({
    history: createWebHistory('/guru'),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

// Guard: rute non-guest wajib login; halaman login tak boleh diakses saat sudah login.
router.beforeEach((to) => {
    if (!to.meta.guest && !auth.isLoggedIn) return { name: 'login' }
    if (to.meta.guest && auth.isLoggedIn) return { name: 'beranda' }
    return true
})

export default router
