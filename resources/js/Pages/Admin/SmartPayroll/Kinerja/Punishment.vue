<template>
    <AdminLayout title="Punishment Kinerja" subtitle="Smart Payroll">
        <Head title="Punishment Kinerja" />

        <!-- Header + periode -->
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Punishment Kinerja</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    Rekap tindakan atas kinerja bulanan — potongan, evaluasi, peringatan, pencopotan.
                </p>
            </div>
            <div class="flex gap-2">
                <select v-model.number="f.bulan" @change="apply"
                    class="px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                    <option v-for="(b, i) in bulanList" :key="i" :value="i + 1">{{ b }}</option>
                </select>
                <select v-model.number="f.tahun" @change="apply"
                    class="px-3 py-2.5 rounded-xl text-sm border border-gray-200 bg-white focus:ring-2 focus:ring-indigo-200">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Total</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Evaluasi</div>
                <div class="text-2xl font-bold text-blue-600 mt-1">{{ stats.evaluasi }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Peringatan</div>
                <div class="text-2xl font-bold text-amber-600 mt-1">{{ stats.peringatan }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Pencopotan</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">{{ stats.pencopotan }}</div>
            </div>
            <div class="bg-red-50 rounded-2xl border border-red-100 shadow-sm p-4">
                <div class="text-[11px] uppercase tracking-wide text-red-500">Total Potongan</div>
                <div class="text-base font-extrabold text-red-700 mt-1.5 tabular-nums">{{ rupiah(stats.total_potongan) }}</div>
            </div>
        </div>

        <!-- Daftar -->
        <div v-if="punishments.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3">Guru</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                        <th class="px-4 py-3">Oleh</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in punishments" :key="p.id" class="hover:bg-gray-50/60">
                        <td class="px-5 py-3">
                            <Link :href="route('admin.smart-payroll.kinerja.detail-guru', { guru: p.guru_id, bulan, tahun })"
                                class="flex items-center gap-2.5 group">
                                <img v-if="p.foto" :src="p.foto" class="w-8 h-8 rounded-full object-cover" />
                                <div v-else class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                    {{ p.guru?.charAt(0) }}
                                </div>
                                <span class="font-medium text-gray-800 group-hover:text-indigo-600">{{ p.guru }}</span>
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border" :class="jenisCls(p.jenis)">
                                {{ p.jenis_label }}
                            </span>
                            <span v-if="p.jabatan" class="block text-[11px] text-gray-400 mt-0.5">{{ p.jabatan }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs">
                            <p class="truncate">{{ p.catatan }}</p>
                            <span v-if="p.skor != null" class="text-[11px] text-gray-400">skor saat itu: {{ p.skor }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums" :class="p.nominal ? 'text-red-600 font-semibold' : 'text-gray-300'">
                            {{ p.nominal ? rupiah(p.nominal) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ p.oleh }}<br>{{ p.tanggal }}</td>
                        <td class="px-4 py-3 text-right">
                            <button @click="hapus(p)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 py-16 px-6 text-center">
            <h3 class="font-semibold text-gray-700">Belum ada punishment</h3>
            <p class="text-sm text-gray-400 mt-1">
                Beri punishment lewat <b>Rekap Kinerja → detail guru</b>.
            </p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    punishments: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: 1 },
    tahun: { type: Number, default: 2026 },
    filters: { type: Object, default: () => ({}) },
})

const bulanList = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const tahunList = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const f = ref({ bulan: props.bulan, tahun: props.tahun })

function apply() {
    router.get(route('admin.smart-payroll.kinerja.punishment.index'), f.value,
        { preserveState: true, preserveScroll: true, replace: true })
}
async function hapus(p) {
    if (!(await confirm({ title: 'Hapus punishment ini?', variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.smart-payroll.kinerja.punishment.destroy', p.id), { preserveScroll: true })
}
function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function jenisCls(j) {
    return { potongan: 'bg-red-50 text-red-700 border-red-200', evaluasi: 'bg-blue-50 text-blue-700 border-blue-200',
        peringatan: 'bg-amber-50 text-amber-700 border-amber-200', pencopotan: 'bg-gray-800 text-white border-gray-800' }[j]
        ?? 'bg-gray-50 text-gray-600 border-gray-200'
}
</script>
