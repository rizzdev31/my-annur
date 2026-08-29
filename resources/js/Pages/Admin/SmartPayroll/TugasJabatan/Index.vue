<template>
    <AdminLayout title="Tugas Jabatan" subtitle="Smart Payroll">

        <Head title="Tugas Jabatan" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Tugas Jabatan</h2>
                <p class="text-sm text-gray-500 mt-0.5">Template tugas per jabatan — realisasi {{ bulanLabel }}</p>
            </div>
            <Link :href="route('admin.smart-payroll.tugas-jabatan.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <MonitorIcon name="plus" size="sm" /> Tambah Tugas
            </Link>
        </div>

        <!-- Ringkasan -->
        <StatStrip :cards="cards" @select="setFilter" />

        <!-- Info cara kerja -->
        <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl px-4 py-3 mb-4 flex items-start gap-3">
            <MonitorIcon name="info" size="sm" class="text-indigo-500 shrink-0 mt-0.5" />
            <p class="text-xs text-indigo-700 leading-relaxed">
                <strong>Mandiri</strong>: guru kerjakan sendiri & upload bukti (wajib laporan = vakasi 0 bila tak submit). ·
                <strong>Absen Kegiatan</strong>: guru mengabsen peserta — peserta hadir otomatis dapat vakasi sama.
            </p>
        </div>

        <!-- Toolbar -->
        <MonitorToolbar :tabs="tabs" :status="filter" :search="search" placeholder="Cari nama tugas…"
            @update:status="setFilter" @update:search="search = $event">
            <template #filters>
                <select v-model="filterJabatan"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-400">
                    <option value="">Semua jabatan</option>
                    <option v-for="j in jabatanUnik" :key="j.id" :value="j.id">{{ j.nama }}</option>
                </select>
            </template>
        </MonitorToolbar>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/70">
                    <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                        <th class="px-5 py-3">Tugas</th>
                        <th class="px-4 py-3">Jabatan</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Realisasi Bulan Ini</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tugasFiltered" :key="t.id" class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <span v-if="t.perlu_verifikasi > 0" class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"
                                    title="Ada realisasi perlu diverifikasi" />
                                <div>
                                    <p class="font-semibold text-gray-800">{{ t.nama_tugas }}</p>
                                    <p class="text-xs text-gray-400 capitalize">{{ t.frekuensi_label }}<span v-if="t.wajib_laporan"> · wajib laporan</span></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-indigo-100 text-indigo-700">{{ t.kode_jabatan }}</span>
                            <p class="text-xs text-gray-500 mt-0.5">{{ t.jabatan }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', t.tipe_pengerjaan === 'absen_kegiatan' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700']">
                                {{ t.tipe_pengerjaan === 'absen_kegiatan' ? 'Absen Kegiatan' : 'Mandiri' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-gray-800 tabular-nums">{{ t.realisasi_bulan }}</span>
                                <span class="text-xs text-gray-400">realisasi</span>
                            </div>
                            <span v-if="t.perlu_verifikasi > 0"
                                class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-700">
                                <MonitorIcon name="clock" size="sm" class="!w-3 !h-3" /> {{ t.perlu_verifikasi }} perlu verifikasi
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.smart-payroll.tugas-jabatan.show', t.id)" title="Lihat detail & realisasi"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"><MonitorIcon name="eye" size="sm" /></Link>
                                <Link :href="route('admin.smart-payroll.tugas-jabatan.edit', t.id)" title="Edit tugas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"><MonitorIcon name="pencil" size="sm" /></Link>
                                <button @click="nonaktifkan(t.id, t.nama_tugas)" title="Nonaktifkan tugas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"><MonitorIcon name="ban" size="sm" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!tugasFiltered.length">
                        <td colspan="5" class="py-16 text-center">
                            <MonitorIcon name="clipboard" size="lg" class="mx-auto text-gray-300 mb-2" />
                            <p class="text-sm text-gray-400">Tidak ada tugas pada filter ini.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'
import StatStrip from '@/Components/Monitor/StatStrip.vue'
import MonitorToolbar from '@/Components/Monitor/MonitorToolbar.vue'
import MonitorIcon from '@/Components/Monitor/MonitorIcon.vue'

const confirm = ref(null)
const props = defineProps({
    tugas:      { type: Array, default: () => [] },
    summary:    { type: Object, default: () => ({}) },
    bulanLabel: { type: String, default: '' },
})

const filter = ref('')           // '' | mandiri | absen_kegiatan | perlu_verifikasi
const filterJabatan = ref('')
const search = ref('')

const cards = computed(() => [
    { label: 'Total Tugas', value: props.summary.total ?? 0, icon: 'clipboard', tone: 'gray', filter: '', active: filter.value === '' },
    { label: 'Mandiri', value: props.summary.mandiri ?? 0, icon: 'document', tone: 'blue', filter: 'mandiri', active: filter.value === 'mandiri' },
    { label: 'Absen Kegiatan', value: props.summary.absen_kegiatan ?? 0, icon: 'users', tone: 'violet', filter: 'absen_kegiatan', active: filter.value === 'absen_kegiatan' },
    { label: 'Perlu Verifikasi', value: props.summary.perlu_verifikasi ?? 0, icon: 'clock', tone: 'amber', filter: 'perlu_verifikasi', active: filter.value === 'perlu_verifikasi' },
])

const tabs = computed(() => [
    { val: '', label: 'Semua', count: props.summary.total ?? 0 },
    { val: 'mandiri', label: 'Mandiri', count: props.summary.mandiri ?? 0 },
    { val: 'absen_kegiatan', label: 'Absen Kegiatan', count: props.summary.absen_kegiatan ?? 0 },
    { val: 'perlu_verifikasi', label: 'Perlu Verifikasi', count: props.summary.perlu_verifikasi ?? 0 },
])

const jabatanUnik = computed(() => {
    const seen = new Set()
    return props.tugas.filter(t => { if (seen.has(t.jabatan_id)) return false; seen.add(t.jabatan_id); return true })
        .map(t => ({ id: t.jabatan_id, nama: t.jabatan }))
})

const tugasFiltered = computed(() => {
    let list = props.tugas
    if (filter.value === 'mandiri' || filter.value === 'absen_kegiatan') list = list.filter(t => t.tipe_pengerjaan === filter.value)
    if (filter.value === 'perlu_verifikasi') list = list.filter(t => t.perlu_verifikasi > 0)
    if (filterJabatan.value) list = list.filter(t => t.jabatan_id === filterJabatan.value)
    if (search.value) { const q = search.value.toLowerCase(); list = list.filter(t => t.nama_tugas.toLowerCase().includes(q)) }
    return list
})

function setFilter(v) { filter.value = v }

function nonaktifkan(id, nama) {
    confirm.value.ask(
        { title: 'Nonaktifkan Tugas Jabatan?', message: `Tugas "${nama}" akan dinonaktifkan.`,
          variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' },
        (done) => router.delete(route('admin.smart-payroll.tugas-jabatan.destroy', id), { preserveScroll: true, onFinish: done }),
    )
}
</script>
