<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { auth } from './store/auth'

const route = useRoute()
const router = useRouter()
const drawer = ref(false)
const title = computed(() => route.meta.title || 'An-Nur Santri')
const initials = (n) => (n || '?').split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()

const menu = [
    { name: 'beranda', label: 'Beranda', c: 'bg-blue-50 text-blue-600', d: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10' },
    { name: 'absensi', label: 'Absen Sekolah', c: 'bg-blue-50 text-blue-600', d: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 14l2 2 4-4' },
    { name: 'tahfidz', label: 'Tahfidz', c: 'bg-emerald-50 text-emerald-600', d: 'M12 6.25C10.5 5 8 4.5 5.5 4.75V18c2.5-.25 5 .25 6.5 1.5 1.5-1.25 4-1.75 6.5-1.5V4.75C16 4.5 13.5 5 12 6.25zm0 0V19' },
    { name: 'tahsin', label: 'Tahsin', c: 'bg-violet-50 text-violet-600', d: 'M12 14l9-5-9-5-9 5 9 5zm0 0v7' },
    { name: 'controlling', label: 'Smart Controlling', c: 'bg-amber-50 text-amber-600', d: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'izin', label: 'Izin', c: 'bg-sky-50 text-sky-600', d: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'kesehatan', label: 'Kesehatan', c: 'bg-red-50 text-red-600', d: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
]
const bottomNav = ['beranda', 'absensi', 'tahfidz', 'kesehatan']

function go(name) { drawer.value = false; if (route.name !== name) router.push({ name }) }
async function keluar() { drawer.value = false; await auth.logout(); router.replace({ name: 'login' }) }
</script>

<template>
    <div class="min-h-dvh pb-28">
        <!-- Topbar -->
        <div class="safe-t sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-gray-100 px-3 h-14 flex items-center gap-2">
            <button @click="drawer = true" class="w-9 h-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600 active:scale-95 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <h1 class="text-base font-extrabold text-[#06346B] flex-1">{{ title }}</h1>
        </div>

        <main class="px-4 py-4 max-w-md mx-auto"><slot /></main>

        <!-- Floating bottom nav (disamakan dengan app guru) -->
        <nav class="safe-b fixed bottom-0 inset-x-0 z-30">
            <div class="mx-4 mb-3 h-16 bg-white rounded-3xl border border-gray-100 shadow-lg shadow-indigo-900/10 flex items-center px-2 max-w-md sm:mx-auto">
                <RouterLink v-for="n in bottomNav" :key="n" :to="{ name: n }"
                    class="flex-1 h-full flex flex-col items-center justify-center gap-1"
                    :class="route.name === n ? 'text-[#0C78FF]' : 'text-gray-400'">
                    <span class="grid place-items-center w-10 h-9 rounded-xl transition-colors" :class="route.name === n ? 'bg-[#0C78FF]/10' : ''">
                        <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="menu.find(m => m.name === n).d" /></svg>
                    </span>
                    <span class="text-[10px] font-semibold">{{ menu.find(m => m.name === n).label.split(' ')[0] }}</span>
                </RouterLink>
            </div>
        </nav>

        <!-- Drawer / Sidebar -->
        <Transition name="drawer">
            <div v-if="drawer" class="fixed inset-0 z-[80]">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="drawer = false"></div>
                <aside class="drawer-panel absolute inset-y-0 left-0 w-[80%] max-w-xs bg-white flex flex-col safe-t">
                    <!-- Header santri -->
                    <div class="bg-gradient-to-br from-[#0C78FF] to-[#06346B] text-white p-5 pt-6">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-white/15 ring-2 ring-white/30 overflow-hidden grid place-items-center text-sm font-extrabold shrink-0">
                                <img v-if="auth.santri?.foto" :src="auth.santri.foto" class="w-full h-full object-cover" />
                                <span v-else>{{ initials(auth.santri?.nama) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-extrabold truncate">{{ auth.santri?.nama || 'Santri' }}</p>
                                <p class="text-white/70 text-[11px]">NIS {{ auth.santri?.nis || '—' }} · {{ auth.santri?.kelas || '' }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Menu -->
                    <div class="flex-1 overflow-y-auto p-3">
                        <p class="text-[10px] font-bold text-gray-400 px-2 mb-1">MONITORING</p>
                        <button v-for="m in menu" :key="m.name" @click="go(m.name)"
                            class="w-full flex items-center gap-3 px-2 py-2.5 rounded-xl active:bg-gray-50 transition" :class="route.name === m.name ? 'bg-blue-50' : ''">
                            <div class="w-9 h-9 rounded-xl grid place-items-center" :class="m.c">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="m.d" /></svg>
                            </div>
                            <span class="text-sm font-bold" :class="route.name === m.name ? 'text-[#0C78FF]' : 'text-gray-700'">{{ m.label }}</span>
                        </button>
                    </div>
                    <!-- Footer -->
                    <div class="p-3 border-t border-gray-100">
                        <button @click="keluar" class="w-full flex items-center gap-2 px-2 py-2.5 rounded-xl text-red-600 font-bold text-sm active:bg-red-50">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            Keluar
                        </button>
                        <p class="text-[10px] text-gray-300 text-center mt-2">An-Nur Smart · Portal Santri</p>
                    </div>
                </aside>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.drawer-enter-active .drawer-panel, .drawer-leave-active .drawer-panel { transition: transform .3s cubic-bezier(.32,.72,0,1); }
.drawer-enter-from .drawer-panel, .drawer-leave-to .drawer-panel { transform: translateX(-100%); }
.drawer-enter-active > div:first-child, .drawer-leave-active > div:first-child { transition: opacity .3s ease; }
.drawer-enter-from > div:first-child, .drawer-leave-to > div:first-child { opacity: 0; }
</style>
