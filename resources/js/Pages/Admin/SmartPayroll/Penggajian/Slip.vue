<template>
    <AdminLayout :title="`Slip Gaji — ${guru.nama}`" subtitle="Smart Payroll">

        <Head :title="`Slip Gaji ${guru.nama}`" />

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
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Slip Gaji</h1>
                    <p class="text-xs text-gray-400">{{ periode.label }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-medium', badgeStatus(slip.status).class]">
                    <span :class="['w-2 h-2 rounded-full', badgeStatus(slip.status).dot]"></span>
                    {{ badgeStatus(slip.status).label }}
                </span>
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

        <!-- DOKUMEN SLIP (komponen bersama dgn Laporan) -->
        <SlipGajiDocument :instansi="instansi" :guru="guru" :periode="periode" :slip="slip" :logo="logo" />

        <!-- KOREKSI MANUAL -->
        <div v-if="slip.status === 'draft' || slip.status === 'final'"
            class="no-print max-w-3xl mx-auto mt-4 bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Koreksi Manual</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Tunjangan Lainnya</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                        <input v-model.number="overrideForm.tunjangan_lainnya" type="number" min="0"
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Potongan Lainnya</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                        <input v-model.number="overrideForm.potongan_lainnya" type="number" min="0"
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Catatan Koreksi <span class="text-red-500">*</span></label>
                <input v-model="overrideForm.catatan" type="text" placeholder="Alasan koreksi manual..."
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
            </div>
            <button @click="submitOverride" :disabled="!overrideForm.catatan || overrideSaving"
                class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                {{ overrideSaving ? 'Menyimpan...' : 'Simpan Koreksi' }}
            </button>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SlipGajiDocument from '@/Components/SlipGajiDocument.vue'

const props = defineProps({
    instansi: { type: Object, default: () => ({}) },
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    slip: { type: Object, default: () => ({}) },
    logo: { type: String, default: null },
})

const overrideForm = ref({
    tunjangan_lainnya: props.slip.tunjangan_lainnya ?? 0,
    potongan_lainnya: props.slip.potongan_lainnya ?? 0,
    catatan: '',
})
const overrideSaving = ref(false)

function badgeStatus(s) {
    return {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' },
        final: { label: 'Final', class: 'bg-amber-50 text-amber-700', dot: 'bg-amber-500' },
        dibayar: { label: 'Dibayar', class: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
    }[s] ?? { label: s, class: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' }
}

function cetak() { window.print() }

function submitOverride() {
    overrideSaving.value = true
    router.patch(route('admin.smart-payroll.penggajian.override', props.slip.id), overrideForm.value, {
        onFinish: () => overrideSaving.value = false,
    })
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
