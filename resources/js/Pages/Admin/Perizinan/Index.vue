<template>
    <AdminLayout title="Perizinan Santri" subtitle="Kesiswaan">
        <Head title="Perizinan Santri" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Perizinan Santri</h2>
            <p class="text-sm text-gray-400 mt-0.5">Tunjuk guru sebagai Petugas Perizinan & pantau izin. Persetujuan dilakukan petugas via aplikasi guru.</p>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                <p class="text-2xl font-bold text-gray-800">{{ ringkasan.petugas }}</p>
                <p class="text-xs text-gray-400">Petugas Perizinan</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                <p class="text-2xl font-bold text-amber-600">{{ ringkasan.diajukan }}</p>
                <p class="text-xs text-amber-500">Menunggu Persetujuan</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <p class="text-2xl font-bold text-emerald-600">{{ ringkasan.disetujui }}</p>
                <p class="text-xs text-emerald-500">Disetujui</p>
            </div>
        </div>

        <!-- Petugas -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 mb-5">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-sm font-bold text-gray-800">👮 Petugas Perizinan</h3>
                <button @click="simpanPetugas"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                    Simpan Petugas
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-3">Centang guru yang diberi akses menyetujui izin santri (bisa lebih dari satu — salah satu menyetujui sudah sah).</p>
            <div class="flex flex-wrap gap-2">
                <label v-for="g in guru" :key="g.id"
                    :class="['flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs cursor-pointer transition-colors',
                        ids.includes(g.id) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                    <input type="checkbox" :value="g.id" v-model="ids" class="w-3.5 h-3.5 rounded text-indigo-600" />
                    {{ g.nama }}
                </label>
            </div>
        </div>

        <!-- Monitor izin -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-bold text-gray-800">Monitor Izin</h3>
                <div class="flex gap-1">
                    <button v-for="s in tabs" :key="s.v" @click="setStatus(s.v)"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold',
                            filterStatus === s.v ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                        {{ s.label }}
                    </button>
                </div>
            </div>
            <div v-if="!izin.length" class="p-8 text-center text-sm text-gray-400">Tidak ada data.</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-400 text-xs">
                    <tr>
                        <th class="text-left px-5 py-2 font-medium">Santri</th>
                        <th class="text-left px-3 py-2 font-medium">Jenis</th>
                        <th class="text-left px-3 py-2 font-medium">Alasan</th>
                        <th class="text-left px-3 py-2 font-medium">Tanggal</th>
                        <th class="text-left px-3 py-2 font-medium">Status</th>
                        <th class="text-left px-5 py-2 font-medium">Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in izin" :key="i.id" class="border-t border-gray-50 hover:bg-gray-50/60">
                        <td class="px-5 py-2.5 font-medium text-gray-800">{{ i.santri }}</td>
                        <td class="px-3 py-2.5">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', i.jenis === 'syari' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600']">{{ i.jenis_label }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-gray-500 text-xs max-w-[200px] truncate">{{ i.alasan }}</td>
                        <td class="px-3 py-2.5 text-gray-600 text-xs">{{ i.tanggal }}</td>
                        <td class="px-3 py-2.5">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', badge(i.status)]">{{ label(i.status) }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-gray-500 text-xs">{{ i.petugas || (i.diajukan ? 'diajukan ' + i.diajukan : '—') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guru:         { type: Array, default: () => [] },
    izin:         { type: Array, default: () => [] },
    filterStatus: { type: String, default: 'all' },
    ringkasan:    { type: Object, default: () => ({}) },
})

const ids = ref(props.guru.filter(g => g.is_petugas).map(g => g.id))
const tabs = [
    { v: 'diajukan', label: 'Menunggu' },
    { v: 'disetujui', label: 'Disetujui' },
    { v: 'ditolak', label: 'Ditolak' },
    { v: 'selesai', label: 'Selesai' },
    { v: 'all', label: 'Semua' },
]

function simpanPetugas() {
    router.post(route('admin.perizinan.petugas'), { petugas_ids: ids.value }, { preserveScroll: true })
}
function setStatus(v) { router.get(route('admin.perizinan.index'), { status: v }, { preserveState: false }) }

function label(s) { return { diajukan: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak', selesai: 'Selesai', dibatalkan: 'Dibatalkan' }[s] ?? s }
function badge(s) {
    return { diajukan: 'bg-amber-50 text-amber-600', disetujui: 'bg-emerald-50 text-emerald-600',
             ditolak: 'bg-red-50 text-red-500', selesai: 'bg-indigo-50 text-indigo-600',
             dibatalkan: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500'
}
</script>
