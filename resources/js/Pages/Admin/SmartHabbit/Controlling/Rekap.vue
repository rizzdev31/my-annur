<template>
    <AdminLayout title="Rekap Controlling" subtitle="Smart Habbit">

        <Head title="Rekap Controlling" />

        <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Rekap Controlling</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kehadiran santri (hadir/telat/alpha) & kegiatan berjalan per periode.</p>
            </div>
            <div class="flex items-end gap-2">
                <select v-model.number="f.periode_id" @change="apply" :class="inp">
                    <option v-for="p in periodeList" :key="p.id" :value="p.id">{{ p.nama }}{{ p.is_aktif ? ' (aktif)' : '' }}</option>
                </select>
                <input v-model="f.dari" type="date" @change="apply" :class="inp" title="Dari" />
                <input v-model="f.sampai" type="date" @change="apply" :class="inp" title="Sampai" />
                <a :href="route('admin.smart-habbit.controlling.harian')"
                    class="px-3 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">
                    Detail Harian →
                </a>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-5 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-gray-900">{{ ringkasan.santri }}</p><p class="text-xs text-gray-400 mt-1">Santri</p></div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-emerald-700">{{ ringkasan.hadir }}</p><p class="text-xs text-emerald-500 mt-1">Hadir</p></div>
            <div class="bg-amber-50 rounded-xl border border-amber-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-amber-700">{{ ringkasan.telat }}</p><p class="text-xs text-amber-500 mt-1">Telat</p></div>
            <div class="bg-red-50 rounded-xl border border-red-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-red-600">{{ ringkasan.alpha }}</p><p class="text-xs text-red-400 mt-1">Alpha</p></div>
            <div class="bg-sky-50 rounded-xl border border-sky-100 px-3 py-2.5 text-center"><p class="text-2xl font-bold text-sky-700">{{ ringkasan.izin ?? 0 }}</p><p class="text-xs text-sky-500 mt-1">Izin</p></div>
        </div>

        <!-- Drill-down detail per santri -->
        <div v-if="detailSantri.length || f.santri_id" class="bg-white rounded-2xl border border-indigo-200 overflow-hidden mb-5">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-indigo-50/50">
                <p class="text-sm font-semibold text-indigo-800">
                    Riwayat Harian: {{ selectedNama }}
                    <span class="text-xs font-normal text-gray-400 ml-1">({{ detailSantri.length }} catatan)</span>
                </p>
                <button @click="clearSantri" class="text-xs font-semibold text-gray-500 hover:text-gray-700">✕ Tutup</button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-gray-50/90"><tr class="text-xs text-gray-400 uppercase">
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Kegiatan</th>
                        <th class="px-2 py-2 text-center">Status</th>
                        <th class="px-2 py-2 text-center">Jam</th>
                        <th class="px-2 py-2 text-center">RamahAnak</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(d, i) in detailSantri" :key="i">
                            <td class="px-3 py-2 text-gray-600">{{ d.tanggal }}</td>
                            <td class="px-3 py-2"><span class="font-medium text-gray-700">{{ d.kegiatan }}</span> <span class="text-[10px] text-gray-400">{{ d.jenis }}</span></td>
                            <td class="px-2 py-2 text-center"><span :class="badge(d.status)">{{ d.status }}</span></td>
                            <td class="px-2 py-2 text-center text-gray-500">{{ d.jam_scan || '—' }}</td>
                            <td class="px-2 py-2 text-center">
                                <span v-if="d.status === 'hadir' || d.status === 'izin'" class="text-[10px] text-gray-300">—</span>
                                <span v-else :class="d.terkirim ? 'text-emerald-600' : 'text-amber-500'" :title="d.terkirim ? 'Terkirim ke RamahAnak' : 'Belum terkirim'">
                                    {{ d.terkirim ? '✓ terkirim' : '⏳ antri' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!detailSantri.length"><td colspan="5" class="py-8 text-center text-gray-400 text-sm">Tidak ada catatan untuk santri ini pada rentang dipilih.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            <!-- Per kegiatan -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100"><p class="text-sm font-semibold text-gray-800">Per Kegiatan</p></div>
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                        <th class="px-3 py-2 text-left">Kegiatan</th>
                        <th class="px-2 py-2 text-center">H</th><th class="px-2 py-2 text-center">T</th><th class="px-2 py-2 text-center">A</th><th class="px-2 py-2 text-center">I</th>
                        <th class="px-3 py-2 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="k in perKegiatan" :key="k.kegiatan_id">
                            <td class="px-3 py-2"><p class="font-semibold text-gray-700">{{ k.nama }}</p><span class="text-[10px] text-gray-400">{{ k.jenis }}</span></td>
                            <td class="px-2 py-2 text-center text-emerald-600 font-semibold">{{ k.hadir }}</td>
                            <td class="px-2 py-2 text-center text-amber-600">{{ k.telat }}</td>
                            <td class="px-2 py-2 text-center text-red-500">{{ k.alpha }}</td>
                            <td class="px-2 py-2 text-center text-sky-600">{{ k.izin ?? 0 }}</td>
                            <td class="px-3 py-2 text-center">
                                <span :class="['text-xs font-semibold px-2 py-0.5 rounded', k.hari_berjalan > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                    {{ k.hari_berjalan > 0 ? `Berjalan ${k.hari_berjalan} hari` : 'Belum berjalan' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!perKegiatan.length"><td colspan="6" class="py-10 text-center text-gray-400 text-sm">Belum ada data.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Per santri (klik untuk lihat riwayat) -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-800">Per Santri</p>
                    <span class="text-[10px] text-gray-400">klik baris untuk riwayat</span>
                </div>
                <div class="max-h-[28rem] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50/90"><tr class="text-xs text-gray-400 uppercase">
                            <th class="px-3 py-2 text-left">Santri</th>
                            <th class="px-2 py-2 text-center">H</th><th class="px-2 py-2 text-center">T</th><th class="px-2 py-2 text-center">A</th><th class="px-2 py-2 text-center">I</th>
                            <th class="px-3 py-2 text-center">%Hadir</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="s in perSantri" :key="s.santri_id"
                                @click="pickSantri(s.santri_id)"
                                :class="['cursor-pointer hover:bg-indigo-50/40', f.santri_id === s.santri_id ? 'bg-indigo-50' : '']">
                                <td class="px-3 py-2"><p class="font-semibold text-gray-700">{{ s.nama }}</p><span class="text-[10px] text-gray-400 font-mono">{{ s.nip }}</span></td>
                                <td class="px-2 py-2 text-center text-emerald-600 font-semibold">{{ s.hadir }}</td>
                                <td class="px-2 py-2 text-center text-amber-600">{{ s.telat }}</td>
                                <td class="px-2 py-2 text-center text-red-500">{{ s.alpha }}</td>
                                <td class="px-2 py-2 text-center text-sky-600">{{ s.izin ?? 0 }}</td>
                                <td class="px-3 py-2 text-center font-semibold" :class="s.pct_hadir >= 80 ? 'text-emerald-600' : s.pct_hadir >= 50 ? 'text-amber-600' : 'text-red-500'">{{ s.pct_hadir }}%</td>
                            </tr>
                            <tr v-if="!perSantri.length"><td colspan="6" class="py-10 text-center text-gray-400 text-sm">Belum ada data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    periodeList: { type: Array, default: () => [] },
    periodeId: { type: Number, default: null },
    filter: { type: Object, default: () => ({}) },
    perSantri: { type: Array, default: () => [] },
    perKegiatan: { type: Array, default: () => [] },
    detailSantri: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const f = reactive({
    periode_id: props.periodeId,
    dari: props.filter.dari ?? '',
    sampai: props.filter.sampai ?? '',
    santri_id: props.filter.santri_id ?? null,
})

const selectedNama = computed(() =>
    props.perSantri.find(s => s.santri_id === f.santri_id)?.nama ?? 'Santri')

const apply = () => router.get(route('admin.smart-habbit.controlling.rekap'), { ...f }, { preserveState: true, preserveScroll: true })
const pickSantri = (id) => { f.santri_id = id; apply() }
const clearSantri = () => { f.santri_id = null; apply() }

const badge = (s) => ({
    hadir: 'text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700',
    telat: 'text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-700',
    alpha: 'text-xs font-semibold px-2 py-0.5 rounded bg-red-50 text-red-600',
    izin: 'text-xs font-semibold px-2 py-0.5 rounded bg-sky-50 text-sky-700',
}[s] ?? 'text-xs text-gray-500')

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>
