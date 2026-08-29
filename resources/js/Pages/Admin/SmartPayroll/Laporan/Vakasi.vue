<template>
    <AdminLayout>

        <!-- HEADER + PERIODE -->
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Laporan Vakasi</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    Rincian honor vakasi per guru untuk satu periode — klik kartu untuk laporan transparan.
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

        <template v-if="periode">
            <!-- RINGKASAN KOMPONEN -->
            <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-5">
                <div class="bg-indigo-600 rounded-2xl shadow-sm p-4 col-span-2 xl:col-span-1">
                    <div class="text-[11px] font-semibold text-indigo-200 uppercase tracking-wide">Total Vakasi</div>
                    <div class="text-base font-extrabold text-white mt-1.5 tabular-nums">{{ rupiah(stats.grand_total) }}</div>
                    <div class="text-[11px] text-indigo-200 mt-0.5">{{ stats.total_guru }} guru menerima</div>
                </div>
                <div v-for="s in componentStats" :key="s.label"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">{{ s.label }}</div>
                    <div class="text-sm font-bold text-gray-800 mt-1.5 tabular-nums">{{ rupiah(s.value) }}</div>
                </div>
            </div>

            <!-- GRID KARTU -->
            <div v-if="vakasi.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="row in vakasi" :key="row.id"
                    :href="route('admin.smart-payroll.laporan.vakasi-detail', row.id)"
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
                        <div class="text-right shrink-0">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Total</div>
                            <div class="text-base font-bold text-indigo-700 tabular-nums">{{ rupiah(row.total_vakasi) }}</div>
                        </div>
                    </div>

                    <!-- chip komponen yang > 0 -->
                    <div class="mt-3 pt-3 border-t border-gray-50 flex flex-wrap gap-1.5">
                        <span v-for="c in komponen(row)" :key="c.label"
                            class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-50 text-gray-500 border border-gray-100">
                            {{ c.label }}: <b class="text-gray-700">{{ rupiah(c.value) }}</b>
                        </span>
                        <span v-if="!komponen(row).length" class="text-[11px] text-gray-300 italic">Tidak ada vakasi</span>
                    </div>
                </Link>
            </div>

            <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
                <h3 class="font-semibold text-gray-700">Belum ada data</h3>
                <p class="text-sm text-gray-400 mt-1">Periode ini belum memiliki penggajian yang digenerate.</p>
            </div>
        </template>

        <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
            <h3 class="font-semibold text-gray-700">Belum ada periode penggajian</h3>
            <p class="text-sm text-gray-400 mt-1">Generate penggajian terlebih dahulu untuk melihat laporan vakasi.</p>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    periodeOptions: { type: Array, default: () => [] },
    periode: { type: Object, default: null },
    vakasi: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const periodeId = ref(props.periode?.id ?? props.filters.periode_id ?? null)

const componentStats = computed(() => [
    { label: 'Kehadiran', value: props.stats.kehadiran },
    { label: 'Mengajar', value: props.stats.mengajar },
    { label: 'Tugas Jabatan', value: props.stats.tugas_jabatan },
    { label: 'Tugas Tambahan', value: props.stats.tugas_tambahan },
    { label: 'Peserta Kegiatan', value: props.stats.peserta_kegiatan },
    { label: 'Lembur', value: props.stats.lembur },
])

function applyPeriode() {
    router.get(route('admin.smart-payroll.laporan.vakasi'), { periode_id: periodeId.value },
        { preserveState: true, preserveScroll: true, replace: true })
}

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}

function komponen(row) {
    return [
        { label: 'Kehadiran', value: row.kehadiran },
        { label: 'Mengajar', value: row.mengajar },
        { label: 'Tugas Jabatan', value: row.tugas_jabatan },
        { label: 'Tugas Tambahan', value: row.tugas_tambahan },
        { label: 'Kegiatan', value: row.peserta_kegiatan },
        { label: 'Lembur', value: row.lembur },
    ].filter(c => c.value > 0)
}
</script>
