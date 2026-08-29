<template>
    <AdminLayout title="Rekap Inventaris" subtitle="Evaluasi Bulanan">
        <Head title="Rekap Inventaris" />

        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Rekap Pemakaian Inventaris</h2>
                <p class="text-sm text-gray-400 mt-0.5">Evaluasi bulanan — frekuensi & durasi pemakaian</p>
            </div>
            <div class="flex items-center gap-2">
                <select v-model.number="f.bulan" @change="reload" class="px-3 py-2 rounded-xl border border-gray-200 text-sm">
                    <option v-for="(b, i) in bulanList" :key="i" :value="i + 1">{{ b }}</option>
                </select>
                <input v-model.number="f.tahun" @change="reload" type="number" class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm" />
                <Link :href="route('admin.inventaris.index')" class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">← Kembali</Link>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                <p class="text-2xl font-bold text-gray-800">{{ ringkasan.total_peminjaman }}</p>
                <p class="text-xs text-gray-400">Total Pemakaian</p>
            </div>
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                <p class="text-2xl font-bold text-gray-800">{{ ringkasan.item_terpakai }}</p>
                <p class="text-xs text-gray-400">Item Terpakai</p>
            </div>
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                <p class="text-2xl font-bold text-gray-800">{{ ringkasan.guru_aktif }}</p>
                <p class="text-xs text-gray-400">Guru Meminjam</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Per Item</h3></div>
                <div v-if="!perItem.length" class="p-6 text-center text-sm text-gray-400">Tidak ada pemakaian.</div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs"><tr>
                        <th class="text-left px-5 py-2 font-medium">Item</th>
                        <th class="text-left px-3 py-2 font-medium">Kategori</th>
                        <th class="text-center px-3 py-2 font-medium">Frekuensi</th>
                        <th class="text-right px-5 py-2 font-medium">Total Jam</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(r, i) in perItem" :key="i" class="border-t border-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ r.nama }}</td>
                            <td class="px-3 py-2.5 capitalize text-gray-500">{{ r.kategori }}</td>
                            <td class="px-3 py-2.5 text-center">{{ r.frekuensi }}×</td>
                            <td class="px-5 py-2.5 text-right">{{ r.total_jam }} jam</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Per Guru</h3></div>
                <div v-if="!perGuru.length" class="p-6 text-center text-sm text-gray-400">Tidak ada data.</div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs"><tr>
                        <th class="text-left px-5 py-2 font-medium">Guru</th>
                        <th class="text-right px-5 py-2 font-medium">Frekuensi</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(r, i) in perGuru" :key="i" class="border-t border-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ r.nama }}</td>
                            <td class="px-5 py-2.5 text-right">{{ r.frekuensi }}×</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    bulan: Number, tahun: Number,
    perItem: { type: Array, default: () => [] },
    perGuru: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const f = reactive({ bulan: props.bulan, tahun: props.tahun })
function reload() {
    router.get(route('admin.inventaris.rekap'), { bulan: f.bulan, tahun: f.tahun }, { preserveState: false })
}
</script>
