<template>
    <AdminLayout title="Data Gaji" subtitle="Smart Payroll">

        <Head title="Data Gaji" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Data Gaji</h2>
                <p class="text-sm text-gray-400 mt-0.5">Pilih periode untuk melihat dan mengelola data penggajian</p>
            </div>
            <Link :href="route('admin.smart-payroll.periode.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Periode
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <Link v-for="p in periodes.data" :key="p.id" :href="route('admin.smart-payroll.penggajian.detail', p.id)"
                :class="[
                    'block bg-white rounded-2xl border transition-all hover:shadow-md',
                    p.status === 'dibayar' ? 'border-emerald-200' : p.status === 'selesai' ? 'border-amber-200' : 'border-gray-200 hover:border-indigo-200'
                ]">
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ p.nama }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ p.tanggal_mulai }} – {{ p.tanggal_selesai }}</p>
                        </div>
                        <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', badgeStatus(p.status).class]">
                            {{ badgeStatus(p.status).label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                            <p class="text-xs text-gray-400">Guru</p>
                            <p class="text-lg font-bold text-gray-800 mt-0.5">{{ p.jumlah_guru }}</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl px-3 py-2.5">
                            <p class="text-xs text-indigo-500">Total Gaji</p>
                            <p class="text-sm font-bold text-indigo-700 mt-0.5">
                                {{ p.total_gaji > 0 ? formatRp(p.total_gaji) : '—' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-gray-50 flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        {{ p.dikunci_pada ? `Dikunci: ${p.dikunci_pada}` : 'Belum dikunci' }}
                    </p>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </Link>
        </div>

        <div v-if="!periodes.data?.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">Belum ada periode penggajian.</p>
            <Link :href="route('admin.smart-payroll.periode.create')"
                class="inline-block mt-3 text-sm text-indigo-600 font-medium hover:underline">
                Buat periode pertama →
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    periodes: { type: Object, default: () => ({ data: [] }) },
})

function badgeStatus(s) {
    return {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600' },
        selesai: { label: 'Selesai', class: 'bg-amber-50 text-amber-700' },
        dibayar: { label: 'Dibayar', class: 'bg-emerald-50 text-emerald-700' },
        proses: { label: 'Proses', class: 'bg-blue-50 text-blue-700' },
    }[s] ?? { label: s, class: 'bg-gray-100 text-gray-600' }
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>