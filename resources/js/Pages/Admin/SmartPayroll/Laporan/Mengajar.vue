<template>
    <AdminLayout>

        <!-- HEADER -->
        <div class="no-print mb-5">
            <h1 class="text-xl font-bold text-gray-800">Laporan Absensi Mengajar</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                Rekap sesi mengajar per guru beserta JP dan honor — pilih guru dan periode (harian, mingguan, atau bulanan).
            </p>
        </div>

        <!-- FILTER -->
        <LaporanFilterBar :guru-list="guruList" :filters="filters"
            route-name="admin.smart-payroll.laporan.mengajar" :can-print="!!laporan" />

        <!-- LAPORAN / EMPTY STATE -->
        <MengajarReport v-if="laporan" :guru="laporan.guru" :periode-label="laporan.periode.label"
            :rows="laporan.rows" :ringkasan="laporan.ringkasan" />

        <div v-else
            class="no-print bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <h3 class="font-semibold text-gray-700">Belum ada guru dipilih</h3>
            <p class="text-sm text-gray-400 mt-1">Pilih guru pada panel di atas untuk menampilkan laporan absensi mengajar.</p>
        </div>

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import LaporanFilterBar from './Partials/LaporanFilterBar.vue'
import MengajarReport from './Partials/MengajarReport.vue'

defineProps({
    guruList: { type: Array, default: () => [] },
    laporan: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
})
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
