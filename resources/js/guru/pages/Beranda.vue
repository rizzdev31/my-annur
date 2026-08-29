<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api'
import { tanggalLokal } from '../tanggal'
import { auth } from '../store/auth'
import { ui } from '../store/ui'

const data = ref(null)
const loading = ref(true)
const error = ref('')

// Akses cepat — fitur tersering. Selebihnya di drawer "Semua Menu".
const quick = [
    { name: 'absen-mengajar', label: 'Mengajar', c: '#0C78FF', icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z' },
    { name: 'tahfidz', label: 'Tahfidz', c: '#059669', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253' },
    { name: 'tahsin', label: 'Tahsin', c: '#7C3AED', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { name: 'izin', label: 'Izin', c: '#F59E0B', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { name: 'slip-gaji', label: 'Slip Gaji', c: '#0284C7', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'kinerja', label: 'Kinerja', c: '#059669', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { name: 'laporan', label: 'Laporan', c: '#0EA5E9', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
]

const jam = new Date().getHours()
const salam = computed(() =>
    jam < 11 ? 'Selamat pagi' : jam < 15 ? 'Selamat siang' : jam < 18 ? 'Selamat sore' : 'Selamat malam')
const initials = computed(() => {
    const n = auth.user?.name || 'AN'
    return n.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
})

// Status absensi → warna & label pill.
const absen = computed(() => data.value?.absen_harian ?? {})
const absenStatus = computed(() => {
    const a = absen.value
    if (a.is_libur && !a.sudah_masuk) return { label: 'Libur', dot: '#94A3B8' }
    if (a.sudah_pulang) return { label: 'Selesai', dot: '#34D399' }
    if (a.sudah_masuk) return { label: 'Sudah Masuk', dot: '#FBBF24' }
    if (a.boleh_checkin) return { label: 'Waktunya Absen', dot: '#F87171' }
    return { label: 'Belum Absen', dot: '#F87171' }
})

// Reminder aktif untuk guru (call-to-action / hitung mundur / info).
const absenReminder = computed(() => {
    const a = absen.value
    if (a.is_libur && !a.sudah_masuk) return { text: 'Hari ini libur — tidak perlu absen.', tone: 'info' }
    if (a.izin_aktif?.ada && !a.sudah_masuk) return { text: `Sedang ${a.izin_aktif.jenis} — absen tidak diperlukan.`, tone: 'info' }
    if (a.sudah_pulang) return { text: 'Absensi hari ini selesai. Terima kasih!', tone: 'done' }
    if (a.sudah_masuk) return { text: `Sudah check-in ${a.jam_masuk || ''}. Jangan lupa check-out nanti.`, tone: 'warn' }
    if (a.boleh_checkin) return { text: 'Waktunya check-in sekarang. Ketuk untuk absen 👉', tone: 'action' }
    if (a.menit_menunggu_checkin > 0) {
        const m = a.menit_menunggu_checkin
        const sisa = m >= 60 ? `${Math.floor(m / 60)} jam ${m % 60} mnt` : `${m} menit`
        return { text: `Check-in buka pukul ${a.bisa_checkin_mulai} — ${sisa} lagi.`, tone: 'info' }
    }
    return null
})
const reminderTone = {
    action: 'bg-amber-300/25 text-amber-50 border-amber-200/30',
    warn:   'bg-white/10 text-white/90 border-white/15',
    info:   'bg-white/10 text-white/80 border-white/15',
    done:   'bg-emerald-300/20 text-emerald-50 border-emerald-200/30',
}

const tugasPersen = computed(() => Math.max(0, Math.min(100, Number(data.value?.tugas?.persen ?? 0))))

// Monitoring (dari dashboard): kelas pengganti, guru piket.
const penggantiCount = computed(() => Number(data.value?.perlu_perhatian?.pengganti ?? 0))
const isPiket = computed(() => !!data.value?.is_piket)

// Berita pesantren (proxy CMS via /berita).
const berita = ref([])
const beritaSumber = ref('https://ppmannursidoarjo.com')
async function loadBerita() {
    try {
        const { data: res } = await api.get('/berita')
        berita.value = res?.data ?? []
        if (res?.sumber) beritaSumber.value = res.sumber
    } catch (_) { berita.value = [] }
}
onMounted(loadBerita)

// ── Carousel banner (foto informasi lembaga) ──────────────────────────────────
// Kandidat di /public/img. Hanya yang benar-benar ada yang tampil → aman bila
// card3 belum ditambahkan. Cukup taruh card3.png di public/img agar muncul.
const bannerCandidates = ['/img/card-2.png', '/img/card-1.png', '/img/card-3.png']
const banners = ref([])
const slide = ref(0)
let slideTimer = null
const reduceMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

function goSlide(i) {
    const n = banners.value.length
    if (n) slide.value = ((i % n) + n) % n
}
function startAutoplay() {
    if (reduceMotion || slideTimer) return
    slideTimer = setInterval(() => { if (banners.value.length > 1) goSlide(slide.value + 1) }, 4500)
}
function stopAutoplay() { if (slideTimer) { clearInterval(slideTimer); slideTimer = null } }

// Swipe sederhana.
let touchX = 0
function onTouchStart(e) { touchX = e.changedTouches[0].clientX; stopAutoplay() }
function onTouchEnd(e) {
    const dx = e.changedTouches[0].clientX - touchX
    if (Math.abs(dx) > 40) goSlide(slide.value + (dx < 0 ? 1 : -1))
    startAutoplay()
}

// Cek keberadaan tiap gambar sebelum dirender (jaga urutan).
function loadBanners() {
    const found = new Array(bannerCandidates.length).fill(null)
    let done = 0
    const finalize = () => {
        if (++done === bannerCandidates.length) {
            banners.value = found.filter(Boolean)
            startAutoplay()
        }
    }
    bannerCandidates.forEach((src, i) => {
        const img = new Image()
        img.onload = () => { found[i] = src; finalize() }
        img.onerror = finalize
        img.src = src
    })
}

onMounted(loadBanners)
onBeforeUnmount(stopAutoplay)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const res = await api.get('/dashboard/ringkasan', { params: { device_date: tanggalLokal() } })
        data.value = res.data.data ?? res.data
        if (!auth.user) auth.fetchMe()
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data.'
    } finally {
        loading.value = false
    }
}
onMounted(load)
</script>

<template>
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4">
        <div class="h-40 rounded-3xl bg-gray-100 animate-pulse"></div>
        <div class="grid grid-cols-3 gap-3">
            <div v-for="i in 3" :key="i" class="h-24 rounded-2xl bg-gray-100 animate-pulse"></div>
        </div>
        <div class="h-40 rounded-2xl bg-gray-100 animate-pulse"></div>
    </div>

    <div v-else-if="error" class="pt-16 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-red-50 grid place-items-center mb-3">
            <svg class="w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 3l9 16H3L12 3z" />
            </svg>
        </div>
        <p class="text-sm text-gray-500">{{ error }}</p>
        <button @click="load"
            class="mt-3 px-5 py-2.5 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold active:scale-95 transition">Coba
            lagi</button>
    </div>

    <div v-else class="space-y-4 anim-in">
        <!-- ══ HERO: salam + absensi jadi satu ══ -->
        <section
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#06346B] via-[#0A4C9E] to-[#0C78FF] text-white p-5 shadow-lg shadow-blue-900/20">
            <img src="/logo.png" class="absolute -right-6 -top-6 w-32 h-32 opacity-10 pointer-events-none" alt="" />
            <div class="absolute -right-10 bottom-0 w-40 h-40 rounded-full bg-white/5 pointer-events-none"></div>

            <div class="relative flex items-start gap-3">
                <div
                    class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 grid place-items-center overflow-hidden shrink-0">
                    <img v-if="auth.user?.foto" :src="auth.user.foto" class="w-full h-full object-cover" alt="" />
                    <span v-else class="text-sm font-extrabold">{{ initials }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[12px] text-white/70">{{ salam }},</p>
                    <h1 class="text-lg font-extrabold leading-tight truncate">{{ auth.user?.name || 'Ustadz/Ustadzah' }}
                    </h1>
                </div>
                <span class="text-[11px] text-white/70 bg-white/10 rounded-full px-2.5 py-1 shrink-0">{{ data.hari_label
                    }}</span>
            </div>

            <!-- Absensi strip -->
            <RouterLink :to="{ name: 'absensi' }"
                class="relative mt-4 flex items-center gap-3 rounded-2xl bg-white/10 backdrop-blur border border-white/15 p-3.5 active:scale-[0.98] transition">
                <span class="w-10 h-10 rounded-xl bg-white/15 grid place-items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div class="flex items-center gap-5">
                    <div>
                        <p class="text-[10px] text-white/60 leading-none">Masuk</p>
                        <p class="text-base font-bold tabular-nums leading-tight mt-0.5">{{ absen.jam_masuk || '--:--'
                            }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-white/60 leading-none">Pulang</p>
                        <p class="text-base font-bold tabular-nums leading-tight mt-0.5">{{ absen.jam_pulang || '--:--'
                            }}</p>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2 shrink-0">
                    <span
                        class="flex items-center gap-1.5 text-[11px] font-semibold bg-white/15 rounded-full px-2.5 py-1">
                        <span class="w-1.5 h-1.5 rounded-full" :style="{ background: absenStatus.dot }"></span>
                        {{ absenStatus.label }}
                    </span>
                    <svg class="w-4 h-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </RouterLink>

            <!-- Reminder absensi (terhubung jam kerja) -->
            <RouterLink v-if="absenReminder" :to="{ name: 'absensi' }"
                class="mt-2 flex items-center gap-2 rounded-xl border px-3 py-2 text-[12px] font-medium leading-snug active:scale-[0.98] transition"
                :class="reminderTone[absenReminder.tone]">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span>{{ absenReminder.text }}</span>
            </RouterLink>
        </section>

        <!-- ══ CAROUSEL BANNER (foto informasi lembaga) ══ -->
        <section v-if="banners.length" class="relative">
            <div
                class="rounded-3xl overflow-hidden border border-gray-100 bg-gradient-to-br from-slate-50 to-blue-50 shadow-sm">
                <div class="flex transition-transform duration-500 ease-out"
                    :style="{ transform: `translateX(-${slide * 100}%)` }" @touchstart.passive="onTouchStart"
                    @touchend.passive="onTouchEnd">
                    <div v-for="b in banners" :key="b" class="w-full shrink-0 aspect-[1.75/1] grid place-items-center">
                        <img :src="b" alt="Informasi PP An-Nur" class="w-full h-full object-contain" />
                    </div>
                </div>
            </div>
            <!-- Dots -->
            <div v-if="banners.length > 1" class="flex justify-center gap-1.5 mt-2.5">
                <button v-for="(b, i) in banners" :key="b" @click="goSlide(i)" :aria-label="`Slide ${i + 1}`"
                    class="h-1.5 rounded-full transition-all duration-300"
                    :class="i === slide ? 'w-5 bg-[#0C78FF]' : 'w-1.5 bg-gray-300'"></button>
            </div>
        </section>

        <!-- ══ STAT TILES ══ -->
        <section class="grid grid-cols-3 gap-3">
            <!-- JP hari ini -->
            <div class="rounded-2xl bg-white border border-gray-100 p-3.5">
                <span class="w-9 h-9 rounded-xl bg-[#0C78FF]/10 grid place-items-center mb-2">
                    <svg class="w-[18px] h-[18px] text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z" />
                    </svg>
                </span>
                <p class="text-2xl font-extrabold text-gray-900 leading-none tabular-nums">{{ data.mengajar?.jp_hari_ini
                    ?? 0 }}</p>
                <p class="text-[11px] text-gray-400 mt-1">JP Hari Ini</p>
            </div>
            <!-- Tugas selesai + ring -->
            <div class="rounded-2xl bg-white border border-gray-100 p-3.5">
                <div class="flex items-center justify-between mb-2">
                    <span class="w-9 h-9 rounded-xl bg-emerald-50 grid place-items-center">
                        <svg class="w-[18px] h-[18px] text-emerald-600" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.9">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div class="relative w-9 h-9">
                        <svg class="w-9 h-9 -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#ECFDF5" stroke-width="4" />
                            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#10B981" stroke-width="4"
                                stroke-linecap="round" :stroke-dasharray="`${tugasPersen} 100`" />
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-extrabold text-gray-900 leading-none tabular-nums">{{ tugasPersen }}<span
                        class="text-sm text-gray-400">%</span></p>
                <p class="text-[11px] text-gray-400 mt-1">Tugas Selesai</p>
            </div>
            <!-- Izin pending -->
            <div class="rounded-2xl bg-white border border-gray-100 p-3.5">
                <span class="w-9 h-9 rounded-xl bg-amber-50 grid place-items-center mb-2">
                    <svg class="w-[18px] h-[18px] text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <p class="text-2xl font-extrabold text-gray-900 leading-none tabular-nums">{{ data.izin_pending ?? 0 }}
                </p>
                <p class="text-[11px] text-gray-400 mt-1">Izin Pending</p>
            </div>
        </section>

        <!-- ══ AKSES CEPAT ══ -->
        <section class="rounded-2xl bg-white border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3.5">
                <h2 class="text-sm font-bold text-gray-800">Akses Cepat</h2>
                <button @click="ui.drawer = true"
                    class="flex items-center gap-0.5 text-[11px] font-bold text-[#0C78FF] active:opacity-70">
                    Semua Menu
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-4 gap-y-4 gap-x-1">
                <RouterLink v-for="m in quick" :key="m.name" :to="{ name: m.name }"
                    class="flex flex-col items-center gap-1.5 active:scale-95 transition">
                    <span class="rounded-2xl grid place-items-center"
                        :style="{ background: m.c + '14', width: '3.25rem', height: '3.25rem' }">
                        <svg class="w-6 h-6" :style="{ color: m.c }" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="m.icon" />
                        </svg>
                    </span>
                    <span class="text-[10.5px] font-medium text-gray-600 text-center leading-tight">{{ m.label }}</span>
                </RouterLink>
                <button @click="ui.drawer = true" class="flex flex-col items-center gap-1.5 active:scale-95 transition">
                    <span class="grid place-items-center rounded-2xl bg-gray-100" style="width:3.25rem;height:3.25rem">
                        <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none" />
                            <circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none" />
                            <circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none" />
                        </svg>
                    </span>
                    <span class="text-[10.5px] font-medium text-gray-600 text-center leading-tight">Lainnya</span>
                </button>
            </div>
        </section>

        <!-- ══ BERITA PESANTREN ══ -->
        <section class="rounded-2xl bg-white border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-800">Berita Pesantren</h2>
                <a :href="beritaSumber" target="_blank" rel="noopener"
                    class="flex items-center gap-0.5 text-[11px] font-bold text-[#0C78FF] active:opacity-70">
                    Lihat semua
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>

            <!-- Daftar berita (scroll horizontal) -->
            <div v-if="berita.length" class="flex gap-3 overflow-x-auto scrollbar-hide -mx-4 px-4 snap-x">
                <a v-for="(b, i) in berita" :key="i" :href="b.link" target="_blank" rel="noopener"
                    class="snap-start shrink-0 w-52 rounded-2xl border border-gray-100 overflow-hidden active:scale-[0.98] transition">
                    <div class="h-28 bg-gray-100">
                        <img v-if="b.gambar" :src="b.gambar" :alt="b.judul" class="w-full h-full object-cover" loading="lazy" />
                        <div v-else class="w-full h-full grid place-items-center bg-gradient-to-br from-[#06346B] to-[#0C78FF]">
                            <svg class="w-8 h-8 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m0 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                        </div>
                    </div>
                    <div class="p-3">
                        <p class="text-[13px] font-bold text-gray-800 leading-snug line-clamp-2">{{ b.judul }}</p>
                        <p v-if="b.ringkasan" class="text-[11px] text-gray-400 line-clamp-2 mt-1 leading-snug">{{ b.ringkasan }}</p>
                        <p v-if="b.tanggal" class="text-[10px] text-gray-400 mt-1.5">{{ b.tanggal }}</p>
                    </div>
                </a>
            </div>

            <!-- Fallback: berita belum termuat → tautan ke website -->
            <a v-else :href="beritaSumber" target="_blank" rel="noopener"
                class="relative flex items-center gap-3 rounded-2xl overflow-hidden bg-gradient-to-br from-[#06346B] to-[#0C78FF] text-white p-4 active:scale-[0.99] transition">
                <span class="w-10 h-10 rounded-xl bg-white/15 grid place-items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m0 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold leading-tight">Berita & Info Pesantren</p>
                    <p class="text-[11px] text-white/70 mt-0.5 truncate">Kunjungi website resmi PP An-Nur Sidoarjo</p>
                </div>
                <svg class="w-4 h-4 text-white/70 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
            </a>
        </section>

        <!-- ══ JADWAL MENGAJAR (timeline) ══ -->
        <section class="rounded-2xl bg-white border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-800">Jadwal Mengajar</h2>
                <span class="text-[11px] font-semibold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">{{
                    data.mengajar?.sudah_absen ?? 0 }}/{{ data.mengajar?.total ?? 0 }} absen</span>
            </div>

            <div v-if="!data.mengajar?.daftar?.length" class="py-8 text-center">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-50 grid place-items-center mb-2">
                    <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-500">Tidak ada jadwal hari ini</p>
                <p class="text-[11px] text-gray-400 mt-0.5">Nikmati harimu, Ustadz/Ustadzah.</p>
            </div>

            <ul v-else class="relative space-y-1">
                <li v-for="(j, i) in data.mengajar.daftar" :key="i"
                    class="relative flex items-center gap-3 pl-4 py-2 rounded-xl transition"
                    :class="j.is_next ? 'bg-[#0C78FF]/[0.06]' : ''">
                    <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 rounded-full"
                        :style="{ background: j.is_next ? '#0C78FF' : (j.sudah_absen ? '#10B981' : '#E2E8F0') }"></span>
                    <div class="w-12 text-center shrink-0">
                        <p class="text-[12px] font-bold text-gray-800 tabular-nums leading-none">{{ j.jam_mulai }}</p>
                        <p class="text-[10px] text-gray-400 tabular-nums mt-0.5">{{ j.jam_selesai }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ j.mapel }}</p>
                            <span v-if="j.is_next"
                                class="text-[9px] font-extrabold text-[#0C78FF] bg-[#0C78FF]/10 px-1.5 py-px rounded uppercase tracking-wide shrink-0">Berikutnya</span>
                        </div>
                        <p class="text-[11px] text-gray-400 truncate">{{ j.kelas }}</p>
                    </div>
                    <span v-if="j.sudah_absen"
                        class="flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 pl-1.5 pr-2 py-0.5 rounded-full shrink-0">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Absen
                    </span>
                    <span v-else
                        class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full shrink-0">Belum</span>
                </li>
            </ul>
        </section>

        <!-- ══ BANNER card-4 ══ -->
        <div class="rounded-3xl overflow-hidden border border-gray-100 bg-gradient-to-br from-slate-50 to-blue-50">
            <img src="/img/card-4.png" alt="Info PP An-Nur" class="w-full h-auto object-contain" loading="lazy" />
        </div>

        <!-- ══ MONITORING ══ -->
        <section class="rounded-2xl bg-white border border-gray-100 p-4">
            <h2 class="text-sm font-bold text-gray-800 mb-3">Monitoring</h2>
            <div class="space-y-2">
                <!-- Kelas Pengganti -->
                <RouterLink :to="{ name: 'kelas-pengganti' }"
                    class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 active:scale-[0.99] transition"
                    :class="penggantiCount > 0 ? 'bg-amber-50/60 border-amber-100' : ''">
                    <span class="w-9 h-9 rounded-xl bg-amber-50 grid place-items-center shrink-0">
                        <svg class="w-[18px] h-[18px] text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-gray-800 leading-tight">Kelas Pengganti</p>
                        <p class="text-[11px] text-gray-400">Sesi yang dilimpahkan ke Anda</p>
                    </div>
                    <span v-if="penggantiCount > 0" class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full shrink-0">{{ penggantiCount }} sesi</span>
                    <span v-else class="text-[10px] font-semibold text-gray-400 shrink-0">Tidak ada</span>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
                </RouterLink>

                <!-- Guru Piket -->
                <RouterLink :to="{ name: 'piket' }"
                    class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 active:scale-[0.99] transition"
                    :class="isPiket ? 'bg-[#0C78FF]/[0.05] border-[#0C78FF]/15' : ''">
                    <span class="w-9 h-9 rounded-xl bg-[#0C78FF]/10 grid place-items-center shrink-0">
                        <svg class="w-[18px] h-[18px] text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-gray-800 leading-tight">Guru Piket</p>
                        <p class="text-[11px] text-gray-400">Nilai & absen saat piket</p>
                    </div>
                    <span v-if="isPiket" class="flex items-center gap-1 text-[10px] font-bold text-[#0C78FF] bg-[#0C78FF]/10 px-2 py-0.5 rounded-full shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#0C78FF]"></span> Hari ini
                    </span>
                    <span v-else class="text-[10px] font-semibold text-gray-400 shrink-0">Tidak piket</span>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
                </RouterLink>

                <!-- Log Aktivitas -->
                <RouterLink :to="{ name: 'riwayat' }"
                    class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 active:scale-[0.99] transition">
                    <span class="w-9 h-9 rounded-xl bg-slate-100 grid place-items-center shrink-0">
                        <svg class="w-[18px] h-[18px] text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-gray-800 leading-tight">Log Aktivitas</p>
                        <p class="text-[11px] text-gray-400">Riwayat kehadiran & kegiatan</p>
                    </div>
                    <span class="text-[10px] font-semibold text-gray-400 shrink-0">Lihat</span>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
                </RouterLink>
            </div>
        </section>

        <!-- ══ BANNER card-5 ══ -->
        <div class="rounded-3xl overflow-hidden border border-gray-100 bg-gradient-to-br from-slate-50 to-blue-50">
            <img src="/img/card-5.png" alt="Info PP An-Nur" class="w-full h-auto object-contain" loading="lazy" />
        </div>
    </div>
</template>

<style scoped>
.anim-in>* {
    animation: rise .32s cubic-bezier(.22, 1, .36, 1) both;
}

.anim-in>*:nth-child(2) {
    animation-delay: .05s;
}

.anim-in>*:nth-child(3) {
    animation-delay: .1s;
}

.anim-in>*:nth-child(4) {
    animation-delay: .15s;
}

@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .anim-in>* {
        animation: none;
    }
}
</style>
