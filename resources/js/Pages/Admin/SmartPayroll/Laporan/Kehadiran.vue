<template>
    <AdminLayout>

        <!-- HEADER -->
        <div class="no-print mb-5">
            <h1 class="text-xl font-bold text-gray-800">Laporan Kehadiran</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                Rekap kehadiran per guru — pilih guru dan periode (harian, mingguan, atau bulanan).
            </p>
        </div>

        <!-- FILTER -->
        <LaporanFilterBar :guru-list="guruList" :filters="filters"
            route-name="admin.smart-payroll.laporan.kehadiran" :can-print="!!laporan" />

        <!-- LAPORAN / EMPTY STATE -->
        <KehadiranReport v-if="laporan" :guru="laporan.guru" :periode-label="laporan.periode.label"
            :rows="laporan.rows" :ringkasan="laporan.ringkasan" />

        <div v-else
            class="no-print bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <h3 class="font-semibold text-gray-700">Belum ada guru dipilih</h3>
            <p class="text-sm text-gray-400 mt-1">Pilih guru pada panel di atas untuk menampilkan laporan kehadiran.</p>
        </div>

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import LaporanFilterBar from './Partials/LaporanFilterBar.vue'
import KehadiranReport from './Partials/KehadiranReport.vue'

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
