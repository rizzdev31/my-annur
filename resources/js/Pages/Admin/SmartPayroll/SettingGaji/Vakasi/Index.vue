<template>
    <AdminLayout title="Setting Vakasi" subtitle="Pengaturan">

        <Head title="Setting Vakasi" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Vakasi</h2>
                <p class="text-sm text-gray-400 mt-0.5">Konfigurasi tunjangan berdasarkan aktivitas tenaga pendidik</p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-gaji.vakasi.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Setting Baru
            </Link>
        </div>

        <!-- Info vakasi mengajar -->
        <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 mb-5 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <div class="text-sm text-amber-700">
                <p class="font-medium">Vakasi Mengajar per JP</p>
                <p class="text-amber-600 mt-0.5 text-xs">
                    Setiap jam pelajaran (JP) yang terlaksana akan dikalkulasi dengan nominal vakasi ini.
                    Bisa berbeda per jabatan atau berlaku untuk semua. Total vakasi mengajar = JP × nominal.
                </p>
            </div>
        </div>

        <!-- Grouped by tipe aktivitas -->
        <div v-for="(items, tipe) in vakasiByTipe" :key="tipe" class="mb-5">
            <div class="flex items-center gap-3 mb-3">
                <span :class="['px-3 py-1 rounded-lg text-xs font-semibold', badgeTipe(tipe).class]">
                    {{ badgeTipe(tipe).label }}
                </span>
                <p class="text-xs text-gray-400">{{ items.length }} setting</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Nama</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                                Berlaku Untuk</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Nominal</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden sm:table-cell">
                                Satuan</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="v in items" :key="v.id" class="hover:bg-gray-50/40 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="text-sm font-medium text-gray-800">{{ v.nama }}</p>
                                <p class="text-xs text-gray-400">berlaku mulai {{ v.berlaku_mulai }}</p>
                            </td>
                            <td class="px-5 py-3.5 hidden md:table-cell">
                                <span v-if="v.berlaku_untuk_semua"
                                    class="text-xs px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-medium">
                                    Semua jabatan
                                </span>
                                <div v-else class="flex flex-wrap gap-1">
                                    <span v-for="jn in v.jabatan_names" :key="jn"
                                        class="text-xs px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700">
                                        {{ jn }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <p class="text-sm font-bold text-indigo-700">{{ formatRp(v.nominal) }}</p>
                            </td>
                            <td class="px-5 py-3.5 hidden sm:table-cell">
                                <span :class="['text-xs px-2 py-1 rounded-lg font-medium', badgeSatuan(v.satuan)]">
                                    {{ labelSatuan[v.satuan] || v.satuan }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1">
                                    <Link :href="route('admin.smart-payroll.setting-gaji.vakasi.edit', v.id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button @click="nonaktifkan(v)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="!vakasi.length"
            class="bg-white rounded-2xl border border-gray-200 py-14 text-center text-sm text-gray-400">
            Belum ada setting vakasi.
        </div>
        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'

const confirm = ref(null)

const props = defineProps({
    vakasi: { type: Array, default: () => [] },
    jabatan: { type: Array, default: () => [] },
})

const vakasiByTipe = computed(() => {
    const g = {}
    props.vakasi.forEach(v => {
        if (!g[v.tipe_aktivitas]) g[v.tipe_aktivitas] = []
        g[v.tipe_aktivitas].push({
            ...v,
            jabatan_names: (v.jabatan_ids ?? []).map(id =>
                props.jabatan.find(j => j.id === id)?.nama_jabatan ?? `#${id}`
            ),
        })
    })
    return g
})

function badgeTipe(t) {
    return {
        absen_harian: { label: 'Kehadiran Harian', class: 'bg-blue-50 text-blue-700' },
        absen_mengajar: { label: 'Mengajar (per JP)', class: 'bg-amber-50 text-amber-700' },
        tugas_jabatan: { label: 'Tugas Jabatan', class: 'bg-violet-50 text-violet-700' },
        tugas_tambahan: { label: 'Tugas Tambahan', class: 'bg-teal-50 text-teal-700' },
        lembur: { label: 'Lembur', class: 'bg-orange-50 text-orange-700' },
        piket: { label: 'Guru Piket', class: 'bg-amber-50 text-amber-700' },
        tasmi: { label: "Tasmi' (Tahfidz)", class: 'bg-emerald-50 text-emerald-700' },
    }[t] ?? { label: t, class: 'bg-gray-100 text-gray-600' }
}

function badgeSatuan(s) {
    return {
        per_hari: 'bg-blue-50 text-blue-700',
        per_jp: 'bg-amber-50 text-amber-700',
        per_tugas: 'bg-teal-50 text-teal-700',
        per_jam: 'bg-indigo-50 text-indigo-700',
        per_bulan: 'bg-violet-50 text-violet-700',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

const labelSatuan = {
    per_hari: '/hari', per_jp: '/JP', per_tugas: '/tugas',
    per_jam: '/jam', per_bulan: '/bulan',
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function nonaktifkan(v) {
    confirm.value.ask(
        { title: 'Nonaktifkan Vakasi?', message: `Setting "${v.nama}" akan dinonaktifkan.`,
          variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' },
        (done) => router.delete(route('admin.smart-payroll.setting-gaji.vakasi.destroy', v.id),
            { preserveScroll: true, onFinish: done }),
    )
}
</script>