<template>
    <AdminLayout>

        <!-- HEADER + PERIODE -->
        <div class="no-print mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Laporan Penggajian</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    Slip gaji per guru untuk satu periode — klik kartu untuk membuka & mencetak slip.
                </p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Periode Penggajian</label>
                <select v-model.number="periodeId" @change="applyPeriode"
                    class="px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200 min-w-[180px]">
                    <option v-if="!periodeOptions.length" :value="null">Belum ada periode</option>
                    <option v-for="p in periodeOptions" :key="p.id" :value="p.id">{{ p.label }}</option>
                </select>
            </div>
        </div>

        <!-- RINGKASAN INSTANSI -->
        <div v-if="periode" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Jumlah Guru</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.total_guru }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Total Penerimaan</div>
                <div class="text-base font-bold text-gray-800 mt-1.5 tabular-nums">{{ rupiah(stats.total_pendapatan) }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Total Potongan</div>
                <div class="text-base font-bold text-red-600 mt-1.5 tabular-nums">{{ rupiah(stats.total_potongan) }}</div>
            </div>
            <div class="bg-emerald-50 rounded-2xl border border-emerald-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-emerald-500 uppercase tracking-wide">Total Gaji Bersih</div>
                <div class="text-base font-extrabold text-emerald-700 mt-1.5 tabular-nums">{{ rupiah(stats.total_bersih) }}</div>
            </div>
        </div>

        <!-- GRID KARTU SLIP -->
        <div v-if="penggajian.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="row in penggajian" :key="row.id"
                :href="route('admin.smart-payroll.laporan.slip-gaji', row.id)"
                class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:border-indigo-300 hover:shadow-md transition-all">
                <div class="flex items-center gap-3">
                    <img v-if="row.foto" :src="row.foto" class="w-11 h-11 rounded-full object-cover" />
                    <div v-else
                        class="w-11 h-11 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600">
                        {{ row.nama?.charAt(0) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-gray-800 truncate group-hover:text-indigo-600">{{ row.nama }}</div>
                        <div class="text-xs text-gray-400 truncate">{{ row.jabatan }}</div>
                    </div>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border shrink-0"
                        :class="badgeClass(row.status)">{{ row.status_badge?.label ?? row.status }}</span>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-50 flex items-end justify-between">
                    <div class="text-xs text-gray-400 space-y-0.5">
                        <div>Penerimaan: <span class="text-gray-600">{{ rupiah(row.total_pendapatan) }}</span></div>
                        <div>Potongan: <span class="text-red-500">{{ rupiah(row.total_potongan) }}</span></div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Diterima</div>
                        <div class="text-base font-bold text-emerald-700 tabular-nums">{{ rupiah(row.gaji_bersih) }}</div>
                    </div>
                </div>
            </Link>
        </div>

        <!-- EMPTY -->
        <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M9 14l2 2 4-4m1 7H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="font-semibold text-gray-700">Belum ada data penggajian</h3>
            <p class="text-sm text-gray-400 mt-1">
                {{ periode ? 'Periode ini belum memiliki slip gaji yang digenerate.' : 'Pilih periode penggajian terlebih dahulu.' }}
            </p>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    periodeOptions: { type: Array, default: () => [] },
    periode: { type: Object, default: null },
    penggajian: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const periodeId = ref(props.periode?.id ?? props.filters.periode_id ?? null)

function applyPeriode() {
    router.get(route('admin.smart-payroll.laporan.penggajian'), { periode_id: periodeId.value },
        { preserveState: true, preserveScroll: true, replace: true })
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

function badgeClass(status) {
    return {
        draft: 'bg-gray-50 text-gray-500 border-gray-200',
        final: 'bg-amber-50 text-amber-700 border-amber-200',
        dibayar: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    }[status] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
</script>
