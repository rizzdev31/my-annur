<template>
    <AdminLayout title="Koreksi Absensi" subtitle="Smart Payroll">

        <Head title="Koreksi Absensi" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Koreksi & Log Absensi</h2>
                <p class="text-sm text-gray-400 mt-0.5">Riwayat seluruh koreksi absensi yang dilakukan admin</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <input v-model="fSearch" type="text" placeholder="Cari nama guru..." @input="applyFilter"
                class="px-4 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-48" />
            <select v-model="fGuru" @change="applyFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none w-44">
                <option value="">Semua Guru</option>
                <option v-for="g in guru" :key="g.id" :value="g.id">{{ g.nama }}</option>
            </select>
            <select v-model="fBulan" @change="applyFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none">
                <option v-for="(n, i) in namaBulan" :key="i" :value="i">{{ n }}</option>
            </select>
            <input v-model.number="fTahun" type="number" @change="applyFilter"
                class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none" />
        </div>

        <!-- Tabel Log -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase">Guru</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Tanggal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase">Perubahan</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                            Alasan</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                            Oleh</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="k in log.data" :key="k.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-gray-800">{{ k.nama }}</p>
                            <span
                                :class="['text-xs px-2 py-0.5 rounded-lg font-medium',
                                    k.tipe_absensi === 'harian' ? 'bg-blue-50 text-blue-700' : 'bg-teal-50 text-teal-700']">
                                {{ k.tipe_absensi }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <p class="text-sm text-gray-700">{{ k.tanggal }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <span :class="['text-xs px-2 py-1 rounded-lg font-medium', statusCls(k.nilai_lama)]">
                                    {{ k.nilai_lama ?? 'baru' }}
                                </span>
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                                <span :class="['text-xs px-2 py-1 rounded-lg font-medium', statusCls(k.nilai_baru)]">
                                    {{ k.nilai_baru }}
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 hidden lg:table-cell">
                            <p class="text-xs text-gray-500 max-w-xs truncate">{{ k.alasan }}</p>
                        </td>
                        <td class="px-5 py-3.5 hidden lg:table-cell">
                            <p class="text-xs text-gray-500">{{ k.dikoreksi_oleh }}</p>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <p class="text-xs text-gray-400">{{ k.waktu }}</p>
                        </td>
                    </tr>
                    <tr v-if="!log.data?.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">Belum ada log koreksi.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="log.last_page > 1" class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">{{ log.from }}–{{ log.to }} dari {{ log.total }}</p>
                <div class="flex gap-1">
                    <button v-for="p in log.last_page" :key="p" @click="goPage(p)"
                        :class="['w-8 h-8 rounded-lg text-xs transition-colors',
                            p === log.current_page ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    log: { type: Object, default: () => ({ data: [] }) },
    guru: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: new Date().getMonth() + 1 },
    tahun: { type: Number, default: new Date().getFullYear() },
})

const fSearch = ref(props.filters.search ?? '')
const fGuru = ref(props.filters.guru_id ?? '')
const fBulan = ref(props.bulan)
const fTahun = ref(props.tahun)

const namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

function statusCls(s) {
    return {
        hadir: 'bg-emerald-50 text-emerald-700', terlambat: 'bg-amber-50 text-amber-700',
        alfa: 'bg-red-50 text-red-600', izin: 'bg-blue-50 text-blue-700',
        sakit: 'bg-indigo-50 text-indigo-700', libur: 'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-500'
}

let searchTimer = null
function applyFilter() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('admin.smart-payroll.absensi.koreksi.index'), {
            search: fSearch.value || undefined,
            guru_id: fGuru.value || undefined,
            bulan: fBulan.value,
            tahun: fTahun.value,
        }, { preserveState: true, replace: true })
    }, 400)
}

function goPage(p) {
    router.get(route('admin.smart-payroll.absensi.koreksi.index'), {
        page: p, search: fSearch.value, guru_id: fGuru.value, bulan: fBulan.value, tahun: fTahun.value,
    })
}
</script>