<template>
    <AdminLayout :title="guru.nama" subtitle="Kinerja Tenaga Pendidik">

        <Head :title="guru.nama" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <a :href="route('admin.smart-payroll.monitoring.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <img v-if="guru.foto" :src="guru.foto" class="w-12 h-12 rounded-xl object-cover shrink-0" />
            <div v-else
                class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-lg font-bold text-indigo-700 shrink-0">
                {{ guru.nama?.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-semibold text-gray-900">{{ guru.nama }}</h2>
                <p class="text-sm text-gray-400">{{ guru.jabatan }}
                    <template v-if="guru.jabatan_tambahan"> · {{ guru.jabatan_tambahan }}</template>
                </p>
            </div>
            <!-- Filter bulan -->
            <div class="flex items-center gap-2 shrink-0">
                <select v-model="fBulan" @change="applyFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none">
                    <option v-for="(n, i) in namaBulan" :key="i" :value="i">{{ n }}</option>
                </select>
                <input v-model.number="fTahun" type="number" @change="applyFilter"
                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none" />
            </div>
        </div>

        <!-- Skor Kinerja -->
        <div v-if="rekap_kinerja"
            class="mb-5 p-5 bg-gradient-to-r from-indigo-50 to-violet-50 border border-indigo-100 rounded-2xl">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-indigo-800">Rekap Kinerja {{ namaBulan[bulan] }} {{ tahun }}
                    </p>
                    <p class="text-xs text-indigo-500 mt-0.5">{{ rekap_kinerja.label_skor }}</p>
                </div>
                <div class="text-3xl font-bold text-indigo-700">{{ rekap_kinerja.skor_total }}</div>
            </div>
            <div class="grid grid-cols-3 gap-3 mt-4">
                <div class="bg-white/70 rounded-xl px-3 py-2 text-center">
                    <p class="text-lg font-bold text-emerald-700">{{ rekap_kinerja.skor_keaktifan }}</p>
                    <p class="text-xs text-gray-400">Keaktifan</p>
                </div>
                <div class="bg-white/70 rounded-xl px-3 py-2 text-center">
                    <p class="text-lg font-bold text-violet-700">{{ rekap_kinerja.skor_penugasan }}</p>
                    <p class="text-xs text-gray-400">Penugasan</p>
                </div>
                <div class="bg-white/70 rounded-xl px-3 py-2 text-center">
                    <p class="text-xs text-gray-500 truncate">{{ rekap_kinerja.catatan || '—' }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Catatan</p>
                </div>
            </div>
        </div>

        <!-- Ringkasan Absensi -->
        <div class="grid grid-cols-4 lg:grid-cols-8 gap-2 mb-5">
            <div v-for="s in absensiCards" :key="s.label" :class="['rounded-xl border px-3 py-2 text-center', s.bg]">
                <p :class="['text-xl font-bold leading-none', s.color]">{{ s.value }}</p>
                <p class="text-xs text-gray-400 mt-1 leading-tight">{{ s.label }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 mb-4 overflow-x-auto">
            <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                :class="['px-4 py-2 rounded-xl text-sm font-medium transition-colors border whitespace-nowrap',
                    activeTab === t.key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300']">
                {{ t.icon }} {{ t.label }}
                <span v-if="t.badge" class="ml-1 text-xs opacity-70">({{ t.badge }})</span>
            </button>
        </div>

        <!-- Tab Content: Absensi Harian -->
        <div v-if="activeTab === 'harian'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Jam
                            Masuk</th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Jam
                            Pulang</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Terlambat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Koreksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in absensi_harian" :key="a.id"
                        :class="['hover:bg-gray-50/40', a.is_koreksi ? 'bg-amber-50/20' : '']">
                        <td class="px-4 py-3">
                            <p class="text-sm text-gray-700">{{ a.tanggal }}</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['text-xs font-semibold px-2 py-0.5 rounded-lg', statusCls(a.status)]">{{
                                statusLabel[a.status] ?? a.status }}</span>
                            <span v-if="a.is_koreksi" class="ml-1 text-xs text-amber-500">✎</span>
                        </td>
                        <td class="px-4 py-3 text-center hidden md:table-cell text-sm text-gray-600">{{ a.jam_masuk ??
                            '—' }}
                        </td>
                        <td class="px-4 py-3 text-center hidden md:table-cell text-sm text-gray-600">{{ a.jam_pulang ??
                            '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="a.menit_terlambat > 0" class="text-xs font-semibold text-amber-600">{{
                                a.menit_terlambat
                                }}m</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="openKoreksiDetail(a, 'harian')"
                                class="text-xs text-amber-600 hover:underline">Koreksi</button>
                        </td>
                    </tr>
                    <tr v-if="!absensi_harian.length">
                        <td colspan="6" class="py-10 text-center text-sm text-gray-400">Belum ada data.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab: Mengajar -->
        <div v-else-if="activeTab === 'mengajar'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Mata Pelajaran
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">JP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Materi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in absensi_mengajar" :key="a.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 text-sm text-gray-600">{{ a.tanggal }}</td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-gray-800">{{ a.mata_pelajaran }}</p>
                            <p class="text-xs text-gray-400">{{ a.kelas }}</p>
                        </td>
                        <td class="px-4 py-3 text-center"><span
                                :class="['text-xs font-semibold px-2 py-0.5 rounded-lg', statusMengajarCls(a.status)]">{{
                                a.status }}</span></td>
                        <td class="px-4 py-3 text-center text-sm font-bold text-teal-700">{{ a.jp_terlaksana }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 truncate max-w-xs">{{ a.materi ?? '—' }}</td>
                    </tr>
                    <tr v-if="!absensi_mengajar.length">
                        <td colspan="5" class="py-10 text-center text-sm text-gray-400">Belum ada data.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab: Log Kerja -->
        <div v-else-if="activeTab === 'log'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Judul</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tugas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Durasi</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="l in log_kerja" :key="l.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 text-sm text-gray-600">{{ l.tanggal }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ l.judul }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ l.tugas }}</td>
                        <td class="px-4 py-3 text-center text-xs font-bold text-indigo-700">{{ l.durasi }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['text-xs font-semibold px-2 py-0.5 rounded-lg',
                                l.status === 'diverifikasi' ? 'bg-emerald-50 text-emerald-700' :
                                    l.status === 'submitted' ? 'bg-amber-50 text-amber-700' :
                                        l.status === 'ditolak' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500']">
                                {{ l.status }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!log_kerja.length">
                        <td colspan="5" class="py-10 text-center text-sm text-gray-400">Belum ada log kerja.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab: Tugas Tambahan -->
        <div v-else-if="activeTab === 'tugas'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Judul Tugas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Disetujui</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Dilaporkan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Verifikator</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tugas_tambahan" :key="t.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ t.judul }}</td>
                        <td class="px-4 py-3 text-center">
                            <span
                                :class="['text-xs font-semibold px-2 py-0.5 rounded-lg',
                                    t.status === 'selesai' ? 'bg-emerald-50 text-emerald-700' :
                                        t.status === 'sedang_berjalan' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-500']">
                                {{ t.status?.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="t.disetujui" class="text-emerald-600 font-bold">✓</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ t.dilaporkan ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ t.diverifikasi ?? '—' }}</td>
                    </tr>
                    <tr v-if="!tugas_tambahan.length">
                        <td colspan="5" class="py-10 text-center text-sm text-gray-400">Belum ada tugas tambahan.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab: Log Koreksi -->
        <div v-else-if="activeTab === 'koreksi'" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Perubahan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Alasan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Oleh / Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="k in log_koreksi" :key="k.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 text-sm text-gray-600">{{ k.tanggal }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-lg bg-gray-100 text-gray-600">{{ k.tipe }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', statusCls(k.nilai_lama)]">{{
                                    k.nilai_lama ?? 'baru' }}</span>
                                <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                                <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', statusCls(k.nilai_baru)]">{{
                                    k.nilai_baru }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">{{ k.alasan }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">
                            <p>{{ k.dikoreksi_oleh }}</p>
                            <p>{{ k.waktu }}</p>
                        </td>
                    </tr>
                    <tr v-if="!log_koreksi.length">
                        <td colspan="5" class="py-10 text-center text-sm text-gray-400">Belum ada log koreksi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Koreksi Detail -->
        <div v-if="showKoreksi" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Koreksi Absensi Harian</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ koreksiTarget?.tanggal }}</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button v-for="s in statusOptions" :key="s.value" type="button"
                                @click="kdForm.nilai_baru = s.value" :class="[`py-2 rounded-xl border-2 text-xs font-semibold transition-all text-center`,
                                    kdForm.nilai_baru === s.value ? s.active : `border-gray-200 text-gray-600`]">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Masuk</label>
                            <input v-model="kdForm.jam_masuk" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Pulang</label>
                            <input v-model="kdForm.jam_pulang" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Alasan Koreksi <span
                                class="text-red-500">*</span></label>
                        <textarea v-model="kdForm.alasan" rows="2" :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none bg-white resize-none`,
                            kdErr ? `border-red-300` : `border-gray-200 focus:border-indigo-500`]" />
                        <p v-if="kdErr" class="mt-1 text-xs text-red-500">{{ kdErr }}</p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showKoreksi = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">Batal</button>
                    <button @click="submitKoreksiDetail" :disabled="kdLoading"
                        class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ kdLoading ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guru: { type: Object, default: () => ({}) },
    ringkasan_absensi: { type: Object, default: () => ({}) },
    absensi_harian: { type: Array, default: () => [] },
    absensi_mengajar: { type: Array, default: () => [] },
    log_kerja: { type: Array, default: () => [] },
    tugas_tambahan: { type: Array, default: () => [] },
    realisasi_jabatan: { type: Array, default: () => [] },
    rekap_kinerja: { type: Object, default: null },
    log_koreksi: { type: Array, default: () => [] },
    bulan: { type: Number, default: new Date().getMonth() + 1 },
    tahun: { type: Number, default: new Date().getFullYear() },
})

const fBulan = ref(props.bulan)
const fTahun = ref(props.tahun)
const activeTab = ref('harian')

const namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const statusLabel = {
    hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit',
    alfa: 'Alfa', libur: 'Libur', dinas_luar: 'Dinas Luar', belum: 'Belum',
}
const statusCls = (s) => ({
    hadir: 'bg-emerald-50 text-emerald-700', terlambat: 'bg-amber-50 text-amber-700',
    alfa: 'bg-red-50 text-red-600', izin: 'bg-blue-50 text-blue-700',
    sakit: 'bg-indigo-50 text-indigo-700', libur: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-500')

const statusMengajarCls = (s) => ({
    terlaksana: 'bg-emerald-50 text-emerald-700', tidak_terlaksana: 'bg-red-50 text-red-600',
    pengganti: 'bg-violet-50 text-violet-700', izin: 'bg-amber-50 text-amber-700',
    libur: 'bg-gray-100 text-gray-500',
}[s] ?? 'bg-gray-100 text-gray-500')

const statusOptions = [
    { value: 'hadir', label: 'Hadir', active: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'terlambat', label: 'Terlambat', active: 'border-amber-500 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', active: 'border-blue-500 bg-blue-50 text-blue-700' },
    { value: 'sakit', label: 'Sakit', active: 'border-indigo-500 bg-indigo-50 text-indigo-700' },
    { value: 'alfa', label: 'Alfa', active: 'border-red-500 bg-red-50 text-red-600' },
    { value: 'libur', label: 'Libur', active: 'border-gray-400 bg-gray-100 text-gray-600' },
    { value: 'dinas_luar', label: 'Dinas', active: 'border-violet-500 bg-violet-50 text-violet-700' },
    { value: 'izin_sakit', label: 'Izin Sakit', active: 'border-indigo-400 bg-indigo-50 text-indigo-700' },
]

const absensiCards = computed(() => {
    const r = props.ringkasan_absensi
    return [
        { label: 'Hari Kerja', value: r.hari_kerja ?? 0, bg: 'bg-white border-gray-200', color: 'text-gray-900' },
        { label: 'Hadir', value: r.hadir ?? 0, bg: 'bg-emerald-50 border-emerald-100', color: 'text-emerald-700' },
        { label: 'Terlambat', value: r.terlambat ?? 0, bg: 'bg-amber-50 border-amber-100', color: 'text-amber-700' },
        { label: 'Izin', value: r.izin ?? 0, bg: 'bg-blue-50 border-blue-100', color: 'text-blue-700' },
        { label: 'Sakit', value: r.sakit ?? 0, bg: 'bg-indigo-50 border-indigo-100', color: 'text-indigo-700' },
        { label: 'Alfa', value: r.alfa ?? 0, bg: 'bg-red-50 border-red-100', color: 'text-red-600' },
        { label: 'Libur', value: r.libur ?? 0, bg: 'bg-gray-50 border-gray-200', color: 'text-gray-500' },
        { label: '% Hadir', value: (r.pct_hadir ?? 0) + '%', bg: 'bg-teal-50 border-teal-100', color: 'text-teal-700' },
    ]
})

const tabs = computed(() => [
    { key: 'harian', icon: '📋', label: 'Absensi Harian', badge: props.absensi_harian.length },
    { key: 'mengajar', icon: '📚', label: 'Mengajar', badge: props.absensi_mengajar.length },
    { key: 'log', icon: '📝', label: 'Log Kerja', badge: props.log_kerja.length },
    { key: 'tugas', icon: '✅', label: 'Tugas Tambahan', badge: props.tugas_tambahan.length },
    { key: 'koreksi', icon: '✎', label: 'Log Koreksi', badge: props.log_koreksi.length },
])

function applyFilter() {
    router.get(route('admin.smart-payroll.monitoring.detail', props.guru.id), {
        bulan: fBulan.value, tahun: fTahun.value,
    })
}

// Koreksi dari halaman detail
const showKoreksi = ref(false)
const kdLoading = ref(false)
const koreksiTarget = ref(null)
const kdForm = reactive({ nilai_baru: 'hadir', jam_masuk: '', jam_pulang: '', alasan: '' })
const kdErr = ref('')

function openKoreksiDetail(a, tipe) {
    koreksiTarget.value = a
    Object.assign(kdForm, { nilai_baru: a.status, jam_masuk: a.jam_masuk ?? '', jam_pulang: a.jam_pulang ?? '', alasan: '' })
    kdErr.value = ''
    showKoreksi.value = true
}

function submitKoreksiDetail() {
    kdErr.value = kdForm.alasan.trim() ? '' : 'Alasan wajib diisi'
    if (kdErr.value) return

    kdLoading.value = true
    router.post(route('admin.smart-payroll.monitoring.koreksi'), {
        tipe: 'harian',
        referensi_id: koreksiTarget.value.id,
        tenaga_pendidik_id: props.guru.id,
        tanggal: koreksiTarget.value.tanggal_raw ?? koreksiTarget.value.tanggal,
        field: 'status',
        nilai_baru: kdForm.nilai_baru,
        jam_masuk: kdForm.jam_masuk || undefined,
        jam_pulang: kdForm.jam_pulang || undefined,
        alasan: kdForm.alasan,
    }, {
        onSuccess: () => { showKoreksi.value = false },
        onFinish: () => kdLoading.value = false,
        preserveScroll: true,
    })
}
</script>