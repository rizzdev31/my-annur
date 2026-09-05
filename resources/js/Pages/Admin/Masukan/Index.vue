<template>
    <AdminLayout title="Saran & Masukan" subtitle="Komunikasi">

        <Head title="Saran & Masukan" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Saran & Masukan</h2>
            <p class="text-sm text-gray-400 mt-0.5">
                Keluhan, laporan bug, dan usulan dari pengguna sistem — dikirim lewat PWA. Balas langsung di percakapan.
            </p>
        </div>

        <!-- Ringkasan: yang menuntut tindakan lebih dulu -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="rounded-2xl bg-sky-50 border border-sky-100 px-4 py-3">
                <p class="text-2xl font-bold text-sky-700">{{ ringkasan.baru }}</p>
                <p class="text-xs text-sky-600 font-medium">Belum ditangani</p>
            </div>
            <div class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3">
                <p class="text-2xl font-bold text-amber-700">{{ ringkasan.diproses }}</p>
                <p class="text-xs text-amber-600 font-medium">Sedang diproses</p>
            </div>
            <div class="rounded-2xl bg-red-50 border border-red-100 px-4 py-3">
                <p class="text-2xl font-bold text-red-700">{{ ringkasan.bug }}</p>
                <p class="text-xs text-red-600 font-medium">Bug belum tuntas</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <input v-model="f.cari" @keyup.enter="terapkan" type="text" placeholder="Cari judul / pelapor…"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 w-64" />
            <select v-model="f.status" @change="terapkan" class="px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none">
                <option value="semua">Semua status</option>
                <option v-for="(l, k) in opsi.status" :key="k" :value="k">{{ l }}</option>
            </select>
            <select v-model="f.kategori" @change="terapkan" class="px-3 py-2 rounded-xl border border-gray-200 text-sm outline-none">
                <option value="semua">Semua jenis</option>
                <option v-for="(l, k) in opsi.kategori" :key="k" :value="k">{{ l }}</option>
            </select>
            <button @click="terapkan" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                Terapkan
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-4 py-2.5 text-left">Masukan</th>
                    <th class="px-3 py-2.5 text-left">Pelapor</th>
                    <th class="px-3 py-2.5 text-left">Jenis</th>
                    <th class="px-3 py-2.5 text-center">Status</th>
                    <th class="px-3 py-2.5 text-left">Terakhir</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="m in masukan.data" :key="m.id" @click="buka(m.id)"
                        class="hover:bg-gray-50/70 cursor-pointer">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span v-if="m.belum_dibaca" class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-800 truncate">{{ m.judul }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ m.cuplikan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-gray-600">{{ m.pelapor }}</td>
                        <td class="px-3 py-3">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', warnaKategori(m.kategori)]">
                                {{ m.kategori_label }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', warnaStatus(m.status)]">
                                {{ m.status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-400">{{ m.waktu }}</td>
                    </tr>
                    <tr v-if="!masukan.data.length">
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">
                            Belum ada masukan yang cocok dengan filter ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="masukan.links?.length > 3" class="flex flex-wrap gap-1 mt-4">
            <Link v-for="(l, i) in masukan.links" :key="i" :href="l.url || ''"
                :class="['px-3 py-1.5 rounded-lg text-sm border',
                    l.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200',
                    !l.url ? 'opacity-40 pointer-events-none' : '']" v-html="l.label" />
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    masukan: Object,
    filter: Object,
    opsi: Object,
    ringkasan: Object,
})

const f = ref({
    cari: props.filter.cari ?? '',
    status: props.filter.status ?? 'semua',
    kategori: props.filter.kategori ?? 'semua',
})

const terapkan = () => router.get(route('admin.masukan.index'), f.value, { preserveState: true, replace: true })
const buka = (id) => router.visit(route('admin.masukan.show', id))

const warnaStatus = (s) => ({
    baru: 'bg-sky-50 text-sky-700',
    diproses: 'bg-amber-50 text-amber-700',
    selesai: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-500')

const warnaKategori = (k) => ({
    bug: 'bg-red-50 text-red-600',
    saran: 'bg-violet-50 text-violet-600',
    pertanyaan: 'bg-sky-50 text-sky-600',
    lainnya: 'bg-gray-100 text-gray-500',
}[k] ?? 'bg-gray-100 text-gray-500')
</script>
