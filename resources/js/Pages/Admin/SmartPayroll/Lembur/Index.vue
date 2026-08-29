<template>
    <AdminLayout>
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Lembur</h1>
                <p class="text-sm text-gray-400 mt-0.5">Kelola pengajuan & lembur kolektif per jabatan.</p>
            </div>
            <div class="flex items-end gap-2">
                <select v-model.number="form.bulan" @change="apply"
                    class="px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white">
                    <option v-for="(b, i) in bulanList" :key="i" :value="i + 1">{{ b }}</option>
                </select>
                <select v-model.number="form.tahun" @change="apply"
                    class="px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white w-24">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>
                <Link :href="route('admin.smart-payroll.lembur.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700">
                    + Lembur Kolektif
                </Link>
            </div>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase">Total</div>
                <div class="text-2xl font-bold text-gray-800 mt-0.5">{{ stats.total }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase">Diajukan</div>
                <div class="text-2xl font-bold text-amber-600 mt-0.5">{{ stats.diajukan }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] font-semibold text-gray-400 uppercase">Disetujui</div>
                <div class="text-2xl font-bold text-indigo-600 mt-0.5">{{ stats.disetujui }}</div>
            </div>
            <div class="rounded-2xl border shadow-sm p-4"
                :class="stats.perlu_tindakan > 0 ? 'bg-red-50 border-red-200' : 'bg-white border-gray-100'">
                <div class="text-[11px] font-semibold uppercase" :class="stats.perlu_tindakan > 0 ? 'text-red-500' : 'text-gray-400'">Perlu Tindakan</div>
                <div class="text-2xl font-bold mt-0.5" :class="stats.perlu_tindakan > 0 ? 'text-red-600' : 'text-gray-800'">{{ stats.perlu_tindakan }}</div>
            </div>
        </div>

        <!-- LIST -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Lembur</th>
                            <th class="px-4 py-3">Jabatan</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Jam</th>
                            <th class="px-4 py-3 text-center">Sumber</th>
                            <th class="px-4 py-3 text-center">Peserta</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="l in lembur" :key="l.id" @click="open(l.id)"
                            class="hover:bg-indigo-50/40 cursor-pointer">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ l.judul }}</div>
                                <div v-if="l.perlu_tindakan > 0" class="text-[11px] text-red-600 font-medium mt-0.5">
                                    ⚠ {{ l.perlu_tindakan }} peserta terlewat — perlu tindakan
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ l.jabatan }}</td>
                            <td class="px-4 py-3 text-gray-600 tabular-nums">{{ l.tanggal }}</td>
                            <td class="px-4 py-3 text-gray-500 tabular-nums">{{ l.jam }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold border"
                                    :class="l.sumber_input === 'kolektif' ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-gray-50 text-gray-600 border-gray-200'">
                                    {{ l.sumber_input === 'kolektif' ? 'Kolektif' : 'Mandiri' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums">
                                <span class="text-emerald-600 font-bold">{{ l.total_sah }}</span>
                                <span class="text-gray-300">/{{ l.total_peserta }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold border" :class="statusCls(l.status)">
                                    {{ statusLabel(l.status) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!lembur.length">
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">Belum ada lembur pada periode ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    lembur: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
})

const bulanList = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const tahunList = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const form = ref({
    bulan: Number(props.filters.bulan ?? new Date().getMonth() + 1),
    tahun: Number(props.filters.tahun ?? new Date().getFullYear()),
})

function apply() {
    router.get(route('admin.smart-payroll.lembur.index'), form.value, { preserveState: true, replace: true })
}
function open(id) {
    router.get(route('admin.smart-payroll.lembur.show', id))
}
function statusLabel(s) {
    return { diajukan: 'Diajukan', disetujui: 'Disetujui', ditolak: 'Ditolak', dibatalkan: 'Dibatalkan' }[s] ?? s
}
function statusCls(s) {
    return {
        diajukan: 'bg-amber-50 text-amber-700 border-amber-200',
        disetujui: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        ditolak: 'bg-red-50 text-red-700 border-red-200',
        dibatalkan: 'bg-gray-50 text-gray-500 border-gray-200',
    }[s] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
</script>
