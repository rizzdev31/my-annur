<template>
    <AdminLayout :title="santri.nama" subtitle="Monitoring Tahsin">

        <Head :title="`Tahsin — ${santri.nama}`" />

        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-education.tahsin-monitoring.index')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ santri.nama }}</h2>
                <p class="text-sm text-gray-400">NIP {{ santri.nip }} · Tahsin · {{ lbl(santri.level) }}</p>
            </div>
        </div>

        <!-- Grid level 1-5 -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Progres Antar Level</h3>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <div v-for="g in levelGrid" :key="g.level" :class="[
                    'rounded-xl p-3 text-center border', cls(g)
                ]">
                    <p class="text-xs font-bold">{{ g.level === 6 ? 'Persiapan' : 'Level ' + g.level }}</p>
                    <p class="text-[10px] mt-1 opacity-80">{{ g.lulus }}/{{ g.total }}</p>
                    <p class="text-[10px] font-semibold mt-0.5">{{ label(g) }}</p>
                </div>
            </div>
        </div>

        <!-- Materi level kini -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Materi {{ lbl(santri.level) }} (saat ini)</h3>
            <div v-if="materiKini.length" class="space-y-2">
                <div v-for="m in materiKini" :key="m.materi_id" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-gray-50">
                    <p class="flex-1 text-sm text-gray-800">{{ m.nama }}</p>
                    <span v-if="m.sudah_dinilai" :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', m.lulus ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                        {{ Number(m.nilai).toFixed(0) }} · {{ m.lulus ? 'Lulus' : 'Belum' }}
                    </span>
                    <span v-else class="text-xs text-gray-400 italic">Belum dinilai</span>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400">Belum ada materi untuk level ini.</p>
        </div>

        <!-- Riwayat -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-base font-semibold text-gray-900">Riwayat Penilaian</h3></div>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Lv</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Materi</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Nilai</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Guru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="(r, i) in riwayat" :key="i" class="hover:bg-gray-50/40">
                        <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">{{ r.tanggal }}</td>
                        <td class="px-5 py-3 text-center text-sm text-gray-600">{{ r.level }}</td>
                        <td class="px-5 py-3 text-sm text-gray-700">{{ r.materi }}</td>
                        <td class="px-5 py-3 text-center">
                            <span :class="['text-sm font-semibold', r.lulus ? 'text-emerald-600' : 'text-red-600']">{{ Number(r.nilai).toFixed(0) }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 hidden md:table-cell">{{ r.guru }}</td>
                    </tr>
                    <tr v-if="!riwayat.length"><td colspan="5" class="py-12 text-center text-sm text-gray-400">Belum ada penilaian.</td></tr>
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
    materiKini: { type: Array, default: () => [] },
    levelGrid: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
})

function cls(g) {
    return {
        lewat: 'bg-emerald-50 text-emerald-700 border-emerald-300',
        siap_naik: 'bg-emerald-50 text-emerald-700 border-emerald-400',
        berjalan: 'bg-violet-50 text-violet-700 border-violet-300',
        belum: 'bg-gray-50 text-gray-400 border-gray-200',
    }[g.status] ?? 'bg-gray-50 text-gray-400 border-gray-200'
}
function label(g) {
    return { lewat: 'Lulus', siap_naik: 'Siap naik', berjalan: 'Berjalan', belum: 'Belum' }[g.status] ?? '—'
}
function lbl(lv) { return lv === 6 ? 'Persiapan Tahfidz' : 'Level ' + lv }
</script>
