<template>
    <AdminLayout title="Absensi Kegiatan" subtitle="Smart Payroll">

        <Head title="Absensi Kegiatan" />

        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Absensi Kegiatan</h2>
            <p class="text-sm text-gray-500 mt-0.5">Kegiatan yang diabsen guru (tugas tipe "Absen Kegiatan")</p>
        </div>

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
            class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 flex items-center gap-2">
            <MonitorIcon name="check" size="sm" /> {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error"
            class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $page.props.flash.error }}</div>

        <!-- Ringkasan -->
        <StatStrip :cards="cards" @select="filterStatus = $event" />

        <!-- Toolbar -->
        <MonitorToolbar :tabs="tabs" :status="filterStatus" :search="search" placeholder="Cari kegiatan / pengabsen…"
            @update:status="filterStatus = $event" @update:search="search = $event" />

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/70">
                    <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                        <th class="px-5 py-3">Kegiatan</th>
                        <th class="px-4 py-3">Pengabsen</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kehadiran</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="k in kegiatanFiltered" :key="k.id" class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-gray-800">{{ k.nama_kegiatan }}</p>
                            <p v-if="k.lokasi" class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                                <MonitorIcon name="location" size="sm" class="!w-3 !h-3" /> {{ k.lokasi }}
                            </p>
                        </td>
                        <td class="px-4 py-3.5">
                            <p class="text-sm text-gray-700">{{ k.pengabsen.nama }}</p>
                            <p class="text-xs text-gray-400">{{ k.pengabsen.jabatan }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <p class="text-sm text-gray-700 tabular-nums">{{ k.tanggal_kegiatan }}</p>
                            <p v-if="k.jam_mulai" class="text-xs text-gray-400 tabular-nums">{{ k.jam_mulai }}{{ k.jam_selesai ? ' – ' + k.jam_selesai : '' }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <ProgressMini :done="k.stats.hadir" :total="k.stats.total_peserta" tone="emerald" label="hadir" />
                        </td>
                        <td class="px-4 py-3.5"><StatusBadge :status="k.status" /></td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.smart-payroll.absensi-kegiatan.show', k.id)"
                                    class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg hover:bg-indigo-100 transition">Detail</Link>
                                <button v-if="k.status !== 'selesai'" @click="hapus(k.id, k.nama_kegiatan)" title="Hapus"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"><MonitorIcon name="trash" size="sm" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!kegiatanFiltered.length">
                        <td colspan="6" class="py-16 text-center">
                            <MonitorIcon name="clipboard" size="lg" class="mx-auto text-gray-300 mb-2" />
                            <p class="text-sm text-gray-400">Tidak ada kegiatan pada filter ini.</p>
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
import StatusBadge from '@/Components/Monitor/StatusBadge.vue'
import ProgressMini from '@/Components/Monitor/ProgressMini.vue'
import MonitorToolbar from '@/Components/Monitor/MonitorToolbar.vue'
import MonitorIcon from '@/Components/Monitor/MonitorIcon.vue'

const confirm = ref(null)
const props = defineProps({
    kegiatan: { type: Array, default: () => [] },
    filters:  { type: Object, default: () => ({}) },
    stats:    { type: Object, default: () => ({}) },
})

const filterStatus = ref(props.filters.status ?? '')
const search = ref(props.filters.search ?? '')

const cards = computed(() => [
    { label: 'Total Kegiatan', value: props.stats.total ?? 0, icon: 'clipboard', tone: 'gray', filter: '', active: filterStatus.value === '' },
    { label: 'Berlangsung', value: props.stats.berlangsung ?? 0, icon: 'play', tone: 'amber', filter: 'berlangsung', active: filterStatus.value === 'berlangsung' },
    { label: 'Selesai', value: props.stats.selesai ?? 0, icon: 'check', tone: 'blue', filter: 'selesai', active: filterStatus.value === 'selesai' },
])

const tabs = computed(() => [
    { val: '', label: 'Semua', count: props.stats.total ?? 0 },
    { val: 'berlangsung', label: 'Berlangsung', count: props.stats.berlangsung ?? 0 },
    { val: 'selesai', label: 'Selesai', count: props.stats.selesai ?? 0 },
])

const kegiatanFiltered = computed(() => {
    let list = props.kegiatan
    if (filterStatus.value) list = list.filter(k => k.status === filterStatus.value)
    if (search.value) {
        const q = search.value.toLowerCase()
        list = list.filter(k => k.nama_kegiatan.toLowerCase().includes(q) || k.pengabsen?.nama?.toLowerCase().includes(q))
    }
    return list
})

function hapus(id, nama) {
    confirm.value.ask(
        { title: 'Hapus Kegiatan?', message: `Kegiatan "${nama}" beserta seluruh data absensi peserta akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.post(route('admin.smart-payroll.absensi-kegiatan.destroy', id), {}, { preserveScroll: true, onFinish: done }),
    )
}
</script>
