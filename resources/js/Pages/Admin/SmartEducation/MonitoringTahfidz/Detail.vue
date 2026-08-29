<template>
    <AdminLayout :title="santri.nama" subtitle="Monitoring Tahfidz">

        <Head :title="`Tahfidz — ${santri.nama}`" />

        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-education.tahfidz-monitoring.index')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ santri.nama }}</h2>
                <p class="text-sm text-gray-400">NIP {{ santri.nip }}
                    <span v-if="santri.tahsin_level"> · Tahsin Lv {{ santri.tahsin_level }}</span>
                </p>
            </div>
        </div>

        <!-- Ringkasan hafalan -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <div class="flex items-end justify-between mb-2">
                <div>
                    <p class="text-xs text-gray-400">Total Hafalan</p>
                    <p class="text-3xl font-bold text-gray-900">{{ status.total_ayat }}
                        <span class="text-base font-medium text-gray-400">/ {{ status.total_ayat_quran }} ayat</span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-emerald-600">{{ status.persen }}%</p>
                    <p class="text-xs text-gray-400">{{ status.juz_selesai_total }} juz selesai</p>
                </div>
            </div>
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full"
                    :style="{ width: Math.min(100, status.persen) + '%' }"></div>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <span v-if="status.perlu_murojaah" class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">⚠️ Perlu murojaah wajib</span>
                <span v-if="status.juz_perlu_tasmi.length" class="px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-medium">🔴 Menunggu tasmi' juz {{ status.juz_perlu_tasmi.join(', ') }}</span>
                <span v-if="status.last" class="px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium">
                    Terakhir: juz {{ status.last.juz }}
                </span>
            </div>
        </div>

        <!-- Grid 30 juz -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Progres per Juz</h3>
            <div class="grid grid-cols-6 sm:grid-cols-10 gap-2">
                <div v-for="j in juzGrid" :key="j.juz" :title="`Juz ${j.juz}: ${j.ayat}/${j.total} ayat`" :class="[
                    'aspect-square rounded-lg flex flex-col items-center justify-center text-xs font-bold border',
                    juzCls(j.status)
                ]">
                    <span>{{ j.juz }}</span>
                    <span v-if="j.status !== 'belum'" class="text-[9px] font-medium opacity-80">{{ pct(j) }}%</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-3 mt-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-gray-100 border border-gray-200"></i> Belum</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-sky-100 border border-sky-300"></i> Berjalan</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-amber-100 border border-amber-300"></i> Selesai (perlu tasmi')</span>
                <span class="flex items-center gap-1.5"><i class="w-3 h-3 rounded bg-emerald-100 border border-emerald-400"></i> Tasmi' lulus</span>
            </div>
        </div>

        <!-- Riwayat setoran -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Riwayat Setoran</h3>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Jenis</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Rentang</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Ayat</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Juz</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Nilai</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Guru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in riwayat" :key="r.id" class="hover:bg-gray-50/40">
                        <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">{{ r.tanggal }}</td>
                        <td class="px-5 py-3">
                            <span :class="['px-2 py-0.5 rounded-lg text-xs font-semibold', jenisCls(r.jenis)]">{{ jenisLabel(r.jenis) }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-700">{{ r.rentang }}</td>
                        <td class="px-5 py-3 text-center text-sm font-semibold text-gray-700">{{ r.jumlah_ayat }}</td>
                        <td class="px-5 py-3 text-center text-sm text-gray-600">{{ r.juz }}</td>
                        <td class="px-5 py-3 text-center">
                            <span v-if="r.nilai != null" :class="['text-sm font-semibold', r.lulus ? 'text-emerald-600' : 'text-red-600']">{{ r.nilai }}</span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 hidden md:table-cell">{{ r.guru }}</td>
                    </tr>
                    <tr v-if="!riwayat.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">Belum ada setoran.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    santri: { type: Object, default: () => ({}) },
    status: { type: Object, default: () => ({}) },
    juzGrid: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
})

function pct(j) {
    return j.total > 0 ? Math.round(j.ayat / j.total * 100) : 0
}
function juzCls(status) {
    return {
        belum: 'bg-gray-50 text-gray-400 border-gray-200',
        berjalan: 'bg-sky-50 text-sky-700 border-sky-300',
        selesai: 'bg-amber-50 text-amber-700 border-amber-300',
        tasmi_lulus: 'bg-emerald-50 text-emerald-700 border-emerald-400',
    }[status] ?? 'bg-gray-50 text-gray-400 border-gray-200'
}
function jenisLabel(j) {
    return { ziyadah: 'Ziyadah', murojaah_wajib: 'Murojaah Wajib', murojaah_tambahan: 'Murojaah', tasmi: "Tasmi'" }[j] ?? j
}
function jenisCls(j) {
    return {
        ziyadah: 'bg-emerald-50 text-emerald-700',
        murojaah_wajib: 'bg-amber-50 text-amber-700',
        murojaah_tambahan: 'bg-sky-50 text-sky-700',
        tasmi: 'bg-violet-50 text-violet-700',
    }[j] ?? 'bg-gray-100 text-gray-600'
}
</script>
