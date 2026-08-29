<template>
    <AdminLayout title="Monitoring Tahsin" subtitle="Smart Education">

        <Head title="Monitoring Tahsin" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Monitoring Tahsin</h2>
            <p class="text-sm text-gray-400 mt-0.5">Pantau progres level & penilaian materi santri per kelas tahsin.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Santri</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.jumlah_santri }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Siap Naik Level</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.level_selesai }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kelas Tahsin</label>
                <select v-model="kelasId" :class="fieldCls">
                    <option :value="null">Pilih kelas...</option>
                    <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}<span v-if="k.level_tahsin"> (Lv {{ k.level_tahsin }})</span></option>
                </select>
            </div>
            <button @click="terapkan" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">Tampilkan</button>
        </div>

        <div v-if="!kelas" class="bg-white rounded-2xl border border-dashed border-gray-300 py-16 text-center">
            <p class="text-sm text-gray-500">Pilih kelas tahsin untuk melihat progres santri.</p>
        </div>

        <div v-else class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Santri</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Level</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Progres Materi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in santri" :key="s.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ s.nama }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ s.nip }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="px-2 py-0.5 rounded-lg bg-violet-50 text-violet-700 text-xs font-bold">{{ s.level === 6 ? 'Persiapan' : 'Lv ' + s.level }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-semibold text-gray-700">{{ s.materi_lulus }} / {{ s.materi_total }} materi lulus</p>
                            <div class="w-28 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                <div class="h-full bg-violet-500 rounded-full" :style="{ width: pct(s) + '%' }"></div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span v-if="s.level_selesai" class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-medium">Siap naik level</span>
                            <span v-else class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">Belajar</span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('admin.smart-education.tahsin-monitoring.detail', s.id)"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">Lihat</Link>
                        </td>
                    </tr>
                    <tr v-if="!santri.length"><td colspan="5" class="py-14 text-center text-sm text-gray-400">Belum ada santri di kelas ini.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    kelas: { type: Object, default: null },
    kelasOpsi: { type: Array, default: () => [] },
    santri: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const kelasId = ref(props.kelas?.id ?? null)
const pct = (s) => s.materi_total > 0 ? Math.round(s.materi_lulus / s.materi_total * 100) : 0
function terapkan() {
    router.get(route('admin.smart-education.tahsin-monitoring.index'), { kelas_id: kelasId.value },
        { preserveState: true, preserveScroll: true })
}
const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>
