<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from './store/auth'
import { ui } from './store/ui'
import { notif, startNotifPolling } from './store/notif'
import PengumumanPopup from './components/PengumumanPopup.vue'
import InstallBanner from './components/InstallBanner.vue'
import { pwa, resetInstallBanner } from './store/pwa'

const route = useRoute()
const router = useRouter()

onMounted(startNotifPolling)

const tabs = [
    { name: 'beranda',  label: 'Beranda', icon: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10' },
    { name: 'absensi',  label: 'Absensi', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4' },
    { name: 'tugas',    label: 'Tugas',   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { name: 'profil',   label: 'Profil',  icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

const sections = [
    { title: 'AKADEMIK', items: [
        { name: 'absen-mengajar',  label: 'Absen Mengajar',  sub: 'Jurnal & jadwal kelas',       c: '#0C78FF', icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z' },
        { name: 'kelas-pengganti', label: 'Kelas Pengganti', sub: 'Sesi dilimpahkan ke Anda',    c: '#7C3AED', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
        { name: 'tahfidz',         label: 'Kelas Tahfidz',   sub: 'Absen & setoran hafalan',     c: '#059669', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253' },
        { name: 'tahsin',          label: 'Kelas Tahsin',    sub: 'Absen & penilaian level',     c: '#7C3AED', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
        { name: 'tasmi',           label: "Tasmi' Saya",     sub: 'Uji tasmi santri',            c: '#059669', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'ekstrakurikuler', label: 'Ekstrakurikuler', sub: 'Absensi & penilaian ekskul',  c: '#0C78FF', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
        { name: 'laporan',         label: 'Laporan',         sub: 'Pembelajaran & pencapaian',   c: '#0284C7', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    ]},
    { title: 'ADMINISTRASI', items: [
        { name: 'izin',        label: 'Pengajuan Izin',   sub: 'Izin · sakit · cuti',        c: '#F59E0B', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
        { name: 'lembur',      label: 'Lembur',           sub: 'Ajukan & bukti lembur',      c: '#6366F1', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'kinerja',     label: 'Kinerja Saya',     sub: 'Skor & rekap bulanan',       c: '#059669', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
        { name: 'punishment',  label: 'Evaluasi & Teguran', sub: 'Catatan punishment kinerja', c: '#EF4444', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        { name: 'slip-gaji',   label: 'Slip Gaji',        sub: 'Riwayat penggajian',         c: '#0284C7', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'riwayat',     label: 'Riwayat Absensi',  sub: 'Rekap kehadiran',            c: '#64748B', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'inventaris',  label: 'Inventaris',       sub: 'Peminjaman sarana',          c: '#0284C7', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    ]},
    { title: 'SMART HABBIT', items: [
        { name: 'smart-controlling', label: 'Smart Controlling', sub: 'Scan kehadiran santri (cadangan)', c: '#0EA5E9', icon: 'M4 7V5a1 1 0 011-1h2m0 16H5a1 1 0 01-1-1v-2m16 0v2a1 1 0 01-1 1h-2M17 4h2a1 1 0 011 1v2M8 12h8' },
        { name: 'smart-eksekusi',    label: 'Smart Eksekusi',    sub: 'Lapor santri ke Guru BK',       c: '#0C78FF', icon: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8' },
        { name: 'piket',             label: 'Guru Piket',        sub: 'Nilai & absen saat piket',      c: '#F59E0B', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    ]},
    { title: 'KESISWAAN', items: [
        { name: 'perizinan-santri', label: 'Perizinan Santri', sub: 'Setujui izin santri',   c: '#14B8A6', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        { name: 'smart-health',     label: 'Smart Health',     sub: 'Lapor & pantau kesehatan', c: '#EF4444', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
    ]},
]

const initials = computed(() => {
    const n = auth.user?.name || 'AN'
    return n.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
})

function go(name) { ui.drawer = false; router.push({ name }) }
function pasangApp() { ui.drawer = false; resetInstallBanner() }
async function keluar() {
    ui.drawer = false
    if (!confirm('Keluar dari aplikasi?')) return
    await auth.logout(); router.replace({ name: 'login' })
}
</script>

<template>
    <div class="min-h-dvh flex flex-col bg-[#F7F9FB]">
        <!-- Top bar -->
        <header class="safe-t sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-gray-100">
            <div class="h-14 px-3 flex items-center gap-2">
                <button @click="ui.drawer = true" class="w-10 h-10 rounded-xl grid place-items-center text-gray-600 active:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <img src="/logo.png" alt="An-Nur" class="w-8 h-8 object-contain" />
                <div class="leading-tight flex-1 min-w-0">
                    <p class="text-[13px] font-extrabold text-[#06346B] truncate">An-Nur Smart</p>
                    <p class="text-[10px] text-gray-400 -mt-0.5">Smart System Pesantren</p>
                </div>
                <button @click="router.push({ name: 'notifikasi' })"
                    class="relative w-10 h-10 rounded-xl grid place-items-center text-gray-500 active:bg-gray-100"
                    :class="route.name === 'notifikasi' ? 'text-[#0C78FF]' : ''" aria-label="Notifikasi">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span v-if="notif.unread > 0"
                        class="absolute top-1.5 right-1.5 min-w-[16px] h-[16px] px-1 rounded-full bg-red-500 text-white text-[9px] font-extrabold grid place-items-center leading-none">
                        {{ notif.unread > 9 ? '9+' : notif.unread }}
                    </span>
                </button>
                <div class="w-9 h-9 rounded-full bg-[#0C78FF]/10 grid place-items-center text-[#0C78FF] text-xs font-bold">{{ initials }}</div>
            </div>
        </header>

        <!-- Konten -->
        <main class="flex-1 overflow-y-auto px-4 pt-4 pb-28">
            <InstallBanner />
            <slot />
        </main>

        <!-- Bottom nav -->
        <nav class="safe-b fixed bottom-0 inset-x-0 z-30">
            <div class="mx-4 mb-3 h-16 bg-white rounded-3xl border border-gray-100 shadow-lg shadow-indigo-900/10 flex items-center px-2">
                <router-link v-for="t in tabs" :key="t.name" :to="{ name: t.name }"
                    class="flex-1 h-full flex flex-col items-center justify-center gap-1"
                    :class="route.name === t.name ? 'text-[#0C78FF]' : 'text-gray-400'">
                    <span class="grid place-items-center w-10 h-9 rounded-xl transition-colors" :class="route.name === t.name ? 'bg-[#0C78FF]/10' : ''">
                        <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="t.icon" /></svg>
                    </span>
                    <span class="text-[10px] font-semibold">{{ t.label }}</span>
                </router-link>
            </div>
        </nav>

        <!-- ══ DRAWER ══ -->
        <Transition name="fade">
            <div v-if="ui.drawer" class="fixed inset-0 z-[60]" @click="ui.drawer = false" style="background: rgba(15,23,42,0.5)"></div>
        </Transition>
        <Transition name="slide">
            <aside v-if="ui.drawer" class="fixed inset-y-0 left-0 z-[61] w-[84%] max-w-xs bg-[#F7F9FB] flex flex-col shadow-2xl">
                <!-- Header profil -->
                <div class="safe-t relative overflow-hidden bg-gradient-to-br from-[#06346B] via-[#1e3a8a] to-[#0C78FF] text-white px-5 pt-6 pb-6">
                    <img src="/logo.png" class="absolute -right-4 -top-3 w-24 h-24 opacity-10" />
                    <div class="flex items-center gap-2 mb-5">
                        <img src="/logo.png" class="w-6 h-6 object-contain" />
                        <p class="text-[11px] font-extrabold tracking-wide">PP AN-NUR SIDOARJO</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-2xl bg-white/15 border-2 border-white/40 grid place-items-center overflow-hidden">
                            <img v-if="auth.user?.foto" :src="auth.user.foto" class="w-full h-full object-cover" />
                            <span v-else class="text-lg font-extrabold">{{ initials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-extrabold text-[15px] truncate">{{ auth.user?.name || '—' }}</p>
                            <p class="text-white/70 text-[11px] truncate">{{ auth.user?.jabatan || auth.user?.email || 'Tenaga Pendidik' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Menu -->
                <div class="flex-1 overflow-y-auto py-3">
                    <template v-for="sec in sections" :key="sec.title">
                        <p class="px-5 pt-3 pb-1.5 text-[10px] font-extrabold text-gray-400 tracking-widest">{{ sec.title }}</p>
                        <button v-for="it in sec.items" :key="it.name" @click="go(it.name)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 active:bg-white transition-colors"
                            :class="route.name === it.name ? 'bg-white' : ''">
                            <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0" :style="{ background: it.c + '1A' }">
                                <svg class="w-[18px] h-[18px]" :style="{ color: it.c }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="it.icon"/></svg>
                            </span>
                            <span class="flex-1 min-w-0 text-left">
                                <span class="block text-[13.5px] font-semibold text-gray-800 leading-tight">{{ it.label }}</span>
                                <span class="block text-[10.5px] text-gray-400 truncate">{{ it.sub }}</span>
                            </span>
                            <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </template>
                </div>

                <!-- Footer -->
                <div class="safe-b border-t border-gray-100 p-3 bg-white">
                    <button v-if="!pwa.installed && (pwa.canInstall || pwa.isIOS)" @click="pasangApp"
                        class="w-full flex items-center gap-3 px-3 py-2.5 mb-2 rounded-xl bg-[#0C78FF]/10 active:scale-[0.99] transition">
                        <span class="w-8 h-8 rounded-lg bg-[#0C78FF]/15 grid place-items-center"><svg class="w-4 h-4 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg></span>
                        <span class="text-sm font-bold text-[#0C78FF]">Pasang Aplikasi ke HP</span>
                    </button>
                    <button @click="keluar" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-red-50 active:scale-[0.99] transition">
                        <span class="w-8 h-8 rounded-lg bg-red-100 grid place-items-center"><svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></span>
                        <span class="text-sm font-bold text-red-600">Keluar</span>
                    </button>
                    <p class="text-center text-[10px] text-gray-300 mt-2">An-Nur Smart · Web v1.0</p>
                </div>
            </aside>
        </Transition>

        <!-- Popup pengumuman -->
        <PengumumanPopup />
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform .28s cubic-bezier(.4,0,.2,1); }
.slide-enter-from, .slide-leave-to { transform: translateX(-100%); }
</style>
