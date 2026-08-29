<template>
    <AdminLayout title="Laporan Kegiatan" subtitle="Guru Piket">
        <Head title="Laporan Kegiatan Penting" />

        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Laporan Kegiatan Penting</h1>
                <p class="text-sm text-gray-400 mt-0.5">Rekap kehadiran guru pada kegiatan wajib per hari.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" v-model="tgl" class="rounded-lg border-gray-200 text-sm" />
                <Link :href="route('admin.smart-payroll.kegiatan-penting.index')" class="text-sm text-indigo-600 font-semibold">Kelola Kegiatan</Link>
            </div>
        </div>

        <div v-if="!laporan.length" class="bg-white rounded-2xl border border-dashed border-gray-200 py-14 text-center text-gray-400">
            Tidak ada kegiatan aktif pada tanggal ini.
        </div>

        <div v-for="keg in laporan" :key="keg.id" class="bg-white rounded-2xl border border-gray-100 mb-4 overflow-hidden">
            <div class="px-5 py-4 flex items-center gap-4 border-b border-gray-50 cursor-pointer" @click="buka = buka === keg.id ? null : keg.id">
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">{{ keg.nama }} <span class="text-xs text-gray-400 font-normal">· {{ keg.jam }} · {{ labelSasaran(keg.sasaran) }}</span></h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ keg.total }} guru diharapkan</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700">Hadir {{ keg.hadir }}</span>
                    <span class="px-2 py-1 rounded-lg bg-red-50 text-red-600">Tidak {{ keg.tidak }}</span>
                    <span v-if="keg.belum" class="px-2 py-1 rounded-lg bg-amber-50 text-amber-600">Belum {{ keg.belum }}</span>
                </div>
                <svg class="w-4 h-4 text-gray-300 transition-transform" :class="buka === keg.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div v-if="buka === keg.id" class="divide-y divide-gray-50">
                <div v-for="p in keg.peserta" :key="p.tenaga_pendidik_id" class="px-5 py-2.5 flex items-center gap-3 text-sm">
                    <span class="flex-1 text-gray-700">{{ p.nama }}</span>
                    <span class="text-[11px] text-gray-400">{{ p.jenis_guru }}</span>
                    <span v-if="!p.hadir_kerja" class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400">tak absen harian</span>
                    <span :class="statusCls(p.status)" class="text-[11px] font-semibold px-2 py-0.5 rounded-md w-24 text-center">
                        {{ p.status === 'hadir' ? ('Hadir' + (p.jam_hadir ? ' ' + p.jam_hadir : '')) : (p.status === 'tidak_hadir' ? 'Tidak hadir' : 'Belum dicatat') }}
                    </span>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    tanggal: { type: String, default: '' },
    laporan: { type: Array, default: () => [] },
})

const tgl = ref(props.tanggal)
const buka = ref(null)

watch(tgl, (v) => {
    router.get(route('admin.smart-payroll.kegiatan-penting.laporan'), { tanggal: v }, { preserveState: true, preserveScroll: true, replace: true })
})

function labelSasaran(s) { return { semua: 'Semua', mukim: 'Mukim', non_mukim: 'Non-mukim' }[s] ?? s }
function statusCls(s) {
    if (s === 'hadir') return 'bg-emerald-50 text-emerald-700'
    if (s === 'tidak_hadir') return 'bg-red-50 text-red-600'
    return 'bg-amber-50 text-amber-600'
}
</script>
