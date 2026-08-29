<template>
    <AdminLayout title="Setting Jenis Pengajuan" subtitle="Smart Payroll">

        <Head title="Setting Jenis Pengajuan" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Jenis Pengajuan</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Konfigurasi jenis izin yang dapat diajukan tenaga pendidik
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-pengajuan.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Jenis
            </Link>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Jenis</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Aturan</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                            Pengaruh Gaji</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">
                            Integrasi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in settings" :key="s.id"
                        :class="['hover:bg-gray-50/50 transition-colors', !s.is_aktif ? 'opacity-60' : '']">

                        <!-- Jenis -->
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="['w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0', bgKategori(s.kategori)]">
                                    {{ ikonKategori[s.kategori] }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ s.nama }}</p>
                                    <p class="text-xs text-gray-400 font-mono mt-0.5">{{ s.kode }}</p>
                                </div>
                            </div>
                        </td>

                        <!-- Aturan -->
                        <td class="px-5 py-4 hidden md:table-cell">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-1.5 text-xs text-gray-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 shrink-0"></span>
                                    Maks. <strong class="text-gray-800 ml-1">{{ s.max_hari_per_pengajuan }}
                                        hari</strong>/pengajuan
                                </div>
                                <div v-if="s.kuota_per_tahun" class="flex items-center gap-1.5 text-xs text-gray-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 shrink-0"></span>
                                    Kuota <strong class="text-gray-800 ml-1">{{ s.kuota_per_tahun }} hari</strong>/tahun
                                </div>
                                <div v-else class="flex items-center gap-1.5 text-xs text-gray-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 shrink-0"></span>
                                    Kuota tidak terbatas
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span v-if="s.auto_approve"
                                        class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-medium">
                                        Auto approve
                                    </span>
                                    <span v-if="s.butuh_dokumen"
                                        class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-xs font-medium">
                                        Butuh dokumen
                                    </span>
                                    <span v-if="s.min_hari_pengajuan_sebelumnya > 0"
                                        class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-xs font-medium">
                                        H-{{ s.min_hari_pengajuan_sebelumnya }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Pengaruh Gaji -->
                        <td class="px-5 py-4 hidden lg:table-cell">
                            <span
                                :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium', badgePengaruhGaji(s.pengaruh_gaji).class]">
                                {{ badgePengaruhGaji(s.pengaruh_gaji).label }}
                            </span>
                            <p v-if="s.pengaruh_gaji === 'potong_sebagian' && s.persen_potongan"
                                class="text-xs text-gray-400 mt-1">
                                {{ s.persen_potongan }}% dipotong
                            </p>
                        </td>

                        <!-- Integrasi Status Kepegawaian -->
                        <td class="px-5 py-4 hidden xl:table-cell">
                            <div v-if="s.update_status_kepegawaian"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-violet-50 border border-violet-100">
                                <svg class="w-3 h-3 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span class="text-xs text-violet-700 font-medium">
                                    → {{ s.status_kepegawaian_tujuan }}
                                    <span class="font-normal text-violet-500">≥{{ s.min_hari_untuk_update_status
                                        }}h</span>
                                </span>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>

                        <!-- Status -->
                        <td class="px-5 py-4">
                            <span :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium',
                                s.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span
                                    :class="['w-1.5 h-1.5 rounded-full', s.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ s.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <button @click="toggleAktif(s)" :class="[
                                    'p-2 rounded-lg transition-colors text-gray-400',
                                    s.is_aktif
                                        ? 'hover:text-red-500 hover:bg-red-50'
                                        : 'hover:text-emerald-600 hover:bg-emerald-50'
                                ]" :title="s.is_aktif ? 'Nonaktifkan' : 'Aktifkan'">
                                    <svg v-if="s.is_aktif" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <Link :href="route('admin.smart-payroll.setting-pengajuan.edit', s.id)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                    title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-if="!settings.length">
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div
                                    class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center text-2xl">
                                    ⚙️</div>
                                <p class="text-sm text-gray-500">Belum ada jenis pengajuan</p>
                                <Link :href="route('admin.smart-payroll.setting-pengajuan.create')"
                                    class="text-xs font-medium text-indigo-600 hover:underline">
                                    Tambah sekarang →
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Info baris -->
        <p class="text-xs text-gray-400 mt-3 px-1">
            {{settings.filter(s => s.is_aktif).length}} aktif dari {{ settings.length }} total jenis pengajuan
        </p>

    </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    settings: { type: Array, default: () => [] },
})

// ── Kategori ──────────────────────────────────────────────────────────────────
const ikonKategori = { sakit: '🏥', izin: '✋', cuti: '🏖️', dinas: '🚗' }

function bgKategori(k) {
    return { sakit: 'bg-blue-50', izin: 'bg-amber-50', cuti: 'bg-violet-50', dinas: 'bg-indigo-50' }[k] ?? 'bg-gray-50'
}

// ── Pengaruh gaji badge ───────────────────────────────────────────────────────
function badgePengaruhGaji(val) {
    return {
        tidak_potong: { label: 'Tidak dipotong', class: 'bg-emerald-50 text-emerald-700' },
        potong_absensi: { label: 'Potong vakasi', class: 'bg-amber-50 text-amber-700' },
        potong_sebagian: { label: 'Potong sebagian', class: 'bg-orange-50 text-orange-700' },
        potong_penuh: { label: 'Potong penuh', class: 'bg-red-50 text-red-700' },
    }[val] ?? { label: val, class: 'bg-gray-100 text-gray-600' }
}

// ── Toggle aktif ──────────────────────────────────────────────────────────────
function toggleAktif(s) {
    router.patch(route('admin.smart-payroll.setting-pengajuan.toggle-aktif', s.id), {}, {
        preserveScroll: true,
    })
}
</script>