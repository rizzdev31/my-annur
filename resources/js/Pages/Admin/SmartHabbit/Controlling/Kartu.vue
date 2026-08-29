<template>
    <AdminLayout title="Kartu Barcode" subtitle="Smart Controlling">

        <Head title="Kartu Barcode Santri" />

        <div class="print:hidden flex items-center justify-between mb-5 gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kartu Barcode Santri</h2>
                <p class="text-sm text-gray-400 mt-0.5">Barcode = NIP (Code128). Cetak & tempel di kartu santri untuk scan absensi.</p>
            </div>
            <div class="flex items-center gap-2">
                <input v-model="q" @keyup.enter="cari" placeholder="Cari nama / NIP..."
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white w-52" />
                <button @click="cari" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cari</button>
                <button @click="cetak" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Cetak</button>
            </div>
        </div>

        <div id="kartu-cetak" class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="s in santri" :key="s.id"
                class="border border-gray-300 rounded-xl p-3 flex flex-col items-center bg-white break-inside-avoid">
                <p class="text-sm font-bold text-gray-900 text-center leading-tight">{{ s.nama }}</p>
                <p class="text-xs text-gray-500 mb-2 font-mono">{{ s.nip }}</p>
                <img :src="route('admin.smart-habbit.controlling.barcode', s.nip)" :alt="s.nip"
                    class="h-14 w-full object-contain" />
            </div>
            <p v-if="!santri.length" class="col-span-full text-center text-sm text-gray-400 py-12">Tidak ada santri.</p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    santri: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
})

const q = ref(props.filter.q ?? '')
const cari = () => router.get(route('admin.smart-habbit.controlling.kartu'), { q: q.value }, { preserveState: true, preserveScroll: true })
const cetak = () => window.print()
</script>

<style scoped>
@media print {
    #kartu-cetak { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    :deep(*) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
