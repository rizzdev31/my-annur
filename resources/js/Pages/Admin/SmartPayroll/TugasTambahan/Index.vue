<template>
    <AdminLayout title="Tugas Tambahan" subtitle="Smart Payroll">

        <Head title="Tugas Tambahan" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Tugas Tambahan</h2>
                <p class="text-sm text-gray-500 mt-0.5">Tugas di luar jadwal rutin dengan vakasi fleksibel</p>
            </div>
            <Link :href="route('admin.smart-payroll.tugas-tambahan.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <MonitorIcon name="plus" size="sm" /> Buat Tugas
            </Link>
        </div>

        <!-- Ringkasan -->
        <StatStrip :cards="cards" @select="setStatus" />

        <!-- Toolbar: tab status + search -->
        <MonitorToolbar :tabs="tabs" :status="filterStatus" :search="search"
            placeholder="Cari judul tugas…"
            @update:status="setStatus" @update:search="onSearch" />

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/70 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">
                        <th class="px-5 py-3.5">Tugas</th>
                        <th class="px-5 py-3.5 hidden md:table-cell">Periode</th>
                        <th class="px-5 py-3.5">Progres Verifikasi</th>
                        <th class="px-5 py-3.5 text-right hidden lg:table-cell">Vakasi</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tugas.data" :key="t.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-4">
                            <div class="flex items-start gap-2">
                                <span v-if="t.menunggu > 0" class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"
                                    title="Ada yang perlu diverifikasi"></span>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ t.judul }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span :class="['text-xs px-2 py-0.5 rounded-lg font-medium', badgeTipe(t.tipe).class]">
                                            {{ badgeTipe(t.tipe).label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 hidden md:table-cell">
                            <p class="text-xs text-gray-600 tabular-nums">{{ t.tanggal_mulai }}</p>
                            <p class="text-xs text-gray-400 tabular-nums" v-if="t.tanggal_selesai">s/d {{ t.tanggal_selesai }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <ProgressMini :done="t.disetujui" :total="t.total_penerima" tone="emerald"
                                :label="`${t.total_penerima} guru`" />
                            <span v-if="t.menunggu > 0"
                                class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-700">
                                <MonitorIcon name="clock" size="sm" class="!w-3 !h-3" /> {{ t.menunggu }} perlu verifikasi
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right hidden lg:table-cell">
                            <p v-if="t.nominal_vakasi > 0" class="text-sm font-bold text-indigo-700 tabular-nums">{{ formatRp(t.nominal_vakasi) }}</p>
                            <p v-else class="text-xs text-gray-400">Tanpa vakasi</p>
                            <p v-if="t.setting_vakasi" class="text-xs text-gray-400 mt-0.5">{{ t.setting_vakasi }}</p>
                        </td>
                        <td class="px-5 py-4"><StatusBadge :status="t.status" /></td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1">
                                <Link :href="route('admin.smart-payroll.tugas-tambahan.show', t.id)" title="Lihat detail"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"><MonitorIcon name="eye" size="sm" /></Link>
                                <Link v-if="t.status !== 'dibatalkan'" :href="route('admin.smart-payroll.tugas-tambahan.edit', t.id)" title="Edit"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"><MonitorIcon name="pencil" size="sm" /></Link>
                                <button v-if="t.status !== 'dibatalkan'" @click="batalkan(t.id, t.judul)" title="Batalkan tugas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"><MonitorIcon name="trash" size="sm" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!tugas.data?.length">
                        <td colspan="6" class="py-16 text-center">
                            <MonitorIcon name="clipboard" size="lg" class="mx-auto text-gray-300 mb-2" />
                            <p class="text-sm text-gray-400">Tidak ada tugas pada filter ini.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- Pagination -->
            <div v-if="tugas.links?.length > 3" class="flex flex-wrap gap-1 justify-center px-4 py-3 border-t border-gray-100">
                <component v-for="(l, i) in tugas.links" :key="i" :is="l.url ? Link : 'span'" :href="l.url || undefined"
                    v-html="l.label"
                    :class="['px-3 py-1.5 rounded-lg text-xs font-medium',
                        l.active ? 'bg-indigo-600 text-white' : l.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300']" />
            </div>
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
    tugas:   { type: Object, default: () => ({ data: [], links: [] }) },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ status: '', q: '' }) },
})

const filterStatus = ref(props.filters.status ?? '')
const search = ref(props.filters.q ?? '')

const cards = computed(() => [
    { label: 'Total Tugas', value: props.summary.total ?? 0, icon: 'clipboard', tone: 'gray', filter: '', active: filterStatus.value === '' },
    { label: 'Sedang Aktif', value: props.summary.aktif ?? 0, icon: 'play', tone: 'emerald', filter: 'aktif', active: filterStatus.value === 'aktif' },
    { label: 'Perlu Verifikasi', value: props.summary.menunggu ?? 0, icon: 'clock', tone: 'amber', filter: 'perlu_verifikasi', active: filterStatus.value === 'perlu_verifikasi' },
    { label: 'Estimasi Vakasi', value: formatRp(props.summary.vakasi ?? 0), icon: 'money', tone: 'indigo' },
])

const tabs = computed(() => [
    { val: '', label: 'Semua', count: props.summary.total ?? 0 },
    { val: 'aktif', label: 'Aktif', count: props.summary.aktif ?? 0 },
    { val: 'selesai', label: 'Selesai', count: props.summary.selesai ?? 0 },
    { val: 'perlu_verifikasi', label: 'Perlu Verifikasi', count: props.summary.menunggu ?? 0 },
])

function reload() {
    router.get(route('admin.smart-payroll.tugas-tambahan.index'),
        { status: filterStatus.value || undefined, q: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true })
}
function setStatus(val) { filterStatus.value = val; reload() }

let timer = null
function onSearch(val) {
    search.value = val
    clearTimeout(timer)
    timer = setTimeout(reload, 350)
}

function batalkan(id, judul) {
    confirm.value.ask(
        { title: 'Batalkan Tugas Tambahan?', message: `Tugas "${judul}" akan dibatalkan.`,
          variant: 'danger', confirmLabel: 'Ya, Batalkan', irreversible: true },
        (done) => router.post(route('admin.smart-payroll.tugas-tambahan.hapus', id), {},
            { preserveScroll: true, onFinish: done }),
    )
}

function badgeTipe(t) {
    return {
        semua: { label: 'Semua Guru', class: 'bg-blue-50 text-blue-700' },
        jabatan: { label: 'Per Jabatan', class: 'bg-violet-50 text-violet-700' },
        individu: { label: 'Per Individu', class: 'bg-teal-50 text-teal-700' },
    }[t] ?? { label: t, class: 'bg-gray-100 text-gray-600' }
}
function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>
