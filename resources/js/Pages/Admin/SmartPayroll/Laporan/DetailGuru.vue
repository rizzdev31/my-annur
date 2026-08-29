<template>
    <AdminLayout>

        <!-- TOOLBAR (disembunyikan saat print) -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3">
                <Link :href="route('admin.smart-payroll.laporan.kehadiran', { mode: 'bulanan', bulan, tahun, guru_id: guru.id })"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold
                           text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali
                </Link>
                <h1 class="text-lg font-bold text-gray-800">Laporan Kehadiran</h1>
            </div>

            <div class="flex items-center gap-2">
                <select v-model.number="form.bulan" @change="applyFilter"
                    class="px-3 py-2 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                    <option v-for="(b, i) in bulanList" :key="i" :value="i + 1">{{ b }}</option>
                </select>
                <select v-model.number="form.tahun" @change="applyFilter"
                    class="px-3 py-2 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>
                <button @click="cetak"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                           text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak
                </button>
            </div>
        </div>

        <KehadiranReport :guru="guru" :periode-label="periode.nama_bulan" :rows="rows" :ringkasan="ringkasan" />

    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import KehadiranReport from './Partials/KehadiranReport.vue'

const props = defineProps({
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: () => new Date().getMonth() + 1 },
    tahun: { type: Number, default: () => new Date().getFullYear() },
    filters: { type: Object, default: () => ({}) },
})

const bulanList = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunList = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const form = ref({
    bulan: Number(props.filters.bulan ?? props.bulan),
    tahun: Number(props.filters.tahun ?? props.tahun),
})

function applyFilter() {
    router.get(route('admin.smart-payroll.laporan.detail-guru', { guru: props.guru.id }), {
        bulan: form.value.bulan,
        tahun: form.value.tahun,
    }, { preserveState: true, replace: true })
}

function cetak() {
    window.print()
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
