<template>
    <AdminLayout title="Dashboard" subtitle="Monitoring">
        <Head title="Dashboard" />

        <!-- ══ HERO ══════════════════════════════════════════════════════════ -->
        <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl mb-5 px-5 py-5 sm:px-8 sm:py-7 text-white"
            style="background: linear-gradient(120deg,#1E1B4B 0%,#3730A3 55%,#4F46E5 100%)">
            <!-- dekorasi -->
            <div class="absolute -top-16 -right-10 w-56 h-56 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-20 right-24 w-44 h-44 rounded-full bg-white/5"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-indigo-200 text-sm">{{ greeting }}, Admin 👋</p>
                    <h1 class="text-xl sm:text-2xl font-extrabold mt-1">Dashboard Monitoring</h1>
                    <p class="text-indigo-200 text-xs mt-1">{{ hariIni }}</p>
                    <p class="text-sm mt-3 text-white/90">
                        <b class="text-white">{{ stats.hadir_hari_ini }}</b> dari
                        <b class="text-white">{{ stats.total_guru }}</b> guru hadir hari ini
                        <span class="inline-flex items-center gap-1 ml-1 px-2 py-0.5 rounded-full bg-white/15 text-xs font-semibold">
                            {{ stats.persen_hadir }}%
                        </span>
                    </p>
                </div>
                <!-- ring hadir -->
                <div class="relative w-20 h-20 sm:w-24 sm:h-24 shrink-0">
                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="3" />
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"
                            :stroke-dasharray="`${show ? stats.persen_hadir : 0} 100`" style="transition:stroke-dasharray 1s ease-out" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-lg font-extrabold">{{ stats.persen_hadir }}%</span>
                        <span class="text-[9px] text-indigo-200">HADIR</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ KPI ROW ═══════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
            <div v-for="(k, i) in kpis" :key="k.label"
                class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 sm:p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                :style="{ transitionDelay: (i * 40) + 'ms', opacity: show ? 1 : 0, transform: show ? 'none' : 'translateY(8px)' }">
                <div class="flex items-center justify-between mb-2.5">
                    <div :class="['w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform', k.bg]">
                        <svg class="w-4.5 h-4.5" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="k.icon" />
                        </svg>
                    </div>
                    <span v-if="k.trend"
                        :class="['text-[10px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-0.5',
                            k.trend.dir === 'up' ? 'bg-emerald-50 text-emerald-600' : k.trend.dir === 'down' ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-400']">
                        <span v-if="k.trend.dir === 'up'">▲</span><span v-else-if="k.trend.dir === 'down'">▼</span>
                        {{ k.trend.text }}
                    </span>
                </div>
                <div class="flex items-baseline gap-1">
                    <span class="text-xl sm:text-2xl font-extrabold text-gray-800 tabular-nums leading-none">{{ k.value }}</span>
                    <span v-if="k.sub" class="text-xs text-gray-400">{{ k.sub }}</span>
                </div>
                <p class="text-[11px] font-medium text-gray-400 mt-1">{{ k.label }}</p>
            </div>
        </div>

        <!-- ══ MONITORING FITUR PESANTREN ════════════════════════════════════ -->
        <div v-if="monitoringFitur.length" class="mb-5">
            <div class="flex items-center justify-between mb-2.5">
                <h2 class="text-sm font-bold text-gray-700">Monitoring Fitur</h2>
                <span class="text-[11px] text-gray-400">Real-time hari ini · klik untuk kelola</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <Link v-for="(f, i) in monitoringFitur" :key="f.label" :href="f.url"
                    class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                    :style="{ transitionDelay: (i * 40) + 'ms', opacity: show ? 1 : 0, transform: show ? 'none' : 'translateY(8px)' }">
                    <div class="flex items-center justify-between mb-2">
                        <div :class="['w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform', toneCls(f.tone).bg, toneCls(f.tone).text]">
                            <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONS[f.icon] || ICONS.users" />
                            </svg>
                        </div>
                        <span v-if="f.alert > 0"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-600">
                            {{ f.alert }}<span v-if="f.alert_label" class="font-medium"> {{ f.alert_label }}</span>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-extrabold text-gray-800 tabular-nums leading-none">{{ f.value }}</span>
                        <span class="text-[11px] text-gray-400">{{ f.satuan }}</span>
                    </div>
                    <p class="text-xs font-semibold text-gray-600 mt-1 flex items-center gap-1">
                        {{ f.label }}
                        <svg class="w-3 h-3 text-gray-300 group-hover:text-indigo-400 group-hover:translate-x-0.5 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </p>
                </Link>
            </div>
        </div>

        <!-- ══ ROW: Donut + Tren Kehadiran + Kinerja ════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <Card title="Absensi Hari Ini" icon="check">
                <div class="flex items-center gap-5">
                    <div class="relative w-32 h-32 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#F1F5F9" stroke-width="3.6" />
                            <circle v-for="(s, i) in donutSegments" :key="i" cx="18" cy="18" r="15.915" fill="none"
                                :stroke="s.color" stroke-width="3.6" :stroke-dasharray="show ? s.dash : '0 100'"
                                :stroke-dashoffset="s.offset" stroke-linecap="round"
                                style="transition:stroke-dasharray .9s ease-out" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-extrabold text-gray-800">{{ stats.persen_hadir }}%</span>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wide">Hadir</span>
                        </div>
                    </div>
                    <div class="flex-1 space-y-2">
                        <div v-for="d in donutAbsensi" :key="d.label"
                            class="flex items-center gap-2 text-xs rounded-lg px-1.5 py-1 hover:bg-gray-50 transition-colors">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: d.color }"></span>
                            <span class="text-gray-500 flex-1">{{ d.label }}</span>
                            <span class="font-bold text-gray-800 tabular-nums">{{ d.value }}</span>
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="Tren Kehadiran (7 Hari)" icon="trend">
                <div class="flex items-end justify-between gap-2 h-36 pt-2">
                    <div v-for="(t, i) in trenKehadiran" :key="i"
                        class="flex-1 flex flex-col items-center gap-1.5 group" :title="`${t.label}: ${t.hadir} hadir`">
                        <span class="text-[10px] font-bold text-gray-400 group-hover:text-indigo-600 transition-colors">{{ t.hadir }}</span>
                        <div class="w-full bg-gray-100 rounded-lg overflow-hidden flex items-end" style="height:100px">
                            <div class="w-full rounded-lg transition-all duration-700 ease-out"
                                :class="i === trenKehadiran.length - 1 ? 'bg-gradient-to-t from-indigo-600 to-indigo-400' : 'bg-indigo-200 group-hover:bg-indigo-300'"
                                :style="{ height: (show ? t.persen : 0) + '%' }"></div>
                        </div>
                        <span class="text-[10px]" :class="i === trenKehadiran.length - 1 ? 'text-indigo-600 font-bold' : 'text-gray-400'">{{ t.label }}</span>
                    </div>
                </div>
            </Card>

            <Card title="Distribusi Kinerja" icon="trophy">
                <div class="space-y-2.5 pt-1">
                    <div v-for="g in kinerjaDistribusi" :key="g.grade" class="flex items-center gap-2"
                        :title="`Grade ${g.grade}: ${g.jumlah} guru`">
                        <span class="w-6 h-6 rounded-lg text-xs font-bold flex items-center justify-center text-white" :style="{ background: g.warna }">{{ g.grade }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700 ease-out"
                                :style="{ width: (show ? barPct(g.jumlah, maxGrade) : 0) + '%', background: g.warna }"></div>
                        </div>
                        <span class="w-5 text-xs font-bold text-gray-700 text-right tabular-nums">{{ g.jumlah }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 mt-1 border-t border-gray-50">
                        <span class="text-[11px] text-gray-400">Rata-rata skor</span>
                        <span class="text-sm font-extrabold text-indigo-600">{{ stats.rata_kinerja }}</span>
                    </div>
                </div>
            </Card>
        </div>

        <!-- ══ ROW: Tren Gaji + Perlu Perhatian ═════════════════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <Card title="Tren Gaji Bersih" icon="wallet" class="lg:col-span-2">
                <div v-if="trenGaji.length" class="pt-1">
                    <div class="flex items-end gap-2 mb-2">
                        <span class="text-2xl font-extrabold text-gray-800">{{ rpShort(gajiTerkini) }}</span>
                        <span v-if="gajiTrend"
                            :class="['text-xs font-bold mb-1', gajiTrend.dir === 'up' ? 'text-emerald-600' : gajiTrend.dir === 'down' ? 'text-red-500' : 'text-gray-400']">
                            {{ gajiTrend.dir === 'up' ? '▲' : gajiTrend.dir === 'down' ? '▼' : '' }} {{ gajiTrend.text }}
                        </span>
                    </div>
                    <svg :viewBox="`0 0 ${gajiW} 130`" class="w-full" style="height:150px" preserveAspectRatio="none">
                        <polyline :points="gajiArea" fill="url(#gg)" stroke="none" />
                        <polyline :points="gajiLine" fill="none" stroke="#4F46E5" stroke-width="2.5"
                            vector-effect="non-scaling-stroke" stroke-linejoin="round"
                            :style="{ strokeDasharray: 1200, strokeDashoffset: show ? 0 : 1200, transition: 'stroke-dashoffset 1.2s ease-out' }" />
                        <circle v-for="(p, i) in gajiDots" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#fff" stroke="#4F46E5" stroke-width="2" vector-effect="non-scaling-stroke" />
                        <defs>
                            <linearGradient id="gg" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#4F46E5" stop-opacity="0.2" />
                                <stop offset="100%" stop-color="#4F46E5" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="flex justify-between mt-1">
                        <span v-for="(p, i) in trenGaji" :key="i" class="text-[10px] text-gray-400">{{ p.label }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 py-10 text-center">Belum ada data penggajian.</p>
            </Card>

            <Card title="Perlu Perhatian" icon="alert">
                <div v-if="kinerjaRendah.length" class="space-y-1.5">
                    <Link v-for="k in kinerjaRendah" :key="k.guru_id"
                        :href="route('admin.smart-payroll.kinerja.detail-guru', k.guru_id)"
                        class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-indigo-50/50 transition-colors group">
                        <span class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold text-white shrink-0"
                            :style="{ background: gradeColor(k.grade) }">{{ k.grade }}</span>
                        <span class="flex-1 text-sm text-gray-700 truncate group-hover:text-indigo-600">{{ k.nama }}</span>
                        <span class="text-sm font-extrabold tabular-nums" :class="k.skor < 60 ? 'text-red-600' : 'text-gray-700'">{{ k.skor }}</span>
                    </Link>
                </div>
                <div v-else class="py-10 text-center">
                    <div class="text-3xl mb-1">🎉</div>
                    <p class="text-sm text-gray-400">Semua kinerja baik bulan ini.</p>
                </div>
            </Card>
        </div>

        <!-- ══ GANTT TIMELINE ════════════════════════════════════════════════ -->
        <Card :title="`Timeline Kegiatan — ${gantt.bulan_label}`" icon="calendar">
            <div v-if="gantt.items.length" class="overflow-x-auto -mx-1 px-1">
                <div class="min-w-[680px]">
                    <!-- header hari + weekend shading -->
                    <div class="flex items-center mb-2">
                        <div class="w-44 shrink-0"></div>
                        <div class="flex-1 relative h-5">
                            <div v-for="w in gantt.weekends" :key="'w' + w" class="absolute top-0 bottom-0 bg-slate-100/70"
                                :style="{ left: ((w - 1) / gantt.hari * 100) + '%', width: (1 / gantt.hari * 100) + '%' }"></div>
                            <span v-for="d in dayTicks" :key="d" class="absolute text-[9px] text-gray-400 -translate-x-1/2"
                                :style="{ left: ((d - 0.5) / gantt.hari * 100) + '%' }">{{ d }}</span>
                        </div>
                    </div>
                    <!-- baris -->
                    <div class="space-y-1.5">
                        <div v-for="(it, i) in gantt.items" :key="i" class="flex items-center">
                            <div class="w-44 shrink-0 pr-3">
                                <p class="text-xs font-semibold text-gray-700 truncate">{{ it.label }}</p>
                                <p class="text-[10px] text-gray-400">{{ it.kategori }}</p>
                            </div>
                            <div class="flex-1 relative h-8 rounded-lg overflow-hidden bg-gray-50/80">
                                <!-- weekend shading -->
                                <div v-for="w in gantt.weekends" :key="'b' + w" class="absolute top-0 bottom-0 bg-slate-100/70"
                                    :style="{ left: ((w - 1) / gantt.hari * 100) + '%', width: (1 / gantt.hari * 100) + '%' }"></div>
                                <!-- today line -->
                                <div class="absolute top-0 bottom-0 w-0.5 bg-red-400 z-10"
                                    :style="{ left: ((gantt.hari_ini - 0.5) / gantt.hari * 100) + '%' }"></div>
                                <!-- bar -->
                                <div class="absolute top-1.5 bottom-1.5 rounded-md flex items-center px-2 z-20 shadow-sm transition-all duration-700 ease-out hover:brightness-110"
                                    :title="`${it.label} · ${it.ket}`"
                                    :style="{ left: ((it.mulai - 1) / gantt.hari * 100) + '%', width: (show ? (it.durasi / gantt.hari * 100) : 0) + '%', background: it.warna }">
                                    <span class="text-[10px] font-semibold text-white truncate">{{ it.ket }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- legend -->
                    <div class="flex flex-wrap gap-4 mt-4 pt-3 border-t border-gray-100">
                        <span v-for="lg in legend" :key="lg.label" class="flex items-center gap-1.5 text-[11px] text-gray-500">
                            <span class="w-3 h-3 rounded" :style="{ background: lg.color }"></span>{{ lg.label }}
                        </span>
                        <span class="flex items-center gap-1.5 text-[11px] text-gray-500"><span class="w-0.5 h-3 bg-red-400"></span>Hari ini</span>
                        <span class="flex items-center gap-1.5 text-[11px] text-gray-500"><span class="w-3 h-3 rounded bg-slate-100"></span>Akhir pekan</span>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400 py-10 text-center">Tidak ada kegiatan terjadwal bulan ini.</p>
        </Card>

    </AdminLayout>
</template>

<script setup>
import { computed, h, ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    donutAbsensi: { type: Array, default: () => [] },
    trenKehadiran: { type: Array, default: () => [] },
    trenGaji: { type: Array, default: () => [] },
    kinerjaDistribusi: { type: Array, default: () => [] },
    gantt: { type: Object, default: () => ({ hari: 30, items: [], hari_ini: 1, bulan_label: '', weekends: [] }) },
    kinerjaRendah: { type: Array, default: () => [] },
    periodeTerkini: { type: Object, default: null },
    monitoringFitur: { type: Array, default: () => [] },
})

function toneCls(t) {
    return {
        violet:  { bg: 'bg-violet-50',  text: 'text-violet-600' },
        indigo:  { bg: 'bg-indigo-50',  text: 'text-indigo-600' },
        amber:   { bg: 'bg-amber-50',   text: 'text-amber-600' },
        blue:    { bg: 'bg-blue-50',    text: 'text-blue-600' },
        rose:    { bg: 'bg-rose-50',    text: 'text-rose-600' },
        emerald: { bg: 'bg-emerald-50', text: 'text-emerald-600' },
        teal:    { bg: 'bg-teal-50',    text: 'text-teal-600' },
        gray:    { bg: 'bg-gray-100',   text: 'text-gray-500' },
    }[t] ?? { bg: 'bg-gray-100', text: 'text-gray-500' }
}

const show = ref(false)
onMounted(() => requestAnimationFrame(() => { show.value = true }))

const jam = new Date().getHours()
const greeting = jam < 11 ? 'Selamat pagi' : jam < 15 ? 'Selamat siang' : jam < 18 ? 'Selamat sore' : 'Selamat malam'
const hariIni = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

const ICONS = {
    users: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z',
    check: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    trophy: 'M8 21h8m-4-4v4m6-16h2a2 2 0 010 4 6 6 0 01-6 6 6 6 0 01-6-6 2 2 0 010-4h2m8 0V3H6v2',
    wallet: 'M3 10h18M7 15h2m10-9H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2z',
    moon: 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z',
    gavel: 'M9 12l-4.5 4.5M14 7l3 3M5 21h6m4.5-16.5l4 4L9 21l-4-4z',
    trend: 'M3 17l6-6 4 4 8-8M21 7h-4m4 0v4',
    alert: 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z',
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    book: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.247m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.247',
    swap: 'M8 7h12m0 0l-4-4m4 4l-4 4m4 6H4m0 0l4 4m-4-4l4-4',
    scan: 'M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2m8-16h2a2 2 0 012 2v2m-4 12h2a2 2 0 002-2v-2M8 12h8',
    flag: 'M3 21V4a1 1 0 011-1h11l-2 4 2 4H4M3 21h4',
    shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
}

// ── KPI cards + trend deltas (diturunkan dari data tren) ───────────────────
const hadirTrend = computed(() => {
    const t = props.trenKehadiran
    if (t.length < 2) return null
    const d = t[t.length - 1].hadir - t[t.length - 2].hadir
    return { dir: d > 0 ? 'up' : d < 0 ? 'down' : 'flat', text: (d > 0 ? '+' : '') + d }
})
const gajiTerkini = computed(() => props.trenGaji.length ? props.trenGaji[props.trenGaji.length - 1].nilai : 0)
const gajiTrend = computed(() => {
    const g = props.trenGaji
    if (g.length < 2 || !g[g.length - 2].nilai) return null
    const pct = Math.round((g[g.length - 1].nilai - g[g.length - 2].nilai) / g[g.length - 2].nilai * 100)
    return { dir: pct > 0 ? 'up' : pct < 0 ? 'down' : 'flat', text: Math.abs(pct) + '%' }
})

const kpis = computed(() => [
    { label: 'Total Guru', value: props.stats.total_guru, icon: ICONS.users, bg: 'bg-indigo-50 text-indigo-600' },
    { label: 'Hadir Hari Ini', value: `${props.stats.hadir_hari_ini}/${props.stats.total_guru}`, sub: `${props.stats.persen_hadir}%`, icon: ICONS.check, bg: 'bg-emerald-50 text-emerald-600', trend: hadirTrend.value },
    { label: 'Rata Kinerja', value: props.stats.rata_kinerja, sub: '/100', icon: ICONS.trophy, bg: 'bg-amber-50 text-amber-600' },
    { label: 'Gaji Periode', value: rpShort(props.periodeTerkini?.gaji_bersih ?? 0), icon: ICONS.wallet, bg: 'bg-sky-50 text-sky-600', trend: gajiTrend.value },
    { label: 'Lembur', value: props.stats.lembur_bulan_ini, sub: 'bln ini', icon: ICONS.moon, bg: 'bg-violet-50 text-violet-600' },
    { label: 'Punishment', value: props.stats.punishment_bulan_ini, sub: 'bln ini', icon: ICONS.gavel, bg: 'bg-rose-50 text-rose-600' },
])

// ── Donut ──────────────────────────────────────────────────────────────────
const donutSegments = computed(() => {
    const total = props.donutAbsensi.reduce((a, b) => a + (b.value || 0), 0)
    let acc = 0
    return props.donutAbsensi.filter(s => s.value > 0).map(s => {
        const pct = total ? (s.value / total) * 100 : 0
        const seg = { color: s.color, dash: `${pct} ${100 - pct}`, offset: 100 - acc + 25 }
        acc += pct
        return seg
    })
})

const maxGrade = computed(() => Math.max(1, ...props.kinerjaDistribusi.map(g => g.jumlah)))
function barPct(v, max) { return max > 0 ? Math.round((v / max) * 100) : 0 }

// ── Tren gaji ────────────────────────────────────────────────────────────────
const gajiW = 300
const gajiPts = computed(() => {
    const data = props.trenGaji.map(p => p.nilai)
    if (!data.length) return []
    const max = Math.max(...data, 1)
    const n = data.length
    const stepX = n > 1 ? gajiW / (n - 1) : gajiW
    return data.map((v, i) => ({ x: n > 1 ? i * stepX : gajiW / 2, y: 125 - (v / max) * 110 }))
})
const gajiLine = computed(() => gajiPts.value.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '))
const gajiArea = computed(() => gajiPts.value.length ? `0,130 ${gajiLine.value} ${gajiW},130` : '')
const gajiDots = computed(() => gajiPts.value)

const dayTicks = computed(() => {
    const n = props.gantt.hari
    const step = n > 20 ? 5 : (n > 10 ? 2 : 1)
    const ticks = []
    for (let d = 1; d <= n; d += step) ticks.push(d)
    if (ticks[ticks.length - 1] !== n) ticks.push(n)
    return ticks
})

const legend = [
    { label: 'Penggajian', color: '#4F46E5' },
    { label: 'Tugas', color: '#0D9488' },
    { label: 'Lembur', color: '#DB2777' },
]

function rpShort(n) {
    n = Number(n || 0)
    if (n >= 1e9) return 'Rp ' + (n / 1e9).toFixed(1).replace('.', ',') + ' M'
    if (n >= 1e6) return 'Rp ' + (n / 1e6).toFixed(1).replace('.', ',') + ' jt'
    if (n >= 1e3) return 'Rp ' + Math.round(n / 1e3) + ' rb'
    return 'Rp ' + n
}
function gradeColor(g) {
    return { A: '#059669', B: '#0284C7', C: '#F59E0B', D: '#EA580C', E: '#DC2626' }[g] ?? '#64748B'
}

// ── Card panel (functional) ─────────────────────────────────────────────────
const CARD_ICONS = ICONS
const Card = (p, { slots }) => h('div',
    { class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-5 transition-shadow hover:shadow-md' },
    [
        p.title ? h('div', { class: 'flex items-center gap-2 mb-3' }, [
            p.icon ? h('svg', { class: 'w-4 h-4 text-indigo-400', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' },
                [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: CARD_ICONS[p.icon] || '' })]) : null,
            h('h3', { class: 'text-sm font-semibold text-gray-700' }, p.title),
        ]) : null,
        slots.default ? slots.default() : null,
    ])
Card.props = ['title', 'icon']
</script>
