<template>
    <AdminLayout title="Setting Gaji Pokok" subtitle="Pengaturan">

        <Head title="Setting Gaji Pokok" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Gaji Pokok Jabatan</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Besaran gaji pokok per jabatan — otomatis dijumlahkan jika guru rangkap jabatan
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-gaji.pokok.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Setting Baru
            </Link>
        </div>

        <!-- Info box -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-2xl px-5 py-4 mb-5 flex items-start gap-3">
            <svg class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-indigo-700">
                Guru yang merangkap jabatan akan menerima <strong>gaji pokok dari masing-masing jabatan
                    dijumlahkan</strong>.
                Bisa di-override per individu di halaman detail tenaga pendidik.
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Jabatan</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Gaji Pokok</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Berlaku Mulai</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                            Keterangan</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in settingGaji" :key="s.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-xs font-bold', bgTipe(s.jabatan_tipe)]">
                                    {{ s.jabatan_kode }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ s.jabatan_nama }}</p>
                                    <p class="text-xs text-gray-400 capitalize">{{ s.jabatan_tipe }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <p class="text-base font-bold text-indigo-700">{{ formatRp(s.nominal) }}</p>
                            <p class="text-xs text-gray-400">per bulan</p>
                        </td>
                        <td class="px-5 py-4 hidden md:table-cell">
                            <p class="text-sm text-gray-700">{{ s.berlaku_mulai }}</p>
                        </td>
                        <td class="px-5 py-4 hidden lg:table-cell">
                            <p class="text-sm text-gray-500 truncate max-w-xs">{{ s.keterangan || '—' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1">
                                <Link :href="route('admin.smart-payroll.setting-gaji.pokok.edit', s.id)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="nonaktifkan(s)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!settingGaji.length">
                        <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                            Belum ada setting gaji pokok.
                            <Link :href="route('admin.smart-payroll.setting-gaji.pokok.create')"
                                class="text-indigo-500 underline ml-1">Buat sekarang</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'

const confirm = ref(null)

const props = defineProps({
    settingGaji: { type: Array, default: () => [] },
})

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function bgTipe(t) {
    return { struktural: 'bg-indigo-100 text-indigo-700', fungsional: 'bg-violet-100 text-violet-700', mengajar: 'bg-teal-100 text-teal-700' }[t] ?? 'bg-gray-100 text-gray-700'
}

function nonaktifkan(s) {
    confirm.value.ask(
        { title: 'Nonaktifkan Gaji Pokok?', message: `Gaji pokok ${s.jabatan_nama} akan dinonaktifkan.`,
          variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' },
        (done) => router.delete(route('admin.smart-payroll.setting-gaji.pokok.destroy', s.id),
            { preserveScroll: true, onFinish: done }),
    )
}
</script>