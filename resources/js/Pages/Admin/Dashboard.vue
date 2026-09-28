<template>
    <AdminLayout title="Dashboard" subtitle="Monitoring">
        <Head title="Dashboard" />

        <!-- ══ RINGKASAN UTAMA ═══════════════════════════════════════════════
             Satu baris pembuka: sapaan, angka kunci hari ini, dan status
             penyegaran. Persentase hadir cukup muncul SEKALI di sini (dulu
             ada di hero dan diulang lagi di kartu donut). ──────────────────── -->
        <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl mb-4 px-5 py-5 sm:px-7 sm:py-6 text-white"
            style="background: linear-gradient(120deg,#1E1B4B 0%,#312E81 55%,#4338CA 100%)">
            <div class="absolute -top-16 -right-8 w-52 h-52 rounded-full bg-white/10"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-indigo-200 text-sm">{{ greeting }}, Admin</p>
                    <h1 class="text-xl sm:text-2xl font-extrabold mt-0.5">Dashboard Monitoring</h1>
                    <p class="text-indigo-200/90 text-xs mt-1">{{ hariIni }}</p>

                    <!-- Status data: penanda kesegaran yang sebelumnya tidak ada -->
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                            :class="gagal ? 'bg-red-500/25 text-red-100' : 'bg-emerald-400/20 text-emerald-100'">
                            <span class="relative flex w-2 h-2">
                                <span v-if="!gagal" class="absolute inline-flex w-full h-full rounded-full bg-emerald-300 opacity-75 animate-ping"></span>
                                <span class="relative inline-flex w-2 h-2 rounded-full" :class="gagal ? 'bg-red-300' : 'bg-emerald-300'"></span>
                            </span>
                            {{ gagal ? 'Gagal menyegarkan' : (menyegarkan ? 'Menyegarkan…' : 'Data langsung') }}
                        </span>
                        <span class="text-[11px] text-indigo-200" aria-live="polite">{{ labelSegar }}</span>
                        <button @click="muatLive(true)" :disabled="menyegarkan"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 hover:bg-white/25
                                   text-[11px] font-semibold transition disabled:opacity-50"
                            style="min-height:32px" title="Segarkan sekarang">
                            <svg style="width:13px;height:13px" :class="menyegarkan ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M20 9A8 8 0 006.3 5.7M4 15a8 8 0 0013.7 3.3" />
                            </svg>
                            Segarkan
                        </button>
                    </div>
                </div>

                <!-- Angka kunci hari ini -->
                <div class="flex items-center gap-4 sm:gap-6">
                    <div class="text-right">
                        <p class="text-[11px] text-indigo-200 uppercase tracking-wide">Hadir hari ini</p>
                        <p class="text-2xl sm:text-3xl font-extrabold tabular-nums leading-tight">
                            {{ live.absensi.hadir_total }}<span class="text-indigo-300 text-lg">/{{ live.absensi.wajib_absen }}</span>
                        </p>
                        <p class="text-[11px] text-indigo-200">
                            {{ live.absensi.belum }} belum absen · {{ live.absensi.terlambat }} terlambat
                        </p>
                    </div>
                    <div class="relative w-20 h-20 sm:w-24 sm:h-24 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3.2" />
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round"
                                :stroke-dasharray="`${show ? live.absensi.persen_hadir : 0} 100`" style="transition:stroke-dasharray .9s ease-out" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-lg font-extrabold">{{ live.absensi.persen_hadir }}%</span>
                            <span class="text-[9px] text-indigo-200 uppercase tracking-wider">hadir</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Periode gaji berjalan: konteks penting yang dulu tersembunyi -->
            <div v-if="live.periode" class="relative mt-4 pt-3 border-t border-white/15 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-indigo-100">
                <span>Periode berjalan <b class="text-white">{{ live.periode.nama }}</b></span>
                <span>{{ live.periode.mulai }} – {{ live.periode.sampai }}</span>
                <span class="px-2 py-0.5 rounded-full bg-white/15 font-semibold uppercase tracking-wide">{{ live.periode.status }}</span>
                <span v-if="live.periode.sisa_hari !== null">{{ live.periode.sisa_hari }} hari lagi menuju tutup periode</span>
            </div>
        </div>

        <!-- ══ ANTRIAN TINDAKAN ══════════════════════════════════════════════
             Paling sering dicek admin, jadi ditaruh paling atas. Yang bernilai 0
             tetap tampil tenang (abu) agar tidak menimbulkan rasa darurat. ──── -->
        <div class="mb-4">
            <div class="flex items-baseline justify-between mb-2">
                <h2 class="text-sm font-bold text-gray-700">Menunggu Tindakan</h2>
                <span class="text-[11px] text-gray-500">{{ totalAntrian }} item perlu diproses</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2.5">
                <Link v-for="a in live.antrian" :key="a.label" :href="a.url"
                    class="group rounded-2xl border p-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                    :class="a.value > 0 ? antrianCls(a.tone) : 'bg-white border-gray-100'">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold tabular-nums leading-none"
                            :class="a.value > 0 ? '' : 'text-gray-300'">{{ a.value }}</span>
                        <svg class="w-3.5 h-3.5 ml-auto text-gray-300 group-hover:translate-x-0.5 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                    <p class="text-[11.5px] font-semibold mt-1" :class="a.value > 0 ? '' : 'text-gray-500'">{{ a.label }}</p>
                </Link>
            </div>
        </div>

        <!-- ══ KPI ROW ═══════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
            <div v-for="(k, i) in kpis" :key="k.label"
                class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 sm:p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                :style="{ transitionDelay: (i * 40) + 'ms', opacity: show ? 1 : 0, transform: show ? 'none' : 'translateY(8px)' }">
                <div class="flex items-center justify-between mb-2.5">
                    <div :class="['w-9 h-9 rounded-xl flex items-center justify-center', k.bg]">
                        <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="k.icon" />
                        </svg>
                    </div>
                    <span v-if="k.trend"
                        :class="['text-[10px] font-bold px-1.5 py-0.5 rounded-md',
                            k.trend.dir === 'up' ? 'bg-emerald-50 text-emerald-700' : k.trend.dir === 'down' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500']">
                        <span v-if="k.trend.dir === 'up'">▲</span><span v-else-if="k.trend.dir === 'down'">▼</span>
                        {{ k.trend.text }}
                    </span>
                </div>
                <div class="flex items-baseline gap-1">
                    <span class="text-xl sm:text-2xl font-extrabold text-gray-900 tabular-nums leading-none">{{ k.value }}</span>
                    <span v-if="k.sub" class="text-xs text-gray-500">{{ k.sub }}</span>
                </div>
                <p class="text-[11.5px] font-medium text-gray-500 mt-1">{{ k.label }}</p>
            </div>
        </div>

        <!-- ══ MONITORING FITUR PESANTREN ════════════════════════════════════ -->
        <div v-if="live.monitoringFitur.length" class="mb-5">
            <div class="flex items-center justify-between mb-2.5">
                <h2 class="text-sm font-bold text-gray-700">Monitoring Fitur Hari Ini</h2>
                <span class="text-[11px] text-gray-500">Disegarkan tiap {{ jedaDetik }} detik · klik untuk kelola</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <Link v-for="(f, i) in live.monitoringFitur" :key="f.label" :href="f.url"
                    class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                    :style="{ transitionDelay: (i * 40) + 'ms', opacity: show ? 1 : 0, transform: show ? 'none' : 'translateY(8px)' }">
                    <div class="flex items-center justify-between mb-2">
                        <div :class="['w-9 h-9 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform', toneCls(f.tone).bg, toneCls(f.tone).text]">
                            <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="ICONS[f.icon] || ICONS.users" />
                            </svg>
                        </div>
                        <span v-if="f.alert > 0"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700">
                            {{ f.alert }}<span v-if="f.alert_label" class="font-medium"> {{ f.alert_label }}</span>
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-extrabold text-gray-800 tabular-nums leading-none">{{ f.value }}</span>
                        <span class="text-[11px] text-gray-500">{{ f.satuan }}</span>
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
            <Card title="Absensi Hari Ini" icon="check" :hint="`dari ${live.absensi.wajib_absen} guru yang wajib absen hari ini`">
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
                            <span class="text-2xl font-extrabold text-gray-900">{{ live.absensi.persen_hadir }}%</span>
                            <span class="text-[10px] text-gray-500 uppercase tracking-wide">Hadir</span>
                        </div>
                    </div>
                    <div class="flex-1 space-y-2">
                        <div v-for="d in live.absensi.donut" :key="d.label"
                            class="flex items-center gap-2 text-xs rounded-lg px-1.5 py-1 hover:bg-gray-50 transition-colors">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: d.color }"></span>
                            <span class="text-gray-600 flex-1">{{ d.label }}</span>
                            <span class="font-bold text-gray-900 tabular-nums">{{ d.value }}</span>
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="Tren Kehadiran (7 Hari)" icon="trend">
                <div class="flex items-end justify-between gap-2 h-36 pt-2">
                    <div v-for="(t, i) in trenKehadiran" :key="i"
                        class="flex-1 flex flex-col items-center gap-1.5 group"
                        :title="`${t.tanggal || t.label}: ${t.hadir} hadir dari ${stats.total_guru} guru (${t.persen}%)`">
                        <span class="text-[11px] font-bold text-gray-500 group-hover:text-indigo-600 transition-colors">{{ t.hadir }}</span>
                        <div class="w-full bg-gray-100 rounded-lg overflow-hidden flex items-end" style="height:100px">
                            <div class="w-full rounded-lg transition-all duration-700 ease-out"
                                :class="i === trenKehadiran.length - 1 ? 'bg-gradient-to-t from-indigo-600 to-indigo-400' : 'bg-indigo-200 group-hover:bg-indigo-300'"
                                :style="{ height: (show ? t.persen : 0) + '%' }"></div>
                        </div>
                        <span class="text-[11px]" :class="i === trenKehadiran.length - 1 ? 'text-indigo-600 font-bold' : 'text-gray-500'">{{ t.label }}</span>
                    </div>
                </div>
            </Card>

            <Card title="Distribusi Kinerja" icon="trophy" :hint="stats.periode_aktif ? `periode ${stats.periode_aktif}` : null">
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
                        <span class="text-[11px] text-gray-500">Rata-rata skor</span>
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
                        <span v-for="(p, i) in trenGaji" :key="i" class="text-[11px] text-gray-500">{{ p.label }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500 py-10 text-center">Belum ada data penggajian.</p>
            </Card>

            <Card title="Perlu Perhatian" icon="alert" hint="5 skor terendah periode ini">
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
                    <p class="text-sm text-gray-500">Semua kinerja baik pada periode ini.</p>
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
                            <span v-for="d in dayTicks" :key="d" class="absolute text-[10px] text-gray-500 -translate-x-1/2"
                                :style="{ left: ((d - 0.5) / gantt.hari * 100) + '%' }">{{ d }}</span>
                        </div>
                    </div>
                    <!-- baris -->
                    <div class="space-y-1.5">
                        <div v-for="(it, i) in gantt.items" :key="i" class="flex items-center">
                            <div class="w-44 shrink-0 pr-3">
                                <p class="text-xs font-semibold text-gray-700 truncate">{{ it.label }}</p>
                                <p class="text-[10.5px] text-gray-500">{{ it.kategori }}</p>
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
            <p v-else class="text-sm text-gray-500 py-10 text-center">Tidak ada kegiatan terjadwal bulan ini.</p>
        </Card>

    </AdminLayout>
</template>

<script setup>
import { computed, h, ref, reactive, onMounted, onBeforeUnmount } from 'vue'
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
    live: { type: Object, default: () => ({}) },
})

// ── Penyegaran berkala ────────────────────────────────────────────────────────
// Dashboard dulu hanya memuat data saat halaman dibuka meski kartunya menulis
// "real-time" — admin harus menekan F5. Sekarang bagian yang benar-benar berubah
// (absensi hari ini, monitoring, antrian) ditarik berkala dari endpoint ringan.
const jedaDetik = 30
const live = reactive({
    absensi: props.live.absensi ?? { donut: [], wajib_absen: 0, hadir_total: 0, persen_hadir: 0, belum: 0, terlambat: 0 },
    monitoringFitur: props.live.monitoringFitur ?? props.monitoringFitur ?? [],
    antrian: props.live.antrian ?? [],
    periode: props.live.periode ?? null,
    diperbarui_iso: props.live.diperbarui_iso ?? new Date().toISOString(),
})

const menyegarkan = ref(false)
const gagal = ref(false)
const sekarang = ref(Date.now())
let timerMuat = null
let timerLabel = null

const labelSegar = computed(() => {
    const t = new Date(live.diperbarui_iso).getTime()
    const detik = Math.max(0, Math.round((sekarang.value - t) / 1000))
    if (gagal.value) return 'terakhir berhasil ' + (detik < 60 ? `${detik} detik lalu` : `${Math.round(detik / 60)} menit lalu`)
    if (detik < 10) return 'baru saja diperbarui'
    if (detik < 60) return `diperbarui ${detik} detik lalu`
    return `diperbarui ${Math.round(detik / 60)} menit lalu`
})

const totalAntrian = computed(() => (live.antrian || []).reduce((a, b) => a + (b.value || 0), 0))

async function muatLive(manual = false) {
    if (menyegarkan.value) return
    menyegarkan.value = true
    try {
        const r = await fetch(route('admin.dashboard.live'), {
            headers: { Accept: 'application/json' }, credentials: 'same-origin',
        })
        const d = await r.json()
        if (d?.success) {
            live.absensi = d.absensi
            live.monitoringFitur = d.monitoringFitur
            live.antrian = d.antrian
            live.periode = d.periode
            live.diperbarui_iso = d.diperbarui_iso
            gagal.value = false
        } else { gagal.value = true }
    } catch (e) {
        // Jaringan putus: biarkan angka terakhir tampil, tandai statusnya saja —
        // lebih baik daripada mengosongkan dashboard.
        gagal.value = true
    } finally {
        menyegarkan.value = false
        sekarang.value = Date.now()
        if (manual) mulaiTimer()   // hitung ulang jeda setelah segarkan manual
    }
}

function mulaiTimer() {
    if (timerMuat) clearInterval(timerMuat)
    timerMuat = setInterval(() => {
        // Hemat: jangan menarik data saat tab tidak terlihat.
        if (document.visibilityState === 'visible') muatLive()
    }, jedaDetik * 1000)
}

function saatKembali() {
    if (document.visibilityState === 'visible') muatLive()
}

onMounted(() => {
    mulaiTimer()
    timerLabel = setInterval(() => { sekarang.value = Date.now() }, 5000)
    document.addEventListener('visibilitychange', saatKembali)
})
onBeforeUnmount(() => {
    if (timerMuat) clearInterval(timerMuat)
    if (timerLabel) clearInterval(timerLabel)
    document.removeEventListener('visibilitychange', saatKembali)
})

/** Warna kartu antrian saat ada yang menunggu (0 tetap netral). */
function antrianCls(t) {
    return {
        blue:    'bg-blue-50 border-blue-100 text-blue-800',
        violet:  'bg-violet-50 border-violet-100 text-violet-800',
        amber:   'bg-amber-50 border-amber-100 text-amber-800',
        rose:    'bg-rose-50 border-rose-100 text-rose-800',
        emerald: 'bg-emerald-50 border-emerald-100 text-emerald-800',
        gray:    'bg-slate-50 border-slate-200 text-slate-700',
    }[t] ?? 'bg-slate-50 border-slate-200 text-slate-700'
}

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
    { label: 'Hadir Hari Ini', value: `${live.absensi.hadir_total}/${live.absensi.wajib_absen}`, sub: `${live.absensi.persen_hadir}%`, icon: ICONS.check, bg: 'bg-emerald-50 text-emerald-600', trend: hadirTrend.value },
    { label: 'Rata Kinerja', value: props.stats.rata_kinerja, sub: '/100', icon: ICONS.trophy, bg: 'bg-amber-50 text-amber-600' },
    { label: 'Gaji Periode', value: rpShort(props.periodeTerkini?.gaji_bersih ?? 0), icon: ICONS.wallet, bg: 'bg-sky-50 text-sky-600', trend: gajiTrend.value },
    { label: 'Lembur', value: props.stats.lembur_bulan_ini, sub: 'bln ini', icon: ICONS.moon, bg: 'bg-violet-50 text-violet-600' },
    { label: 'Punishment', value: props.stats.punishment_bulan_ini, sub: 'bln ini', icon: ICONS.gavel, bg: 'bg-rose-50 text-rose-600' },
])

// ── Donut ──────────────────────────────────────────────────────────────────
const donutSegments = computed(() => {
    const sumber = live.absensi.donut ?? props.donutAbsensi
    const total = sumber.reduce((a, b) => a + (b.value || 0), 0)
    let acc = 0
    return sumber.filter(s => s.value > 0).map(s => {
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
        p.title ? h('div', { class: 'flex items-start gap-2 mb-3' }, [
            p.icon ? h('svg', { class: 'w-4 h-4 text-indigo-500 mt-0.5 shrink-0', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' },
                [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: CARD_ICONS[p.icon] || '' })]) : null,
            h('div', { class: 'min-w-0' }, [
                h('h3', { class: 'text-sm font-semibold text-gray-800' }, p.title),
                // Keterangan jendela data: pembaca tahu angka ini "dari mana"
                p.hint ? h('p', { class: 'text-[11px] text-gray-500 mt-0.5' }, p.hint) : null,
            ]),
        ]) : null,
        slots.default ? slots.default() : null,
    ])
Card.props = ['title', 'icon', 'hint']
</script>
