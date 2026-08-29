<template>
    <AdminLayout title="Jurnal Mengajar" subtitle="Smart Education">

        <Head title="Jurnal Mengajar" />

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Jurnal & Absensi Santri</h2>
            <p class="text-sm text-gray-400 mt-0.5">Monitoring sesi mengajar sekolah dan rekap kehadiran santri.</p>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Total Sesi</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ summary.total_sesi }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Sudah Absen Santri</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ summary.sudah_isi }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Hadir</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_hadir }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Telat</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ summary.total_telat }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-400">Alpha</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ summary.total_alpha }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                <input v-model="f.dari" type="date" :class="fieldCls" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                <input v-model="f.sampai" type="date" :class="fieldCls" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kelas</label>
                <select v-model="f.kelas_id" :class="fieldCls">
                    <option :value="null">Semua kelas</option>
                    <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Guru</label>
                <select v-model="f.guru_id" :class="fieldCls">
                    <option :value="null">Semua guru</option>
                    <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                </select>
            </div>
            <button @click="terapkan"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Terapkan
            </button>
        </div>

        <!-- Tabel sesi -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Kelas / Mapel</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Guru</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Absensi Santri</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in sesi" :key="s.id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ formatTgl(s.tanggal) }}</p>
                            <p class="text-xs text-gray-400">{{ s.jp }} JP · {{ statusLabel(s.status_sesi) }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ s.kelas }}</p>
                            <p class="text-xs text-gray-400">{{ s.mapel }}</p>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span class="text-sm text-gray-600">{{ s.guru }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div v-if="s.sudah_absen_santri" class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-medium">H {{ s.hadir }}</span>
                                <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium">T {{ s.telat }}</span>
                                <span class="px-2 py-0.5 rounded-lg bg-red-50 text-red-700 text-xs font-medium">A {{ s.alpha }}</span>
                                <span class="text-xs text-gray-400 self-center">/ {{ s.total_santri }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-400">Belum diisi</span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button @click="lihat(s)"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!sesi.length">
                        <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada sesi mengajar pada rentang ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal detail -->
        <Transition name="modal">
            <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="detail = null" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ detail.kelas }} — {{ detail.mapel }}</h3>
                            <p class="text-xs text-gray-400">{{ formatTgl(detail.tanggal) }} · {{ detail.guru }} · {{ detail.jp }} JP</p>
                        </div>
                        <button @click="detail = null" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="detail.materi" class="mb-4 mt-2 p-3 rounded-xl bg-gray-50 text-sm text-gray-700">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Materi</span>
                        <p class="mt-1">{{ detail.materi }}</p>
                    </div>

                    <div v-if="detail.santri.length" class="space-y-1.5">
                        <div v-for="(st, i) in detail.santri" :key="i"
                            class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ st.nama }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ st.nip }}</p>
                            </div>
                            <span :class="['px-2.5 py-1 rounded-lg text-xs font-semibold', badgeStatus(st.status)]">
                                {{ statusSantriLabel(st.status) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-gray-400">Absensi santri belum diisi guru.</p>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    sesi: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
    kelasOpsi: { type: Array, default: () => [] },
    guruOpsi: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const f = reactive({
    dari: props.filter.dari,
    sampai: props.filter.sampai,
    kelas_id: props.filter.kelas_id ?? null,
    guru_id: props.filter.guru_id ?? null,
})

const detail = ref(null)

function terapkan() {
    router.get(route('admin.smart-education.jurnal.index'), {
        dari: f.dari, sampai: f.sampai, kelas_id: f.kelas_id, guru_id: f.guru_id,
    }, { preserveState: true, preserveScroll: true })
}

function lihat(s) { detail.value = s }

function formatTgl(t) {
    if (!t) return '—'
    return new Date(t).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
}
function statusLabel(s) {
    return { terlaksana: 'Terlaksana', hadir: 'Hadir', libur: 'Libur', izin: 'Izin', tidak_terlaksana: 'Tidak terlaksana' }[s] ?? s
}
function statusSantriLabel(s) {
    return { hadir: 'Hadir', telat: 'Telat', alpha: 'Alpha' }[s] ?? s
}
function badgeStatus(s) {
    return {
        hadir: 'bg-emerald-50 text-emerald-700',
        telat: 'bg-amber-50 text-amber-700',
        alpha: 'bg-red-50 text-red-700',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease; }
.modal-leave-active { transition: all 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
