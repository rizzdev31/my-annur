<template>
    <AdminLayout :title="`Penggajian — ${periode.nama}`" subtitle="Smart Payroll">

        <Head :title="`Penggajian ${periode.nama}`" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.penggajian.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-gray-900">{{ periode.nama }}</h2>
                    <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', badgeStatus(periode.status).class]">
                        {{ badgeStatus(periode.status).label }}
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ periode.tanggal_mulai }} – {{ periode.tanggal_selesai }}
                </p>
            </div>
            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button @click="generate" :disabled="generating || periode.status === 'dibayar'" :class="['inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors',
                    'bg-teal-600 hover:bg-teal-700 text-white disabled:opacity-50']">
                    <svg class="w-4 h-4" :class="generating ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ generating ? 'Generate...' : 'Generate Gaji' }}
                </button>
                <button v-if="stats.total_draft > 0" @click="finalisasiSemua"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                    Finalisasi Semua
                </button>
            </div>
        </div>

        <!-- Stats strip -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-gray-900">{{ stats.total_guru }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Total guru</p>
            </div>
            <div class="bg-gray-50 rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-gray-600">{{ stats.total_draft }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Draft</p>
            </div>
            <div class="bg-amber-50 rounded-xl border border-amber-200 px-4 py-3">
                <p class="text-2xl font-bold text-amber-600">{{ stats.total_final }}</p>
                <p class="text-xs text-amber-500 mt-0.5">Final</p>
            </div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-200 px-4 py-3">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.total_dibayar }}</p>
                <p class="text-xs text-emerald-500 mt-0.5">Dibayar</p>
            </div>
            <div class="bg-indigo-50 rounded-xl border border-indigo-200 px-4 py-3 lg:col-span-1">
                <p class="text-xl font-bold text-indigo-700">{{ formatRp(stats.grand_total_bersih) }}</p>
                <p class="text-xs text-indigo-500 mt-0.5">Total gaji bersih</p>
            </div>
        </div>

        <!-- Warning: ada guru belum digenerate -->
        <div v-if="belumGenerate.length > 0"
            class="mb-4 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-2xl">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <p class="text-sm font-medium text-amber-700">
                    {{ belumGenerate.length }} guru belum digenerate:
                    <span class="font-normal">{{belumGenerate.slice(0, 3).map(g => g.nama).join(', ')}}{{
                        belumGenerate.length > 3 ? '...' : '' }}</span>
                </p>
                <button @click="generate" class="text-xs text-amber-700 underline mt-0.5">Generate semua →</button>
            </div>
        </div>

        <!-- Tabel gaji -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Guru</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Gaji Pokok</th>
                        <th
                            class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Vakasi</th>
                        <th
                            class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                            Potongan</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Gaji Bersih</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="pg in penggajian.data" :key="pg.id"
                        :class="['hover:bg-gray-50/40 transition-colors', pg.ada_koreksi_manual ? 'bg-amber-50/20' : '']">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <img v-if="pg.foto" :src="pg.foto" class="w-7 h-7 rounded-full object-cover shrink-0" />
                                <div v-else
                                    class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-bold text-indigo-700">{{ pg.nama?.charAt(0) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ pg.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ pg.jabatan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-medium text-gray-700">{{ formatRp(pg.gaji_pokok) }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-right hidden md:table-cell">
                            <p class="text-sm font-medium text-teal-700">
                                +{{ formatRp(pg.vakasi_absen_harian + pg.vakasi_mengajar + pg.vakasi_tugas_jabatan +
                                pg.vakasi_tugas_tambahan) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-right hidden lg:table-cell">
                            <p v-if="pg.total_potongan > 0" class="text-sm font-medium text-red-600">
                                -{{ formatRp(pg.total_potongan) }}
                            </p>
                            <p v-else class="text-xs text-gray-300">—</p>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-bold text-indigo-700">{{ formatRp(pg.gaji_bersih) }}</p>
                            <span v-if="pg.ada_koreksi_manual" class="text-xs text-amber-600">✎ koreksi</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span
                                :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium', badgeStatus(pg.status).class]">
                                {{ badgeStatus(pg.status).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1">
                                <!-- Slip gaji -->
                                <Link :href="route('admin.smart-payroll.penggajian.slip', pg.id)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                    title="Slip Gaji">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </Link>
                                <!-- Finalisasi -->
                                <button v-if="pg.status === 'draft'" @click="finalisasi(pg)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                                    title="Finalisasi">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <!-- Generate ulang -->
                                <button @click="generateSatu(pg)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors"
                                    title="Generate ulang">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!penggajian.data?.length">
                        <td colspan="7" class="py-14 text-center">
                            <p class="text-sm text-gray-400">Belum ada data gaji. Klik "Generate Gaji" untuk mulai.</p>
                        </td>
                    </tr>
                </tbody>
                <!-- Footer total -->
                <tfoot v-if="penggajian.data?.length" class="border-t border-gray-200 bg-gray-50/50">
                    <tr>
                        <td class="px-5 py-3.5 text-xs font-semibold text-gray-600" colspan="2">
                            Total {{ stats.total_guru }} guru
                        </td>
                        <td class="px-5 py-3.5 text-right hidden md:table-cell">
                            <p class="text-xs font-semibold text-teal-700">
                                +{{formatRp(stats.grand_total_kotor - penggajian.data?.reduce((s, p) => s + p.gaji_pokok, 0))
                                }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-right hidden lg:table-cell">
                            <p class="text-xs font-semibold text-red-600">
                                -{{ formatRp(stats.total_potongan) }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <p class="text-sm font-bold text-indigo-700">{{ formatRp(stats.grand_total_bersih) }}</p>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Konfirmasi: Finalisasi Semua -->
        <ConfirmDialog
            :show="showFinalisasi"
            variant="primary"
            title="Finalisasi Semua Penggajian?"
            :message="`${stats.total_draft ?? 0} penggajian draft akan difinalisasi.`"
            :details="[
                'Status berubah dari Draft menjadi Final (Siap Bayar)',
                'Data gaji terkunci dari perubahan otomatis (generate ulang)',
                'Setelah final, periode bisa ditandai sudah dibayar',
            ]"
            confirm-label="Ya, Finalisasi Semua"
            :loading="finalizing"
            @confirm="confirmFinalisasi"
            @cancel="showFinalisasi = false" />

        <!-- Konfirmasi: Generate Gaji -->
        <ConfirmDialog
            :show="showGenerate"
            variant="primary"
            title="Generate Gaji Semua Guru?"
            message="Sistem akan menghitung gaji seluruh guru aktif untuk periode ini."
            :details="[
                'Gaji dihitung otomatis dari absensi, mengajar, tugas, dan potongan',
                'Data draft yang sudah ada akan diperbarui (dihitung ulang)',
                'Penyesuaian liburan manual tetap dipertahankan',
            ]"
            confirm-label="Ya, Generate Sekarang"
            :loading="generating"
            @confirm="confirmGenerate"
            @cancel="showGenerate = false" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'

const props = defineProps({
    periode: { type: Object, required: true },
    penggajian: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    belumGenerate: { type: Array, default: () => [] },
})

const generating    = ref(false)
const showFinalisasi = ref(false)
const finalizing     = ref(false)
const showGenerate   = ref(false)

function badgeStatus(s) {
    return {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600' },
        final: { label: 'Final', class: 'bg-amber-50 text-amber-700' },
        dibayar: { label: 'Dibayar', class: 'bg-emerald-50 text-emerald-700' },
    }[s] ?? { label: s, class: 'bg-gray-100 text-gray-600' }
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function generate() {
    showGenerate.value = true
}
function confirmGenerate() {
    generating.value = true
    router.post(route('admin.smart-payroll.penggajian.generate', props.periode.id), {}, {
        onFinish: () => { generating.value = false; showGenerate.value = false },
    })
}

function generateSatu(pg) {
    router.post(route('admin.smart-payroll.penggajian.generate-satu', [props.periode.id, pg.id]), {}, {
        preserveScroll: true,
    })
}

function finalisasi(pg) {
    router.patch(route('admin.smart-payroll.penggajian.finalisasi', pg.id), {}, { preserveScroll: true })
}

function finalisasiSemua() {
    showFinalisasi.value = true
}
function confirmFinalisasi() {
    router.patch(route('admin.smart-payroll.penggajian.finalisasi-semua', props.periode.id), {}, {
        onStart:  () => { finalizing.value = true },
        onFinish: () => { finalizing.value = false; showFinalisasi.value = false },
    })
}
</script>