<template>
    <AdminLayout title="Pengajuan Izin" subtitle="Smart Payroll">

        <Head title="Pengajuan Izin" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Pengajuan Izin</h2>
                <p class="text-sm text-gray-400 mt-0.5">Monitor dan proses semua pengajuan tenaga pendidik</p>
            </div>
            <button @click="showModalBuat = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Input Manual
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 text-base">⏳</div>
                <div>
                    <p class="text-xl font-bold text-amber-700 leading-none">{{ summary.pending }}</p>
                    <p class="text-xs text-amber-600 mt-0.5">Menunggu Review</p>
                </div>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0 text-base">✅
                </div>
                <div>
                    <p class="text-xl font-bold text-emerald-700 leading-none">{{ summary.disetujui }}</p>
                    <p class="text-xs text-emerald-600 mt-0.5">Disetujui Bulan Ini</p>
                </div>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0 text-base">📋
                </div>
                <div>
                    <p class="text-xl font-bold text-indigo-700 leading-none">{{ summary.bulan_ini }}</p>
                    <p class="text-xs text-indigo-600 mt-0.5">Total Bulan Ini</p>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl border border-gray-200 mb-4">
            <div class="flex flex-wrap gap-3 p-4">
                <select v-model="filterStatus" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[150px]">
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>

                <select v-model="filterJenis" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[150px]">
                    <option value="">Semua Jenis</option>
                    <option v-for="j in jenisList" :key="j.id" :value="j.id">{{ j.nama }}</option>
                </select>

                <select v-model="filterBulan" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[140px]">
                    <option value="">Semua Bulan</option>
                    <option v-for="b in bulanList" :key="b.value" :value="b.value">{{ b.label }}</option>
                </select>

                <select v-model="filterTahun" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[120px]">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>

                <button v-if="hasFilter" @click="resetFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-500 hover:bg-gray-50 transition-colors">
                    Reset filter
                </button>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Pengaju</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Jenis</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                Tanggal</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">
                                Alasan</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Status</th>
                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in pengajuan.data" :key="p.id"
                            :class="['hover:bg-gray-50/50 transition-colors', p.status === 'pending' ? 'bg-amber-50/30' : '']">

                            <!-- Pengaju -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <img v-if="p.foto_guru" :src="p.foto_guru"
                                        class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-100 shrink-0" />
                                    <div v-else
                                        class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center shrink-0">
                                        <span class="text-white text-xs font-bold">{{ p.nama_guru?.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">{{ p.nama_guru }}</p>
                                        <p class="text-xs text-gray-400">{{ p.jabatan }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Jenis -->
                            <td class="px-5 py-3.5">
                                <span
                                    :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium', p.jenis_badge.class]">
                                    {{ p.jenis }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">{{ p.jumlah_hari }} hari kerja</p>
                            </td>

                            <!-- Tanggal -->
                            <td class="px-5 py-3.5 hidden lg:table-cell">
                                <p class="text-sm text-gray-700 font-medium">{{ p.tanggal_mulai }}</p>
                                <p v-if="p.tanggal_mulai !== p.tanggal_selesai" class="text-xs text-gray-400">
                                    s.d. {{ p.tanggal_selesai }}
                                </p>
                            </td>

                            <!-- Alasan -->
                            <td class="px-5 py-3.5 hidden xl:table-cell">
                                <p class="text-sm text-gray-600 max-w-[200px] truncate" :title="p.alasan">
                                    {{ p.alasan }}
                                </p>
                                <a v-if="p.file_dokumen" :href="p.file_dokumen" target="_blank"
                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline mt-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    {{ p.nama_dokumen || 'Lihat dokumen' }}
                                </a>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5">
                                <span
                                    :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium', p.status_badge.class]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                    {{ p.status_badge.label }}
                                </span>
                                <p v-if="p.diproses_oleh" class="text-xs text-gray-400 mt-1">
                                    {{ p.diproses_oleh }}
                                </p>
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">

                                    <!-- Setujui (pending only) -->
                                    <button v-if="p.status === 'pending'" @click="bukaModalSetujui(p)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-medium hover:bg-emerald-100 transition-colors">
                                        ✓ Setujui
                                    </button>

                                    <!-- Tolak (pending only) -->
                                    <button v-if="p.status === 'pending'" @click="bukaModalTolak(p)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-50 text-red-700 text-xs font-medium hover:bg-red-100 transition-colors">
                                        ✕ Tolak
                                    </button>

                                    <!-- Detail -->
                                    <Link :href="route('admin.smart-payroll.pengajuan-izin.show', p.id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!pengajuan.data?.length">
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div
                                        class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center text-2xl">
                                        📋</div>
                                    <p class="text-sm text-gray-500">Tidak ada pengajuan</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pengajuan.last_page > 1"
                class="flex items-center justify-between px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400">
                    {{ pengajuan.from }}–{{ pengajuan.to }} dari {{ pengajuan.total }}
                </p>
                <div class="flex gap-1">
                    <Link v-for="link in pengajuan.links" :key="link.label" :href="link.url ?? '#'" :class="['px-3 py-1.5 text-xs rounded-lg transition-colors',
                        link.active ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100',
                        !link.url ? 'opacity-40 pointer-events-none' : '']" v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- ── Modal Input Manual ─────────────────────────────────────────── -->
        <ModalInputManual :show="showModalBuat" :guru-list="guruList" :jenis-list="jenisList"
            @close="showModalBuat = false" @success="router.reload()" />
        <Transition name="modal">
            <div v-if="showModalSetujui" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalSetujui = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <div class="bg-emerald-50 border-b border-emerald-100 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Setujui Pengajuan</h3>
                                <p class="text-xs text-gray-500">{{ aksiTarget?.nama_guru }} · {{ aksiTarget?.jenis }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-5">
                        <div class="bg-gray-50 rounded-xl p-4 mb-4 text-sm text-gray-600 space-y-1.5">
                            <p>📅 <span class="font-medium">{{ aksiTarget?.tanggal_mulai }}</span>
                                <span v-if="aksiTarget?.tanggal_mulai !== aksiTarget?.tanggal_selesai"> s.d. {{
                                    aksiTarget?.tanggal_selesai }}</span>
                                <span class="text-gray-400 ml-1">({{ aksiTarget?.jumlah_hari }} hari kerja)</span>
                            </p>
                            <p>📝 {{ aksiTarget?.alasan }}</p>
                        </div>
                        <p class="text-sm text-indigo-600 bg-indigo-50 rounded-xl px-4 py-3 mb-4">
                            ℹ️ Absensi <strong>{{ aksiTarget?.jumlah_hari }} hari</strong> akan diupdate otomatis
                            setelah disetujui.
                        </p>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Catatan (opsional)
                        </label>
                        <textarea v-model="catatanSetujui" rows="2" placeholder="Catatan untuk guru..."
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 resize-none">
                        </textarea>
                    </div>
                    <div class="flex gap-3 px-6 pb-5">
                        <button @click="showModalSetujui = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="doSetujui" :disabled="loading"
                            class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Memproses...' : 'Ya, Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ── Modal Tolak ────────────────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showModalTolak" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalTolak = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <div class="bg-red-50 border-b border-red-100 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Tolak Pengajuan</h3>
                                <p class="text-xs text-gray-500">{{ aksiTarget?.nama_guru }} · {{ aksiTarget?.jenis }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan Penolakan <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="catatanTolak" rows="3" placeholder="Tulis alasan penolakan..."
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 resize-none">
                        </textarea>
                        <p v-if="!catatanTolak && errorTolak" class="text-xs text-red-500 mt-1">
                            Alasan penolakan wajib diisi.
                        </p>
                    </div>
                    <div class="flex gap-3 px-6 pb-5">
                        <button @click="showModalTolak = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="doTolak" :disabled="loading"
                            class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Memproses...' : 'Ya, Tolak' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ModalInputManual from '@/Components/ModalInputManual.vue'

const props = defineProps({
    pengajuan: { type: Object, required: true },
    jenisList: { type: Array, default: () => [] },
    guruList: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

// ── Filter ────────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '')
const filterStatus = ref(props.filters.status ?? '')
const filterJenis = ref(props.filters.jenis_id ?? '')
const filterBulan = ref(props.filters.bulan ?? '')
const filterTahun = ref(props.filters.tahun ?? new Date().getFullYear())

// ── Modal Input Manual ────────────────────────────────────────────────────────
const showModalBuat = ref(false)

const tahunList = computed(() => {
    const y = new Date().getFullYear()
    return [y, y - 1, y - 2]
})

const bulanList = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

const hasFilter = computed(() => filterStatus.value || filterJenis.value || filterBulan.value)

function doFilter() {
    router.get(route('admin.smart-payroll.pengajuan-izin.index'), {
        status: filterStatus.value || undefined,
        jenis_id: filterJenis.value || undefined,
        bulan: filterBulan.value || undefined,
        tahun: filterTahun.value || undefined,
    }, { preserveState: true, replace: true })
}

function resetFilter() {
    filterStatus.value = ''
    filterJenis.value = ''
    filterBulan.value = ''
    doFilter()
}

// ── Modal Setujui ─────────────────────────────────────────────────────────────
const showModalSetujui = ref(false)
const catatanSetujui = ref('')
const aksiTarget = ref(null)
const loading = ref(false)

function bukaModalSetujui(p) {
    aksiTarget.value = p
    catatanSetujui.value = ''
    showModalSetujui.value = true
}

function doSetujui() {
    loading.value = true
    router.post(
        route('admin.smart-payroll.pengajuan-izin.setujui', aksiTarget.value.id),
        { catatan: catatanSetujui.value },
        {
            onSuccess: () => { showModalSetujui.value = false },
            onFinish: () => { loading.value = false },
        }
    )
}

// ── Modal Tolak ───────────────────────────────────────────────────────────────
const showModalTolak = ref(false)
const catatanTolak = ref('')
const errorTolak = ref(false)

function bukaModalTolak(p) {
    aksiTarget.value = p
    catatanTolak.value = ''
    errorTolak.value = false
    showModalTolak.value = true
}

function doTolak() {
    if (!catatanTolak.value) { errorTolak.value = true; return }
    loading.value = true
    router.post(
        route('admin.smart-payroll.pengajuan-izin.tolak', aksiTarget.value.id),
        { catatan: catatanTolak.value },
        {
            onSuccess: () => { showModalTolak.value = false },
            onFinish: () => { loading.value = false },
        }
    )
}
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.2s ease;
}

.modal-leave-active {
    transition: all 0.15s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>