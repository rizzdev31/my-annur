<template>
    <AdminLayout :title="tugas.nama_tugas" subtitle="Tugas Jabatan">

        <Head :title="tugas.nama_tugas" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.tugas-jabatan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">{{ tugas.nama_tugas }}</h2>
                    <span
                        :class="['px-2.5 py-1 rounded-full text-xs font-semibold', tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700']">
                        {{ tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'Absen Kegiatan' : 'Mandiri' }}
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                        {{ tugas.frekuensi_label }}
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ tugas.jabatan }} · {{ tugas.deskripsi ?? 'Tidak ada deskripsi' }}
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.tugas-jabatan.edit', tugas.id)"
                class="px-4 py-2 bg-indigo-50 text-indigo-600 text-sm font-semibold rounded-xl hover:bg-indigo-100">
                Edit Tugas
            </Link>
        </div>

        <!-- Filter bulan/tahun -->
        <div class="flex items-center gap-3 mb-5">
            <select v-model="filterBulan" @change="reload"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-400 bg-white">
                <option v-for="b in bulanList" :key="b.val" :value="b.val">{{ b.label }}</option>
            </select>
            <select v-model="filterTahun" @change="reload"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-400 bg-white">
                <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
            </select>
            <span class="text-xs text-gray-400">Filter periode realisasi</span>
        </div>

        <!-- Stat cards -->
        <StatStrip :cards="statCards" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kiri: Guru Penerima -->
            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">
                        Guru Penerima
                        <span class="text-gray-400 font-normal">({{ guru_penerima.length }} orang)</span>
                    </p>
                    <div class="space-y-2">
                        <div v-for="g in guru_penerima" :key="g.id"
                            class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-gray-50">
                            <img v-if="g.foto" :src="g.foto" class="w-8 h-8 rounded-full object-cover" />
                            <div v-else
                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                {{ g.nama?.charAt(0) }}
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">{{ g.nama }}</p>
                            </div>
                            <span
                                :class="['text-xs font-semibold px-2 py-0.5 rounded-lg', g.sudah_realisasi ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400']">
                                {{ g.sudah_realisasi ? 'Sudah' : 'Belum' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Info vakasi (jika absen_kegiatan) -->
                <div v-if="tugas.tipe_pengerjaan === 'absen_kegiatan' && tugas.nominal_vakasi > 0"
                    class="bg-white rounded-2xl border border-violet-200 overflow-hidden">
                    <div class="bg-violet-600 px-5 py-3">
                        <p class="text-sm font-semibold text-white">Skema Vakasi</p>
                    </div>
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex items-center gap-2">
                            <MonitorIcon name="user" size="sm" class="text-violet-500" />
                            <span class="text-gray-700">Pengabsen: <strong class="text-violet-700">{{
                                    formatRp(tugas.nominal_vakasi) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <MonitorIcon name="users" size="sm" class="text-violet-500" />
                            <span class="text-gray-700">Peserta hadir: <strong class="text-violet-700">{{
                                    formatRp(tugas.nominal_vakasi) }}</strong>/orang</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kanan: Tab Realisasi / Kegiatan -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Tab header -->
                <div class="flex gap-2 border-b border-gray-200">
                    <button @click="activeTab = 'realisasi'"
                        :class="['pb-3 px-1 text-sm font-semibold border-b-2 transition-colors -mb-px', activeTab === 'realisasi' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600']">
                        Realisasi & Bukti
                        <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{
                            realisasi.length }}</span>
                    </button>
                    <button v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'" @click="activeTab = 'kegiatan'"
                        :class="['pb-3 px-1 text-sm font-semibold border-b-2 transition-colors -mb-px', activeTab === 'kegiatan' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-400 hover:text-gray-600']">
                        Kegiatan Absensi
                        <span class="ml-1.5 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{
                            kegiatan.length }}</span>
                    </button>
                </div>

                <!-- Tab Realisasi -->
                <div v-if="activeTab === 'realisasi'"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                                <th class="px-4 py-3">Guru</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Bukti</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in realisasi" :key="r.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <img v-if="r.guru_foto" :src="r.guru_foto"
                                            class="w-7 h-7 rounded-full object-cover" />
                                        <div v-else
                                            class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                            {{ r.guru_nama?.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800">{{ r.guru_nama }}</p>
                                            <p class="text-xs text-gray-400">{{ r.guru_jabatan }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 font-mono">{{ r.tanggal }}</td>
                                <td class="px-4 py-3">
                                    <div v-if="r.bukti_tipe" class="flex items-center gap-1.5">
                                        <MonitorIcon size="sm" class="text-gray-400 shrink-0"
                                            :name="r.bukti_tipe === 'foto' ? 'camera' : r.bukti_tipe === 'link' ? 'link' : 'document'" />
                                        <a v-if="r.bukti_tipe === 'link'" :href="r.link_bukti" target="_blank"
                                            class="text-xs text-indigo-600 hover:underline">Lihat Link</a>
                                        <button v-else-if="r.bukti_tipe === 'foto' && r.file_bukti_url"
                                            @click="lihatFoto(r.file_bukti_url, r.guru_nama)"
                                            class="text-xs text-indigo-600 hover:underline">Lihat Foto</button>
                                        <span v-else class="text-xs text-gray-500 truncate max-w-24">{{ r.teks_bukti ??
                                            r.keterangan }}</span>
                                    </div>
                                    <span v-else class="text-gray-300 text-xs">Belum ada bukti</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="r.disetujui === true"
                                        class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700">Disetujui</span>
                                    <span v-else-if="r.disetujui === false"
                                        class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-red-100 text-red-600">Ditolak</span>
                                    <span v-else
                                        class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-amber-100 text-amber-700">Pending</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="r.disetujui === null" class="flex gap-1">
                                        <button @click="verifikasi(r.id, true)"
                                            class="px-2.5 py-1 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700">Setujui</button>
                                        <button @click="verifikasi(r.id, false)"
                                            class="px-2.5 py-1 bg-red-500 text-white text-xs font-semibold rounded-lg hover:bg-red-600">Tolak</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!realisasi.length">
                                <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                                    <MonitorIcon name="clipboard" size="lg" class="mx-auto text-gray-300 mb-2" />
                                    Belum ada realisasi di periode ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tab Kegiatan -->
                <div v-if="activeTab === 'kegiatan' && tugas.tipe_pengerjaan === 'absen_kegiatan'"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                                <th class="px-4 py-3">Nama Kegiatan</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Pengabsen</th>
                                <th class="px-4 py-3 text-center">Hadir/Total</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="k in kegiatan" :key="k.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ k.nama_kegiatan }}</p>
                                    <p v-if="k.lokasi" class="text-xs text-gray-400 flex items-center gap-1"><MonitorIcon name="location" size="sm" class="!w-3 !h-3" /> {{ k.lokasi }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono text-gray-600">
                                    {{ k.tanggal_kegiatan }}
                                    <span v-if="k.jam_mulai" class="block text-gray-400">{{ k.jam_mulai }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-700">{{ k.pengabsen }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-emerald-600">{{ k.hadir }}</span>
                                    <span class="text-gray-300 mx-0.5">/</span>
                                    <span class="text-gray-500 text-xs">{{ k.total_peserta }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="['px-2 py-0.5 rounded-lg text-xs font-semibold', k.status === 'selesai' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700']">
                                        {{ k.status === 'selesai' ? 'Selesai' : 'Berlangsung' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link :href="route('admin.smart-payroll.absensi-kegiatan.show', k.id)"
                                        class="text-xs text-indigo-600 hover:underline font-medium">Detail</Link>
                                </td>
                            </tr>
                            <tr v-if="!kegiatan.length">
                                <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                                    <MonitorIcon name="clipboard" size="lg" class="mx-auto text-gray-300 mb-2" />
                                    Belum ada kegiatan absensi untuk tugas ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal foto -->
        <Teleport to="body">
            <div v-if="fotoModal.url" @click.self="fotoModal.url = null"
                class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                <div class="max-w-lg w-full">
                    <p class="text-white text-sm text-center mb-2 opacity-80">{{ fotoModal.title }}</p>
                    <img :src="fotoModal.url" class="w-full rounded-2xl shadow-2xl max-h-[75vh] object-contain" />
                    <button @click="fotoModal.url = null"
                        class="block mx-auto mt-4 text-white/70 hover:text-white">Tutup</button>
                </div>
            </div>
        </Teleport>

        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'
import StatStrip from '@/Components/Monitor/StatStrip.vue'
import MonitorIcon from '@/Components/Monitor/MonitorIcon.vue'

const confirm = ref(null)

const props = defineProps({
    tugas: { type: Object, default: () => ({}) },
    realisasi: { type: Array, default: () => [] },
    kegiatan: { type: Array, default: () => [] },
    guru_penerima: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: () => new Date().getMonth() + 1 },
    tahun: { type: Number, default: () => new Date().getFullYear() },
})

const activeTab = ref('realisasi')
const filterBulan = ref(props.bulan)
const filterTahun = ref(props.tahun)
const fotoModal = ref({ url: null, title: '' })

const bulanList = [
    { val: 1, label: 'Januari' }, { val: 2, label: 'Februari' },
    { val: 3, label: 'Maret' }, { val: 4, label: 'April' },
    { val: 5, label: 'Mei' }, { val: 6, label: 'Juni' },
    { val: 7, label: 'Juli' }, { val: 8, label: 'Agustus' },
    { val: 9, label: 'September' }, { val: 10, label: 'Oktober' },
    { val: 11, label: 'November' }, { val: 12, label: 'Desember' },
]

const tahunList = computed(() => {
    const y = new Date().getFullYear()
    return [y - 1, y, y + 1]
})

const statCards = computed(() => [
    { label: 'Total Guru', value: props.stats.total_guru ?? 0, icon: 'users', tone: 'gray' },
    { label: 'Sudah Realisasi', value: props.stats.sudah_realisasi ?? 0, icon: 'clipboard', tone: 'indigo' },
    { label: 'Disetujui', value: props.stats.disetujui ?? 0, icon: 'check', tone: 'emerald' },
    { label: 'Pending Verifikasi', value: props.stats.pending ?? 0, icon: 'clock', tone: 'amber' },
])

function verifikasi(id, disetujui) {
    confirm.value.ask(
        disetujui
            ? { title: 'Setujui Realisasi?', message: 'Realisasi tugas jabatan ini akan disetujui.',
                variant: 'success', confirmLabel: 'Ya, Setujui' }
            : { title: 'Tolak Realisasi?', message: 'Realisasi tugas jabatan ini akan ditolak.',
                variant: 'danger', confirmLabel: 'Ya, Tolak' },
        (done) => router.post(route('admin.smart-payroll.tugas-jabatan.verifikasi', id),
            { disetujui }, { preserveScroll: true, onFinish: done }),
    )
}

function lihatFoto(url, nama) { fotoModal.value = { url, title: 'Bukti — ' + nama } }

function reload() {
    router.get(route('admin.smart-payroll.tugas-jabatan.show', props.tugas.id),
        { bulan: filterBulan.value, tahun: filterTahun.value },
        { preserveScroll: true }
    )
}

function formatRp(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0)
}
</script>