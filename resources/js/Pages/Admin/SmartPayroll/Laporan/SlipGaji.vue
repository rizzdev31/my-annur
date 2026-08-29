<template>
    <AdminLayout>

        <!-- TOOLBAR -->
        <div class="no-print flex items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3">
                <button @click="$inertia.back()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold
                           text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali
                </button>
                <h1 class="text-lg font-bold text-gray-800">Slip Gaji — {{ guru.nama }}</h1>
            </div>
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

        <SlipGajiDocument :instansi="instansi" :guru="guru" :periode="periode" :slip="slip" :logo="logo" />

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SlipGajiDocument from '@/Components/SlipGajiDocument.vue'

defineProps({
    instansi: { type: Object, default: () => ({}) },
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    slip: { type: Object, default: () => ({}) },
    logo: { type: String, default: null },
})

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
