<template>
    <AdminLayout title="Sanggah Penilaian" subtitle="Guru Piket">

        <Head title="Sanggah Penilaian Piket" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Sanggah Penilaian Piket</h2>
            <p class="text-sm text-gray-400 mt-0.5">Tinjau keberatan guru atas penilaian piket. "Diterima" → penilaian dibatalkan & tidak dihitung di kinerja.</p>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-amber-50 rounded-xl border border-amber-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-amber-700">{{ ringkasan.diajukan }}</p><p class="text-xs text-amber-500 mt-1">Menunggu</p></div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-emerald-700">{{ ringkasan.diterima }}</p><p class="text-xs text-emerald-500 mt-1">Diterima</p></div>
            <div class="bg-gray-50 rounded-xl border border-gray-200 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-gray-600">{{ ringkasan.ditolak }}</p><p class="text-xs text-gray-400 mt-1">Ditolak</p></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-3 py-2.5 text-left">Guru Dinilai</th>
                    <th class="px-3 py-2.5 text-left">Penilaian</th>
                    <th class="px-3 py-2.5 text-left">Alasan Sanggah</th>
                    <th class="px-3 py-2.5 text-center">Status</th>
                    <th class="px-3 py-2.5 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50/40 align-top">
                        <td class="px-3 py-2.5">
                            <p class="font-semibold text-gray-700">{{ r.guru_dinilai }}</p>
                            <p class="text-[10px] text-gray-400">oleh piket: {{ r.piket }} · {{ r.tanggal }}</p>
                        </td>
                        <td class="px-3 py-2.5">
                            <span :class="['text-xs font-semibold px-2 py-0.5 rounded', r.jenis==='apresiasi' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600']">{{ r.kategori }} ({{ r.poin }})</span>
                            <p v-if="r.catatan" class="text-[11px] text-gray-400 mt-1">{{ r.catatan }}</p>
                        </td>
                        <td class="px-3 py-2.5 text-gray-600 max-w-xs">
                            <p>{{ r.alasan_sanggah || '—' }}</p>
                            <p v-if="r.catatan_tinjauan" class="text-[11px] text-indigo-500 mt-1">Tinjauan: {{ r.catatan_tinjauan }}</p>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span :class="badge(r.status_sanggah)">{{ labelStatus(r.status_sanggah) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <template v-if="r.status_sanggah === 'diajukan'">
                                <button @click="tinjau(r, 'diterima')" class="text-xs font-semibold text-emerald-600 hover:underline mr-3">Terima</button>
                                <button @click="tinjau(r, 'ditolak')" class="text-xs font-semibold text-red-500 hover:underline">Tolak</button>
                            </template>
                            <span v-else class="text-[10px] text-gray-300">selesai</span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="py-12 text-center text-gray-400 text-sm">Belum ada sanggahan.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ rows: { type: Array, default: () => [] }, ringkasan: { type: Object, default: () => ({}) } })

const labelStatus = (s) => ({ diajukan: 'Menunggu', diterima: 'Diterima', ditolak: 'Ditolak' }[s] ?? s)
const badge = (s) => ({
    diajukan: 'text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-700',
    diterima: 'text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700',
    ditolak: 'text-xs font-semibold px-2 py-0.5 rounded bg-gray-100 text-gray-500',
}[s] ?? 'text-xs text-gray-500')

function tinjau(r, keputusan) {
    const catatan = window.prompt(`${keputusan === 'diterima' ? 'TERIMA' : 'TOLAK'} sanggahan ${r.guru_dinilai}. Catatan tinjauan (opsional):`, '')
    if (catatan === null) return // batal
    router.post(route('admin.piket.sanggah.tinjau', r.id), { keputusan, catatan }, { preserveScroll: true })
}
</script>
