<template>
    <AdminLayout title="Sesi Mengajar Kosong" subtitle="Smart Payroll">

        <Head title="Sesi Mengajar Kosong" />

        <div class="flex items-start justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Sesi Mengajar Kosong</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Sesi yang <strong>tidak terlaksana</strong> atau tidak pernah dicatat, dalam satu
                    rentang tanggal — untuk diperiksa sebelum penggajian.
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.absensi.mengajar')"
                class="shrink-0 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm text-gray-600 hover:bg-gray-50">
                ← Absensi Harian
            </Link>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dari tanggal</label>
                    <input v-model="f.dari" type="date" :class="inputCls" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sampai tanggal</label>
                    <input v-model="f.sampai" type="date" :class="inputCls" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jenjang</label>
                    <select v-model="f.jenjang" :class="inputCls">
                        <option value="semua">Semua jenjang</option>
                        <option value="smp">SMP (kelas VII–IX)</option>
                        <option value="sma">SMA/MA (kelas X–XII)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Program</label>
                    <select v-model="f.program" :class="inputCls">
                        <option value="reguler">Reguler (non-Qur'an)</option>
                        <option value="quran">Tahfidz &amp; Tahsin</option>
                        <option value="semua">Semua program</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Guru</label>
                    <select v-model="f.guru_id" :class="inputCls">
                        <option :value="null">Semua guru</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button @click="terapkan" :disabled="memuat"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                    {{ memuat ? 'Memuat…' : 'Tampilkan' }}
                </button>
                <span class="text-xs text-gray-400">Cepat:</span>
                <button v-for="c in cepat" :key="c.label" @click="pakaiCepat(c)"
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs text-gray-600 hover:bg-gray-50">
                    {{ c.label }}
                </button>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-gray-500">Terjadwal</p>
                <p class="text-lg font-bold text-gray-700">{{ ringkasan.terjadwal }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-gray-500">Terlaksana</p>
                <p class="text-lg font-bold text-emerald-600">{{ ringkasan.terlaksana }}</p>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-red-600">KOSONG</p>
                <p class="text-lg font-bold text-red-700">{{ ringkasan.kosong }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-gray-500">Izin / Dialihkan</p>
                <p class="text-lg font-bold text-gray-600">{{ ringkasan.izin }} / {{ ringkasan.digantikan }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-gray-500">Guru terdampak</p>
                <p class="text-lg font-bold text-amber-600">{{ ringkasan.guru_terdampak }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl py-2.5 text-center">
                <p class="text-[11px] text-gray-500">Keterlaksanaan</p>
                <p class="text-lg font-bold" :class="persenCls">{{ persenLabel }}</p>
            </div>
        </div>

        <!-- Penjelasan cara hitung: angka gaji tidak boleh jadi kotak hitam -->
        <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 mb-5">
            <strong>Kosong</strong> = sesi milik guru sendiri yang <strong>tidak terlaksana</strong>
            ({{ ringkasan.tidak_terlaksana }}) atau <strong>tanpa catatan sama sekali</strong>
            ({{ ringkasan.tanpa_catatan }}). Hari libur, libur pembelajaran, jadwal yang belum dibuat,
            dan sesi yang belum lewat batas absen <strong>tidak</strong> dihitung. Sesi yang dialihkan
            ke guru pengganti bersifat netral bagi guru asli
            <span v-if="ringkasan.inval_tidak_datang">— pengganti yang tidak datang:
                <strong>{{ ringkasan.inval_tidak_datang }}</strong> sesi</span>.
        </p>

        <div v-if="!kosong.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">Tidak ada sesi kosong pada rentang & saringan ini.</p>
        </div>

        <div v-else class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <!-- Rekap per guru -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Per Guru</h3>
                    <p class="text-xs text-gray-400">{{ perGuru.length }} guru · urut terbanyak</p>
                </div>
                <ul class="divide-y divide-gray-50 max-h-[70vh] overflow-y-auto">
                    <li v-for="g in perGuru" :key="g.guru_id" class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <button @click="saringGuru(g.guru_id)"
                                class="text-sm text-left font-medium text-gray-800 hover:text-indigo-600 truncate">
                                {{ g.guru }}
                            </button>
                            <span class="shrink-0 px-2 py-0.5 rounded-lg bg-red-50 text-red-700 text-xs font-bold">
                                {{ g.jumlah }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ ringkasTanggal(g.tanggal) }}</p>
                    </li>
                </ul>
            </div>

            <!-- Daftar sesi kosong -->
            <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Daftar Sesi Kosong</h3>
                        <p class="text-xs text-gray-400">{{ kosong.length }} sesi · klik tanggal untuk membuka harinya</p>
                    </div>
                    <button @click="salin" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs text-gray-600 hover:bg-gray-50">
                        {{ tersalin ? 'Tersalin ✓' : 'Salin daftar' }}
                    </button>
                </div>
                <div class="overflow-x-auto max-h-[70vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-xs text-gray-500">
                                <th class="px-3 py-2.5">Tanggal</th>
                                <th class="px-3 py-2.5">Guru</th>
                                <th class="px-3 py-2.5">Kelas</th>
                                <th class="px-3 py-2.5 hidden md:table-cell">Mapel</th>
                                <th class="px-3 py-2.5">Jam</th>
                                <th class="px-3 py-2.5">Sebab</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(s, i) in kosong" :key="i" class="hover:bg-gray-50/60">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <Link :href="route('admin.smart-payroll.absensi.mengajar', { tanggal: s.tanggal })"
                                        class="font-medium text-indigo-600 hover:underline">
                                        {{ tglSingkat(s.tanggal) }}
                                    </Link>
                                </td>
                                <td class="px-3 py-2">{{ s.guru }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{ s.kelas }}
                                    <span v-if="s.tingkat" class="text-[10px] text-gray-400">({{ s.tingkat }})</span>
                                </td>
                                <td class="px-3 py-2 hidden md:table-cell text-xs text-gray-600">{{ s.mapel }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">{{ s.jam }}</td>
                                <td class="px-3 py-2 text-xs">
                                    <span class="px-1.5 py-0.5 rounded font-semibold"
                                        :class="s.kategori === 'tanpa_catatan' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700'">
                                        {{ s.kategori === 'tanpa_catatan' ? 'tanpa catatan' : 'tidak terlaksana' }}
                                    </span>
                                    <span v-if="s.sebab" class="block text-[11px] text-gray-500 mt-0.5">{{ s.sebab }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    ringkasan: { type: Object, default: () => ({}) },
    kosong: { type: Array, default: () => [] },
    perGuru: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
})

const inputCls = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'

const f = reactive({
    dari: props.filter.dari ?? '',
    sampai: props.filter.sampai ?? '',
    jenjang: props.filter.jenjang ?? 'semua',
    program: props.filter.program ?? 'reguler',
    guru_id: props.filter.guru_id ?? null,
})
const memuat = ref(false)
const tersalin = ref(false)

// Rentang yang sering dipakai saat memeriksa gaji. Tanggal 1–22 & 5–17 adalah
// rentang periksa yang dipakai pesantren (jenjang bisa berbeda rentangnya).
const bulanIni = (d) => {
    const t = props.filter.dari ? new Date(props.filter.dari + 'T00:00:00') : new Date()
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}
const cepat = [
    { label: 'Tgl 1–22', dari: 1, sampai: 22, jenjang: null },
    { label: 'Tgl 5–17 (SMP)', dari: 5, sampai: 17, jenjang: 'smp' },
    { label: 'Bulan penuh', dari: 1, sampai: 31, jenjang: null },
]
function pakaiCepat(c) {
    f.dari = bulanIni(c.dari)
    f.sampai = bulanIni(c.sampai)
    if (c.jenjang) f.jenjang = c.jenjang
    terapkan()
}

function terapkan() {
    memuat.value = true
    router.get(route('admin.smart-payroll.absensi.mengajar-kosong'), { ...f }, {
        preserveState: true, preserveScroll: true,
        onFinish: () => { memuat.value = false },
    })
}
function saringGuru(id) { f.guru_id = f.guru_id === id ? null : id; terapkan() }

const persenLabel = computed(() =>
    props.ringkasan.persen_terlaksana === null || props.ringkasan.persen_terlaksana === undefined
        ? '—' : props.ringkasan.persen_terlaksana + '%')
const persenCls = computed(() => {
    const p = props.ringkasan.persen_terlaksana
    if (p === null || p === undefined) return 'text-gray-400'
    return p >= 90 ? 'text-emerald-600' : p >= 75 ? 'text-amber-600' : 'text-red-600'
})

const tglSingkat = (t) => new Date(t + 'T00:00:00')
    .toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })

// Tanggal dirangkum per bulan supaya baris per guru tetap pendek tapi lengkap.
function ringkasTanggal(list) {
    if (!list?.length) return '—'
    const peta = new Map()
    list.forEach((t) => {
        const d = new Date(t + 'T00:00:00')
        const b = d.toLocaleDateString('id-ID', { month: 'short' })
        if (!peta.has(b)) peta.set(b, [])
        peta.get(b).push(d.getDate())
    })
    return [...peta.entries()]
        .map(([b, h]) => `${[...new Set(h)].sort((a, z) => a - z).join(', ')} ${b}`)
        .join(' · ')
}

// Daftar teks untuk ditempel ke WhatsApp/notula rapat penggajian.
function salin() {
    const baris = props.kosong.map((s) =>
        `${s.tanggal} · ${s.guru} · ${s.kelas} · ${s.mapel} · ${s.jam} · ` +
        `${s.kategori === 'tanpa_catatan' ? 'tanpa catatan' : 'tidak terlaksana'}`)
    const teks = [
        `Sesi mengajar kosong ${props.filter.dari} s.d. ${props.filter.sampai}`,
        `Jenjang: ${props.filter.jenjang} · Program: ${props.filter.program}`,
        `Total: ${props.ringkasan.kosong} sesi · ${props.ringkasan.guru_terdampak} guru`,
        '', ...baris,
    ].join('\n')
    navigator.clipboard?.writeText(teks).then(() => {
        tersalin.value = true
        setTimeout(() => { tersalin.value = false }, 2000)
    }).catch(() => {})
}
</script>
