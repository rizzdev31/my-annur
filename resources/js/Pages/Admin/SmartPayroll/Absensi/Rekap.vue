<template>
    <AdminLayout title="Rekap Absensi" subtitle="Smart Payroll">

        <Head title="Rekap Absensi" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Rekap Absensi Bulanan</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ namaBulan[bulan] }} {{ tahun }} · {{ total_hari_kerja }} hari kerja efektif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <select v-model="fBulan" @change="applyFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none">
                    <option v-for="(n, i) in namaBulan" :key="i" :value="i">{{ n }}</option>
                </select>
                <input v-model.number="fTahun" type="number" @change="applyFilter"
                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none" />
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-lg shrink-0">👥</div>
                <div>
                    <p class="text-xl font-bold text-gray-900">{{ summary.total_guru }}</p>
                    <p class="text-xs text-gray-400">Total Guru</p>
                </div>
            </div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center text-lg shrink-0">📅
                </div>
                <div>
                    <p class="text-xl font-bold text-emerald-700">{{ summary.avg_hadir }}</p>
                    <p class="text-xs text-emerald-500">Rata-rata Hadir</p>
                </div>
            </div>
            <div class="bg-red-50 rounded-xl border border-red-100 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center text-lg shrink-0">⚠️</div>
                <div>
                    <p class="text-xl font-bold text-red-600">{{ summary.total_alfa }}</p>
                    <p class="text-xs text-red-400">Total Alfa</p>
                </div>
            </div>
            <div class="bg-teal-50 rounded-xl border border-teal-100 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-teal-100 flex items-center justify-center text-lg shrink-0">📚</div>
                <div>
                    <p class="text-xl font-bold text-teal-700">{{ summary.total_jp }}</p>
                    <p class="text-xs text-teal-400">Total JP</p>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex items-center gap-2 mb-4">
            <input v-model="fSearch" type="text" placeholder="Cari nama guru..."
                class="px-4 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-52" />
            <select v-model="fJabatan" @change="applyFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none">
                <option value="">Semua Jabatan</option>
                <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
            </select>
        </div>

        <!-- Tabel Rekap -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase sticky left-0 bg-gray-50/50">
                            Guru</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-emerald-400 uppercase">Hadir</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-amber-400 uppercase">Terlambat</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-blue-400 uppercase">Izin</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-indigo-400 uppercase">Sakit</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-red-400 uppercase">Alfa</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Libur</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-teal-400 uppercase">JP</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-violet-400 uppercase">Tugas</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-400 uppercase">% Hadir</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in rekapFiltered" :key="r.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-4 py-3.5 sticky left-0 bg-white">
                            <p class="text-sm font-semibold text-gray-800">{{ r.nama }}</p>
                            <p class="text-xs text-gray-400">{{ r.jabatan }}</p>
                        </td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm font-bold text-emerald-700">{{ r.hadir
                                }}</span></td>
                        <td class="px-3 py-3.5 text-center"><span
                                :class="['text-sm font-bold', r.terlambat > 0 ? 'text-amber-600' : 'text-gray-300']">{{
                                r.terlambat }}</span></td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm text-blue-600">{{ r.izin }}</span></td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm text-indigo-600">{{ r.sakit }}</span>
                        </td>
                        <td class="px-3 py-3.5 text-center"><span
                                :class="['text-sm font-bold', r.alfa > 0 ? 'text-red-600' : 'text-gray-300']">{{ r.alfa
                                }}</span></td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm text-gray-400">{{ r.libur }}</span>
                        </td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm font-bold text-teal-700">{{
                                r.jp_mengajar }}</span></td>
                        <td class="px-3 py-3.5 text-center"><span class="text-sm text-violet-700">{{ r.tugas_selesai
                                }}</span></td>
                        <td class="px-3 py-3.5 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <span
                                    :class="['text-xs font-bold', r.pct_hadir >= 90 ? 'text-emerald-600' : r.pct_hadir >= 75 ? 'text-amber-600' : 'text-red-600']">
                                    {{ r.pct_hadir }}%
                                </span>
                                <div class="w-14 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div :style="{ width: r.pct_hadir + '%' }"
                                        :class="['h-full rounded-full', r.pct_hadir >= 90 ? 'bg-emerald-500' : r.pct_hadir >= 75 ? 'bg-amber-500' : 'bg-red-500']" />
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-right">
                            <a :href="route('admin.smart-payroll.monitoring.detail', r.id) + `?bulan=${bulan}&tahun=${tahun}`"
                                class="text-xs text-indigo-600 hover:underline">
                                Lihat →
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    rekap: { type: Array, default: () => [] },
    bulan: { type: Number, default: new Date().getMonth() + 1 },
    tahun: { type: Number, default: new Date().getFullYear() },
    total_hari_kerja: { type: Number, default: 0 },
    jabatan: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

const fBulan = ref(props.bulan)
const fTahun = ref(props.tahun)
const fSearch = ref(props.filters.search ?? '')
const fJabatan = ref(props.filters.jabatan_id ?? '')

const namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const rekapFiltered = computed(() => {
    let list = props.rekap
    if (fSearch.value) list = list.filter(r => r.nama.toLowerCase().includes(fSearch.value.toLowerCase()))
    return list
})

function applyFilter() {
    router.get(route('admin.smart-payroll.absensi.rekap'), {
        bulan: fBulan.value, tahun: fTahun.value,
        jabatan_id: fJabatan.value || undefined,
    })
}
</script>