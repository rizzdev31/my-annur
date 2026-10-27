<template>
    <AdminLayout title="Log Aktivitas Kinerja" subtitle="Smart Payroll">

        <Head title="Log Aktivitas Kinerja" />

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">Log Aktivitas Kinerja</h2>
                    <span v-if="periode.berjalan"
                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                        periode berjalan
                    </span>
                </div>
                <!-- Jendela penilaian ditulis terang-terangan: periode kinerja
                     mengikuti periode gaji (26 ke 25), bukan bulan kalender. -->
                <p class="text-sm text-gray-500 mt-0.5">
                    <b class="text-gray-700">{{ periode.label }}</b> ·
                    dinilai {{ periode.dari }} &ndash; {{ periode.sampai }}
                    <span v-if="periode.ikut_gaji" class="text-gray-400">(ikut periode penggajian)</span>
                    <span v-else class="text-amber-600">(bulan kalender — periode gaji belum dibuat)</span>
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" v-model="autoSegar" class="rounded border-gray-300" />
                    Segarkan otomatis
                </label>
                <a :href="urlPdf"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#2E3160] text-white text-sm font-semibold rounded-xl hover:bg-[#252850] transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h5l2 2h5a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    Unduh PDF
                </a>
            </div>
        </div>

        <!-- Ringkasan pergerakan -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Total pergerakan</p>
                <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ ringkasan.total }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Kenaikan</p>
                <p class="text-2xl font-bold text-emerald-600 tabular-nums">{{ ringkasan.naik }}</p>
                <p v-if="ringkasan.naik" class="text-[11px] text-gray-400">rata-rata +{{ ringkasan.rata_naik }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Penurunan</p>
                <p class="text-2xl font-bold text-red-600 tabular-nums">{{ ringkasan.turun }}</p>
                <p v-if="ringkasan.turun" class="text-[11px] text-gray-400">rata-rata {{ ringkasan.rata_turun }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Nilai awal</p>
                <p class="text-2xl font-bold text-gray-500 tabular-nums">{{ ringkasan.awal }}</p>
                <p class="text-[11px] text-gray-400">skor pertama kali</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Guru terdampak</p>
                <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ ringkasan.guru_terdampak }}</p>
            </div>
        </div>

        <!-- Yang paling bergerak: pertanyaan pertama saat mengaudit -->
        <div v-if="ringkasan.naik_terbesar || ringkasan.turun_terbesar"
            class="grid md:grid-cols-2 gap-3 mb-5">
            <div v-if="ringkasan.naik_terbesar" class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4">
                <p class="text-xs font-semibold text-emerald-800">Kenaikan terbesar</p>
                <p class="text-sm text-emerald-900 mt-1">
                    {{ ringkasan.naik_terbesar.guru }} ·
                    <b>{{ ringkasan.naik_terbesar.skor_lama }} &rarr; {{ ringkasan.naik_terbesar.skor_baru }}</b>
                    (+{{ ringkasan.naik_terbesar.selisih }})
                </p>
                <p class="text-[11px] text-emerald-700 mt-0.5">{{ ringkasan.naik_terbesar.label_sebab }}</p>
            </div>
            <div v-if="ringkasan.turun_terbesar" class="bg-red-50 border border-red-100 rounded-2xl p-4">
                <p class="text-xs font-semibold text-red-800">Penurunan terbesar</p>
                <p class="text-sm text-red-900 mt-1">
                    {{ ringkasan.turun_terbesar.guru }} ·
                    <b>{{ ringkasan.turun_terbesar.skor_lama }} &rarr; {{ ringkasan.turun_terbesar.skor_baru }}</b>
                    ({{ ringkasan.turun_terbesar.selisih }})
                </p>
                <p class="text-[11px] text-red-700 mt-0.5">{{ ringkasan.turun_terbesar.label_sebab }}</p>
            </div>
        </div>

        <!-- Saringan -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[230px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Periode kinerja</label>
                    <select :value="periodeNilai" @change="pilihPeriode($event.target.value)"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option v-for="o in periodeOpsi" :key="o.tahun + '-' + o.bulan"
                            :value="o.tahun + '-' + o.bulan">
                            {{ o.label }} &middot; {{ o.dari }}&ndash;{{ o.sampai }}
                        </option>
                    </select>
                </div>

                <div class="min-w-[180px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Guru</label>
                    <select v-model="guruId" @change="reload"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option :value="null">Semua guru</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>

                <div class="min-w-[170px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sebab</label>
                    <select v-model="sebab" @change="reload"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Semua sebab</option>
                        <option v-for="s in sebabOpsi" :key="s.nilai" :value="s.nilai">{{ s.label }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Arah</label>
                    <div class="flex gap-1">
                        <button v-for="a in arahOpsi" :key="a.nilai" @click="pilihArah(a.nilai)"
                            class="px-3 py-2 text-xs font-semibold rounded-xl border transition-colors"
                            :class="arah === a.nilai
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                            {{ a.label }}
                        </button>
                    </div>
                </div>

                <button v-if="guruId || sebab || arah" @click="hapusSaringan"
                    class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-xl transition-colors">
                    Hapus saringan
                </button>
            </div>
            <p v-if="autoSegar" class="text-[11px] text-gray-400 mt-3">
                Halaman menyegarkan diri tiap 30 detik. Terakhir dimuat {{ jamMuat }}.
            </p>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">
                        <th class="px-5 py-3.5">Waktu</th>
                        <th class="px-5 py-3.5">Guru</th>
                        <th class="px-5 py-3.5 text-right">Pergerakan skor</th>
                        <th class="px-5 py-3.5">Sebab</th>
                        <th class="px-5 py-3.5">Alasan</th>
                        <th class="px-5 py-3.5">Oleh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="b in baris" :key="b.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3.5 text-sm text-gray-500 whitespace-nowrap">{{ b.waktu }}</td>
                        <td class="px-5 py-3.5 text-sm font-medium text-gray-800">{{ b.guru }}</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <span v-if="b.arah === 'awal'" class="text-sm text-gray-500">
                                nilai awal <b class="text-gray-800">{{ b.skor_baru }}</b>
                            </span>
                            <span v-else class="text-sm">
                                <span class="text-gray-400 tabular-nums">{{ b.skor_lama }}</span>
                                <span class="text-gray-300 mx-1">&rarr;</span>
                                <span class="font-semibold text-gray-800 tabular-nums">{{ b.skor_baru }}</span>
                                <span class="ml-2 text-xs font-bold tabular-nums"
                                    :class="b.arah === 'naik' ? 'text-emerald-600'
                                        : (b.arah === 'turun' ? 'text-red-600' : 'text-gray-400')">
                                    {{ b.selisih > 0 ? '+' : '' }}{{ b.selisih }}
                                </span>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="warnaSebab(b.sebab)">{{ b.label_sebab }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-500 max-w-xs">{{ b.alasan ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-xs text-gray-500">{{ b.oleh }}</td>
                    </tr>
                    <tr v-if="!baris.length">
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">
                            Belum ada pergerakan skor pada periode &amp; saringan ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    baris: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    periodeOpsi: { type: Array, default: () => [] },
    sebabOpsi: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const guruId = ref(props.filters.guru_id ?? null)
const sebab = ref(props.filters.sebab ?? '')
const arah = ref(props.filters.arah ?? '')
const autoSegar = ref(false)
const jamMuat = ref('')

const arahOpsi = [
    { nilai: '', label: 'Semua' },
    { nilai: 'naik', label: 'Naik' },
    { nilai: 'turun', label: 'Turun' },
    { nilai: 'awal', label: 'Awal' },
]

const periodeNilai = computed(() => `${props.periode.tahun}-${props.periode.bulan}`)

function kueri() {
    return {
        bulan: props.periode.bulan,
        tahun: props.periode.tahun,
        guru_id: guruId.value || undefined,
        sebab: sebab.value || undefined,
        arah: arah.value || undefined,
    }
}

function reload(extra = {}) {
    router.get(route('admin.smart-payroll.kinerja.log-aktivitas'), { ...kueri(), ...extra },
        { preserveState: true, preserveScroll: true, replace: true })
}

function pilihPeriode(val) {
    const [tahun, bulan] = String(val).split('-')
    reload({ bulan, tahun })
}
function pilihArah(val) { arah.value = val; reload() }
function hapusSaringan() {
    guruId.value = null; sebab.value = ''; arah.value = ''
    reload()
}

const urlPdf = computed(() => {
    const q = new URLSearchParams()
    q.set('bulan', props.periode.bulan)
    q.set('tahun', props.periode.tahun)
    if (guruId.value) q.set('guru_id', guruId.value)
    if (sebab.value) q.set('sebab', sebab.value)
    return route('admin.smart-payroll.kinerja.log-aktivitas.pdf') + '?' + q.toString()
})

function warnaSebab(s) {
    return {
        awal: 'bg-gray-100 text-gray-600',
        hitung_ulang: 'bg-blue-50 text-blue-700',
        reset: 'bg-amber-50 text-amber-700',
        reset_semua: 'bg-amber-50 text-amber-700',
        override: 'bg-violet-50 text-violet-700',
        finalisasi: 'bg-emerald-50 text-emerald-700',
        catatan: 'bg-gray-100 text-gray-600',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

// "Realtime" secukupnya: muat ulang berkala, bukan websocket. Hanya menukar
// prop lewat Inertia, jadi saringan & posisi gulir tidak hilang.
let timer = null
function mulaiTimer() {
    hentikanTimer()
    timer = setInterval(() => reload(), 30000)
}
function hentikanTimer() {
    if (timer) { clearInterval(timer); timer = null }
}

watch(autoSegar, (nyala) => (nyala ? mulaiTimer() : hentikanTimer()))
onMounted(() => { jamMuat.value = new Date().toLocaleTimeString('id-ID') })
onBeforeUnmount(hentikanTimer)
</script>
