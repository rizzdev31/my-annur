<template>
    <AdminLayout title="Detail Harian" subtitle="Smart Controlling">

        <Head title="Detail Absensi Harian" />

        <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Detail Absensi Harian</h2>
                <p class="text-sm text-gray-400 mt-0.5">Siapa saja Hadir / Telat / Alpha pada satu sesi + status kirim RamahAnak.</p>
            </div>
            <div class="flex items-end gap-2">
                <input v-model="f.tanggal" type="date" @change="apply" :class="inp" />
                <select v-model.number="f.kegiatan_id" @change="apply" :class="inp">
                    <option :value="null">Semua kegiatan</option>
                    <option v-for="k in kegiatanOpsi" :key="k.id" :value="k.id">{{ k.nama }} ({{ k.jenis }})</option>
                </select>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-gray-900">{{ ringkasan.total }}</p><p class="text-xs text-gray-400 mt-1">Total</p></div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-emerald-700">{{ ringkasan.hadir }}</p><p class="text-xs text-emerald-500 mt-1">Hadir</p></div>
            <div class="bg-amber-50 rounded-xl border border-amber-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-amber-700">{{ ringkasan.telat }}</p><p class="text-xs text-amber-500 mt-1">Telat</p></div>
            <div class="bg-red-50 rounded-xl border border-red-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-red-600">{{ ringkasan.alpha }}</p><p class="text-xs text-red-400 mt-1">Alpha</p></div>
            <div class="bg-sky-50 rounded-xl border border-sky-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-sky-700">{{ ringkasan.izin ?? 0 }}</p><p class="text-xs text-sky-500 mt-1">Izin</p></div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100"><p class="text-sm font-semibold text-gray-800">Daftar Absensi · {{ tanggal }}</p></div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-3 py-2 text-left">Santri</th>
                    <th class="px-3 py-2 text-left">Kegiatan</th>
                    <th class="px-2 py-2 text-center">Status</th>
                    <th class="px-2 py-2 text-center">Jam</th>
                    <th class="px-3 py-2 text-left">Petugas</th>
                    <th class="px-2 py-2 text-center">RamahAnak</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="(r, i) in rows" :key="i" class="hover:bg-gray-50/40">
                        <td class="px-3 py-2"><p class="font-semibold text-gray-700">{{ r.nama }}</p><span class="text-[10px] text-gray-400 font-mono">{{ r.nip }}</span></td>
                        <td class="px-3 py-2 text-gray-600">{{ r.kegiatan }}</td>
                        <td class="px-2 py-2 text-center"><span :class="badge(r.status)">{{ r.status }}</span></td>
                        <td class="px-2 py-2 text-center text-gray-500">{{ r.jam_scan || '—' }}</td>
                        <td class="px-3 py-2 text-gray-500 text-xs">{{ r.petugas || '—' }}</td>
                        <td class="px-2 py-2 text-center">
                            <span v-if="r.status === 'hadir' || r.status === 'izin'" class="text-[10px] text-gray-300">—</span>
                            <span v-else :class="r.terkirim ? 'text-emerald-600' : 'text-amber-500'" class="text-xs font-semibold">
                                {{ r.terkirim ? '✓ terkirim' : '⏳ antri' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="6" class="py-12 text-center text-gray-400 text-sm">Belum ada absensi pada tanggal/kegiatan ini.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    tanggal: { type: String, default: '' },
    kegiatanId: { type: Number, default: null },
    kegiatanOpsi: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const f = reactive({ tanggal: props.tanggal, kegiatan_id: props.kegiatanId })
const apply = () => router.get(route('admin.smart-habbit.controlling.harian'), { ...f }, { preserveState: true, preserveScroll: true })

const badge = (s) => ({
    hadir: 'text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700',
    telat: 'text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-700',
    alpha: 'text-xs font-semibold px-2 py-0.5 rounded bg-red-50 text-red-600',
    izin: 'text-xs font-semibold px-2 py-0.5 rounded bg-sky-50 text-sky-700',
}[s] ?? 'text-xs text-gray-500')

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>
