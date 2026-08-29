<template>
    <AdminLayout title="Multi Jabatan Guru" subtitle="Master Data">

        <Head title="Multi Jabatan Guru" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Multi Jabatan Guru</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Lihat dan kelola jabatan setiap tenaga pendidik — termasuk guru dengan rangkap jabatan
                </p>
            </div>
            <Link :href="route('admin.master.jabatan.index')"
                class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Daftar Jabatan
            </Link>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-lg shrink-0">👤</div>
                <div>
                    <p class="text-xl font-bold text-gray-900">{{ stats.total_guru }}</p>
                    <p class="text-xs text-gray-400">Total Guru Aktif</p>
                </div>
            </div>
            <div class="bg-amber-50 rounded-xl border border-amber-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center text-lg shrink-0">🔀</div>
                <div>
                    <p class="text-xl font-bold text-amber-700">{{ stats.rangkap }}</p>
                    <p class="text-xs text-amber-600">Rangkap Jabatan</p>
                </div>
            </div>
            <div class="bg-red-50 rounded-xl border border-red-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center text-lg shrink-0">⚠️</div>
                <div>
                    <p class="text-xl font-bold text-red-600">{{ stats.tanpa_jabatan }}</p>
                    <p class="text-xs text-red-500">Tanpa Jabatan</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-4">
            <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" :class="[
                'px-4 py-2 rounded-xl text-sm font-medium transition-colors border',
                activeTab === t.key
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'
            ]">
                {{ t.label }}
                <span v-if="t.key === 'rangkap'"
                    class="ml-1.5 px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-xs">
                    {{ stats.rangkap }}
                </span>
            </button>

            <!-- Search -->
            <div class="ml-auto relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input v-model="search" type="text" placeholder="Cari nama guru..." @input="doSearch"
                    class="pl-10 pr-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white w-52" />
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Guru</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Jabatan Aktif</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Jumlah</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="g in guruFiltered" :key="g.id"
                        :class="['hover:bg-gray-50/40 transition-colors', g.is_rangkap ? 'bg-amber-50/10' : '']">

                        <!-- Guru -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <img v-if="g.foto" :src="g.foto" class="w-8 h-8 rounded-full object-cover shrink-0" />
                                <div v-else
                                    class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-bold text-indigo-700">{{ g.nama?.charAt(0) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ g.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ g.nip || '—' }}</p>
                                </div>
                            </div>
                        </td>

                        <!-- Jabatan badges -->
                        <td class="px-5 py-3.5">
                            <div v-if="g.jabatan_aktif?.length" class="flex flex-wrap gap-1.5">
                                <span v-for="j in g.jabatan_aktif" :key="j.pivot_id" :class="[
                                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
                                    j.adalah_utama ? badgeTipe(j.tipe).aktif : badgeTipe(j.tipe).normal
                                ]">
                                    <span v-if="j.adalah_utama"
                                        class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ j.nama_jabatan }}
                                    <span v-if="j.adalah_utama" class="opacity-60 text-xs">(U)</span>
                                </span>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 italic">{{ g.jabatan_lama }}</span>
                                <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-500">Sistem lama</span>
                            </div>
                        </td>

                        <!-- Jumlah jabatan -->
                        <td class="px-5 py-3.5 text-center">
                            <span :class="[
                                'inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold',
                                g.jumlah_jabatan > 1 ? 'bg-amber-100 text-amber-700' :
                                    g.jumlah_jabatan === 0 ? 'bg-red-100 text-red-600' :
                                        'bg-gray-100 text-gray-600'
                            ]">
                                {{ g.jumlah_jabatan || '!' }}
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-3.5 text-right">
                            <Link :href="route('admin.master.jabatan-guru.index', g.id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Setting Jabatan
                            </Link>
                        </td>
                    </tr>

                    <tr v-if="!guruFiltered.length">
                        <td colspan="4" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada data yang cocok.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Info cara kerja -->
        <div class="mt-4 p-4 bg-indigo-50 border border-indigo-100 rounded-2xl flex gap-3">
            <svg class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-xs text-indigo-700 space-y-1">
                <p><strong>Jabatan Utama (U)</strong> — jabatan primer yang menjadi referensi gaji pokok pertama.</p>
                <p><strong>Rangkap Jabatan</strong> — guru dengan lebih dari 1 jabatan aktif. Gaji pokok dari semua
                    jabatan dijumlahkan.</p>
                <p><strong>Sistem lama</strong> — guru yang belum dimigrasi ke sistem multi jabatan. Klik "Setting
                    Jabatan" untuk mulai.</p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guru: { type: Object, default: () => ({ data: [] }) },
    jabatan: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const activeTab = ref('semua')
const search = ref(props.filters.search ?? '')
let searchTimer = null

const tabs = [
    { key: 'semua', label: 'Semua Guru' },
    { key: 'rangkap', label: 'Rangkap Jabatan' },
    { key: 'tanpa_jabatan', label: 'Tanpa Jabatan Pivot' },
]

const guruFiltered = computed(() => {
    let list = props.guru.data ?? []
    if (activeTab.value === 'rangkap') list = list.filter(g => g.is_rangkap)
    if (activeTab.value === 'tanpa_jabatan') list = list.filter(g => !g.jabatan_aktif?.length)
    return list
})

function doSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('admin.master.jabatan.multi'), { search: search.value }, {
            preserveState: true, replace: true,
        })
    }, 350)
}

function badgeTipe(tipe) {
    const map = {
        struktural: { aktif: 'bg-indigo-200 text-indigo-800', normal: 'bg-indigo-50 text-indigo-600' },
        fungsional: { aktif: 'bg-violet-200 text-violet-800', normal: 'bg-violet-50 text-violet-600' },
        mengajar: { aktif: 'bg-teal-200 text-teal-800', normal: 'bg-teal-50 text-teal-600' },
    }
    return map[tipe] ?? { aktif: 'bg-gray-200 text-gray-700', normal: 'bg-gray-100 text-gray-600' }
}
</script>