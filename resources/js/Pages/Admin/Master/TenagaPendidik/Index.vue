<template>
    <AdminLayout title="Tenaga Pendidik" subtitle="Master Data">

        <Head title="Tenaga Pendidik" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Tenaga Pendidik</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kelola data seluruh tenaga pendidik</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <ImportExcel :template-url="route('admin.master.tenaga-pendidik.template-import')"
                    :import-url="route('admin.master.tenaga-pendidik.import')" label="Import Guru" />
                <Link :href="route('admin.master.tenaga-pendidik.create')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Pendidik
                </Link>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div v-for="s in summaryCards" :key="s.label"
                class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', s.bg]">
                    <span class="text-base">{{ s.icon }}</span>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900 leading-none">{{ s.value }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ s.label }}</p>
                </div>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="bg-white rounded-2xl border border-gray-200 mb-4">
            <div class="flex flex-col sm:flex-row gap-3 p-4">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input v-model="search" type="text" placeholder="Cari nama, NIP, atau email..." @input="doSearch"
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all" />
                </div>

                <select v-model="filterJabatan" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[160px]">
                    <option value="">Semua Jabatan</option>
                    <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
                </select>

                <select v-model="filterJenis" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[140px]">
                    <option value="">Semua Jenis</option>
                    <option value="mukim">Mukim</option>
                    <option value="non_mukim">Non Mukim</option>
                </select>

                <select v-model="filterStatus" @change="doFilter"
                    class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white min-w-[170px]">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="cuti">Cuti</option>
                    <option value="cuti_sakit">Cuti Sakit</option>
                    <option value="nonaktif_sementara">Nonaktif Sementara</option>
                    <option value="resign">Resign</option>
                    <option value="pensiun">Pensiun</option>
                    <option value="meninggal">Meninggal</option>
                </select>
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
                                Pendidik</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                                NIP</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                Jabatan</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">
                                Jenis</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">
                                Masuk</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Status</th>
                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="guru in tenagaPendidik.data" :key="guru.id"
                            class="hover:bg-gray-50/50 transition-colors">

                            <!-- Nama + Foto -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="relative shrink-0">
                                        <img v-if="guru.foto" :src="guru.foto" :alt="guru.nama"
                                            class="w-9 h-9 rounded-full object-cover ring-2 ring-gray-100" />
                                        <div v-else
                                            class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center ring-2 ring-gray-100">
                                            <span class="text-white text-sm font-bold">{{ guru.nama?.charAt(0) }}</span>
                                        </div>
                                        <span :class="[
                                            'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white',
                                            statusDotColor(guru.status_kepegawaian)
                                        ]"></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">{{ guru.nama }}</p>
                                        <p class="text-xs text-gray-400">{{ guru.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-3.5 hidden md:table-cell">
                                <span class="text-sm text-gray-600 font-mono">{{ guru.nip }}</span>
                            </td>

                            <td class="px-5 py-3.5 hidden lg:table-cell">
                                <!-- Multi jabatan badge -->
                                <div class="flex flex-wrap gap-1">
                                    <template v-if="guru.jabatan_aktif?.length">
                                        <span v-for="j in guru.jabatan_aktif" :key="j.pivot_id" :class="[
                                            'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium',
                                            j.adalah_utama ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'
                                        ]">
                                            <span v-if="guru.is_rangkap && j.adalah_utama"
                                                class="w-1 h-1 rounded-full bg-indigo-500 shrink-0"></span>
                                            {{ j.nama_jabatan }}
                                        </span>
                                    </template>
                                    <span v-else
                                        class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-medium">
                                        {{ guru.jabatan }}
                                    </span>
                                </div>
                                <p v-if="guru.is_rangkap" class="text-xs text-amber-600 mt-1 font-medium">
                                    Rangkap jabatan
                                </p>
                            </td>

                            <td class="px-5 py-3.5 hidden xl:table-cell">
                                <span :class="[
                                    'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium',
                                    guru.jenis_guru === 'mukim' ? 'bg-violet-50 text-violet-700' : 'bg-amber-50 text-amber-700'
                                ]">
                                    {{ guru.jenis_guru === 'mukim' ? 'Mukim' : 'Non Mukim' }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 hidden xl:table-cell text-sm text-gray-500">
                                {{ guru.tanggal_masuk }}
                            </td>

                            <!-- Status Kepegawaian — klik untuk ubah -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col gap-1">
                                    <button @click="bukaModalStatus(guru)" :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-all hover:opacity-75 cursor-pointer w-fit',
                                        guru.status_badge?.class ?? 'bg-gray-100 text-gray-600'
                                    ]" :title="'Klik untuk ubah status ' + guru.nama">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                        {{ guru.status_badge?.label ?? 'Aktif' }}
                                        <!-- Icon pensil kecil -->
                                        <svg class="w-2.5 h-2.5 opacity-60 ml-0.5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <!-- Alasan nonaktif -->
                                    <p v-if="guru.alasan_nonaktif && guru.status_kepegawaian !== 'aktif'"
                                        class="text-xs text-gray-400 truncate max-w-[130px]"
                                        :title="guru.alasan_nonaktif">
                                        {{ guru.alasan_nonaktif }}
                                    </p>
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">

                                    <!-- Aktifkan Kembali (hanya jika sementara & bukan aktif) -->
                                    <button v-if="guru.bisa_aktif_kembali && guru.status_kepegawaian !== 'aktif'"
                                        @click="bukaModalAktifkan(guru)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                                        title="Aktifkan Kembali">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>

                                    <Link :href="route('admin.master.tenaga-pendidik.show', guru.id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                        title="Lihat Detail">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>

                                    <Link :href="route('admin.master.tenaga-pendidik.edit', guru.id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                        title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>

                                    <button @click="confirmDelete(guru)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Hapus">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!tenagaPendidik.data?.length">
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center">
                                        <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Belum ada tenaga pendidik</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Klik "Tambah Pendidik" untuk menambahkan
                                            data</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="tenagaPendidik.last_page > 1"
                class="flex items-center justify-between px-5 py-3.5 border-t border-gray-100">
                <p class="text-xs text-gray-400">
                    Menampilkan {{ tenagaPendidik.from }}–{{ tenagaPendidik.to }} dari {{ tenagaPendidik.total }} data
                </p>
                <div class="flex items-center gap-1">
                    <Link v-for="link in tenagaPendidik.links" :key="link.label" :href="link.url ?? '#'" :class="[
                        'px-3 py-1.5 text-xs rounded-lg transition-colors',
                        link.active ? 'bg-indigo-600 text-white font-semibold' : 'text-gray-500 hover:bg-gray-100',
                        !link.url ? 'opacity-40 pointer-events-none' : ''
                    ]" v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- ── Modal Ubah Status ──────────────────────────────────────────── -->
        <ModalUbahStatus :show="showStatusModal" :guru="statusTarget" @close="showStatusModal = false"
            @success="router.reload()" />

        <!-- ── Modal Aktifkan Kembali ─────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showAktifkanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAktifkanModal = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Aktifkan Kembali</h3>
                            <p class="text-xs text-gray-400">{{ aktifkanTarget?.nama }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-4">
                        Status saat ini:
                        <span
                            :class="['px-2 py-0.5 rounded-md text-xs font-medium ml-1', aktifkanTarget?.status_badge?.class]">
                            {{ aktifkanTarget?.status_badge?.label }}
                        </span>
                    </p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan Pengaktifan <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="alasanAktifkan" rows="3"
                            placeholder="Masa cuti selesai / kondisi sudah membaik..."
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 resize-none">
                </textarea>
                    </div>

                    <div class="flex gap-3">
                        <button @click="showAktifkanModal = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="doAktifkan" :disabled="!alasanAktifkan || aktifkanLoading"
                            class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                            {{ aktifkanLoading ? 'Memproses...' : 'Aktifkan' }}
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
import ImportExcel from '@/Components/ImportExcel.vue'
import ModalUbahStatus from '@/Components/ModalUbahStatus.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    tenagaPendidik: { type: Object, required: true },
    jabatan: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

// ── Filter ────────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '')
const filterJabatan = ref(props.filters.jabatan_id ?? '')
const filterJenis = ref(props.filters.jenis_guru ?? '')
const filterStatus = ref(props.filters.status_kepegawaian ?? '')

const summaryCards = computed(() => [
    { label: 'Total', value: props.summary.total ?? 0, icon: '👥', bg: 'bg-indigo-50' },
    { label: 'Aktif', value: props.summary.aktif ?? 0, icon: '✅', bg: 'bg-emerald-50' },
    { label: 'Cuti/Nonaktif', value: props.summary.cuti ?? 0, icon: '⏸️', bg: 'bg-amber-50' },
    { label: 'Resign/Pensiun', value: props.summary.resign_pensiun ?? 0, icon: '📤', bg: 'bg-gray-100' },
])

// ── Filter & Search ───────────────────────────────────────────────────────────
let searchTimeout = null
function doSearch() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(doFilter, 400)
}

function doFilter() {
    router.get(route('admin.master.tenaga-pendidik.index'), {
        search: search.value || undefined,
        jabatan_id: filterJabatan.value || undefined,
        jenis_guru: filterJenis.value || undefined,
        status_kepegawaian: filterStatus.value || undefined,
    }, { preserveState: true, replace: true })
}

// ── Status dot ────────────────────────────────────────────────────────────────
function statusDotColor(status) {
    return {
        aktif: 'bg-emerald-400',
        cuti: 'bg-amber-400',
        cuti_sakit: 'bg-blue-400',
        nonaktif_sementara: 'bg-orange-400',
        resign: 'bg-red-400',
        pensiun: 'bg-gray-400',
        meninggal: 'bg-gray-500',
    }[status] ?? 'bg-gray-300'
}

// ── Modal Ubah Status ─────────────────────────────────────────────────────────
const showStatusModal = ref(false)
const statusTarget = ref(null)

function bukaModalStatus(guru) {
    statusTarget.value = guru
    showStatusModal.value = true
}

// ── Modal Aktifkan Kembali ────────────────────────────────────────────────────
const showAktifkanModal = ref(false)
const aktifkanTarget = ref(null)
const alasanAktifkan = ref('')
const aktifkanLoading = ref(false)

function bukaModalAktifkan(guru) {
    aktifkanTarget.value = guru
    alasanAktifkan.value = ''
    showAktifkanModal.value = true
}

function doAktifkan() {
    if (!alasanAktifkan.value) return
    aktifkanLoading.value = true
    router.patch(
        route('admin.master.tenaga-pendidik.aktifkan', aktifkanTarget.value.id),
        { alasan: alasanAktifkan.value },
        {
            onSuccess: () => { showAktifkanModal.value = false; router.reload() },
            onFinish: () => { aktifkanLoading.value = false },
        }
    )
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function confirmDelete(guru) {
    if (!(await confirm({
        title: `Hapus ${guru.nama}?`,
        message: 'Data tenaga pendidik ini akan dihapus permanen.',
        details: ['Jika memiliki riwayat penggajian, gunakan fitur Resign — bukan hapus.'],
        variant: 'danger',
        irreversible: true,
        confirmLabel: 'Ya, Hapus',
    }))) return
    router.delete(route('admin.master.tenaga-pendidik.destroy', guru.id), { preserveScroll: true })
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