<template>
    <AdminLayout title="Absensi Harian" subtitle="Smart Payroll">

        <Head title="Absensi Harian" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Absensi Harian</h2>
                <p class="text-sm text-gray-400 mt-0.5">{{ hari }}</p>
            </div>
            <div class="flex items-center gap-2">
                <input v-model="fTanggal" type="date" @change="applyFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
                <button @click="openMassal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium rounded-xl transition-colors">
                    📋 Input Massal
                </button>
                <button @click="openManual"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    + Input Manual
                </button>
            </div>
        </div>

        <!-- Hari Libur Alert -->
        <div v-if="hari_libur" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-2xl flex items-center gap-3">
            <span class="text-2xl shrink-0">🏖️</span>
            <div>
                <p class="text-sm font-semibold text-blue-800">Hari Libur: {{ hari_libur.nama }}</p>
                <p class="text-xs text-blue-600 mt-0.5">Absensi hari ini mungkin sudah otomatis ditandai libur</p>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-4 lg:grid-cols-7 gap-2 mb-5">
            <div v-for="s in summaryCards" :key="s.label" :class="['rounded-xl border px-3 py-2.5 text-center cursor-pointer transition-all hover:shadow-sm',
                fStatus === s.key ? 'ring-2 ring-indigo-500 ' + s.bg : s.bg]"
                @click="fStatus = fStatus === s.key ? '' : s.key">
                <p :class="['text-2xl font-bold leading-none', s.color]">{{ s.value }}</p>
                <p class="text-xs text-gray-400 mt-1 leading-tight">{{ s.label }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex items-center gap-2 mb-4">
            <input v-model="fSearch" type="text" placeholder="Cari nama guru..."
                class="px-4 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-52" />
            <select v-model="fJabatan" @change="applyFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none">
                <option value="">Semua Jabatan</option>
                <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
            </select>
            <button v-if="fStatus || fSearch" @click="fStatus = ''; fSearch = ''"
                class="text-xs text-gray-400 hover:text-gray-600">Reset filter</button>
            <span class="ml-auto text-xs text-gray-400">{{ absensiFiltered.length }} dari {{ absensi.length }}
                guru</span>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Guru</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Jam Masuk</th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Jam Pulang</th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                            Terlambat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in absensiFiltered" :key="a.id"
                        :class="[`hover:bg-gray-50/40 transition-colors`, a.is_koreksi ? `bg-amber-50/20` : ``]">
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <img v-if="a.foto" :src="a.foto" class="w-8 h-8 rounded-full object-cover shrink-0" />
                                <div v-else
                                    class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-xs font-bold text-indigo-700">
                                    {{ a.nama?.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ a.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ a.jabatan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span :class="[`text-xs font-semibold px-2.5 py-1 rounded-lg`, statusCls(a.status)]">
                                {{ statusLabel[a.status] ?? a.status }}
                            </span>
                            <span v-if="a.is_koreksi" class="ml-1 text-xs text-amber-500"
                                title="Sudah dikoreksi">✎</span>
                        </td>
                        <td class="px-4 py-3.5 text-center hidden md:table-cell">
                            <div class="flex items-center gap-1">
                                <span class="text-sm font-mono text-gray-700">{{ a.jam_masuk ?? '—' }}</span>
                                <span v-if="a.status === 'terlambat'" class="text-amber-400 text-xs"
                                    title="Terlambat">⚠</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center hidden md:table-cell">
                            <span class="text-sm text-gray-700">{{ a.jam_pulang ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3.5 text-center hidden lg:table-cell">
                            <div v-if="a.menit_terlambat > 0">
                                <span class="text-sm font-bold text-amber-600 block">
                                    {{ a.label_terlambat ?? (a.menit_terlambat + ' mnt') }}
                                </span>
                                <span v-if="a.durasi_label" class="text-xs text-gray-400">
                                    Durasi: {{ a.durasi_label }}
                                </span>
                            </div>
                            <span v-else-if="a.status === 'hadir'" class="text-xs text-emerald-500 font-medium">Tepat
                                waktu</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <button @click="openKoreksi(a)"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors" :class="a.sudah_absen
                                    ? `border-amber-200 text-amber-600 hover:bg-amber-50`
                                    : `border-indigo-200 text-indigo-600 hover:bg-indigo-50`">
                                {{ a.sudah_absen ? 'Koreksi' : 'Input' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!absensiFiltered.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada data absensi.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ══ MODAL INPUT / KOREKSI ══ -->
        <div v-if="showModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ modalTarget?.sudah_absen ? 'Koreksi Absensi' : 'Input Absensi' }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ modalTarget?.nama }} · {{ tanggal }}</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                class="text-red-500">*</span></label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button v-for="s in statusOptions" :key="s.value" type="button"
                                @click="mForm.status = s.value"
                                :class="[`py-2 rounded-xl border-2 text-xs font-semibold transition-all text-center`,
                                    mForm.status === s.value ? s.active : `border-gray-200 text-gray-600 hover:border-gray-300`]">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>
                    <!-- Jam -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Masuk</label>
                            <input v-model="mForm.jam_masuk" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Pulang</label>
                            <input v-model="mForm.jam_pulang" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                    </div>
                    <!-- Terlambat -->
                    <div v-if="mForm.status === 'terlambat'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Menit Terlambat</label>
                        <input v-model.number="mForm.menit_terlambat" type="number" min="0"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                    </div>
                    <!-- Keterangan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <textarea v-model="mForm.keterangan" rows="2"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white resize-none" />
                    </div>
                    <!-- Alasan koreksi (wajib jika koreksi) -->
                    <div v-if="modalTarget?.sudah_absen">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan Koreksi <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="mForm.alasan_koreksi" rows="2"
                            placeholder="Jelaskan alasan koreksi absensi ini..."
                            :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white resize-none`,
                                mErr.alasan ? `border-red-300` : `border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`]" />
                        <p v-if="mErr.alasan" class="mt-1 text-xs text-red-500">{{ mErr.alasan }}</p>
                    </div>
                    <!-- Alasan manual (wajib jika input baru) -->
                    <div v-else>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan Input Manual <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="mForm.alasan" rows="2" placeholder="Guru lupa absen, input by admin, dll..."
                            :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white resize-none`,
                                mErr.alasan ? `border-red-300` : `border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`]" />
                        <p v-if="mErr.alasan" class="mt-1 text-xs text-red-500">{{ mErr.alasan }}</p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showModal = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                    <button @click="submitModal" :disabled="mLoading"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ mLoading ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ MODAL INPUT MASSAL ══ -->
        <div v-if="showMassal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Input Massal</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Tandai semua guru yang belum absen hari ini</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
                        <strong>{{ belumAbsen.length }} guru</strong> belum absen — akan di-input dengan status yang
                        dipilih.
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="s in ['hadir', 'alfa', 'libur']" :key="s" type="button"
                                @click="massalStatus = s"
                                :class="[`py-2 rounded-xl border-2 text-sm font-semibold transition-all capitalize text-center`,
                                    massalStatus === s ? statusOptionsMap[s]?.active : `border-gray-200 text-gray-600`]">
                                {{ s }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showMassal = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">Batal</button>
                    <button @click="submitMassal" :disabled="!belumAbsen.length"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60">
                        Input {{ belumAbsen.length }} Guru
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
    absensi: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    tanggal: { type: String, default: '' },
    hari: { type: String, default: '' },
    hari_libur: { type: Object, default: null },
    jadwal: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const fTanggal = ref(props.filters.tanggal ?? props.tanggal)
const fJabatan = ref(props.filters.jabatan_id ?? '')
const fSearch = ref(props.filters.search ?? '')
const fStatus = ref('')

const batasTerlambat = computed(() => {
    if (!props.jadwal?.jam_masuk) return '—'
    const [h, m] = props.jadwal.jam_masuk.split(':').map(Number)
    const tol = Number(props.jadwal.toleransi ?? 15)
    const total = h * 60 + m + tol
    const hh = String(Math.floor(total / 60)).padStart(2, '0')
    const mm = String(total % 60).padStart(2, '0')
    return hh + ':' + mm
})

const statusLabel = {
    hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', izin_sakit: 'Izin Sakit',
    sakit: 'Sakit', alfa: 'Alfa', libur: 'Libur', dinas_luar: 'Dinas Luar', belum: 'Belum Absen',
}

const statusCls = (s) => ({
    hadir: 'bg-emerald-50 text-emerald-700',
    terlambat: 'bg-amber-50 text-amber-700',
    izin: 'bg-blue-50 text-blue-700',
    izin_sakit: 'bg-indigo-50 text-indigo-700',
    sakit: 'bg-indigo-50 text-indigo-700',
    alfa: 'bg-red-50 text-red-600',
    libur: 'bg-gray-100 text-gray-500',
    dinas_luar: 'bg-violet-50 text-violet-700',
    belum: 'bg-gray-100 text-gray-400',
}[s] ?? 'bg-gray-100 text-gray-500')

const summaryCards = computed(() => [
    { key: '', label: 'Total', value: props.ringkasan.total ?? 0, bg: 'bg-white border-gray-200', color: 'text-gray-900' },
    { key: 'hadir', label: 'Hadir', value: props.ringkasan.hadir ?? 0, bg: 'bg-emerald-50 border-emerald-100', color: 'text-emerald-700' },
    { key: 'terlambat', label: 'Terlambat', value: props.ringkasan.terlambat ?? 0, bg: 'bg-amber-50 border-amber-100', color: 'text-amber-700' },
    { key: 'izin', label: 'Izin', value: props.ringkasan.izin ?? 0, bg: 'bg-blue-50 border-blue-100', color: 'text-blue-700' },
    { key: 'sakit', label: 'Sakit', value: props.ringkasan.sakit ?? 0, bg: 'bg-indigo-50 border-indigo-100', color: 'text-indigo-700' },
    { key: 'alfa', label: 'Alfa', value: props.ringkasan.alfa ?? 0, bg: 'bg-red-50 border-red-100', color: 'text-red-600' },
    { key: 'belum', label: 'Belum', value: props.ringkasan.belum ?? 0, bg: 'bg-gray-50 border-gray-200', color: 'text-gray-600' },
])

const absensiFiltered = computed(() => {
    let list = props.absensi
    if (fStatus.value) list = list.filter(a => a.status === fStatus.value)
    if (fSearch.value) list = list.filter(a => a.nama.toLowerCase().includes(fSearch.value.toLowerCase()))
    return list
})

const belumAbsen = computed(() => props.absensi.filter(a => !a.sudah_absen))

function applyFilter() {
    router.get(route('admin.smart-payroll.absensi.harian'), {
        tanggal: fTanggal.value,
        jabatan_id: fJabatan.value || undefined,
    }, { preserveState: true, replace: true })
}

// ── Modal Input/Koreksi ───────────────────────────────────────────────────────
const showModal = ref(false)
const mLoading = ref(false)
const modalTarget = ref(null)
const mForm = reactive({ status: 'hadir', jam_masuk: '', jam_pulang: '', menit_terlambat: 0, keterangan: '', alasan_koreksi: '', alasan: '' })
const mErr = reactive({ alasan: '' })

const statusOptions = [
    { value: 'hadir', label: 'Hadir', active: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'terlambat', label: 'Terlambat', active: 'border-amber-500 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', active: 'border-blue-500 bg-blue-50 text-blue-700' },
    { value: 'sakit', label: 'Sakit', active: 'border-indigo-500 bg-indigo-50 text-indigo-700' },
    { value: 'alfa', label: 'Alfa', active: 'border-red-500 bg-red-50 text-red-700' },
    { value: 'libur', label: 'Libur', active: 'border-gray-400 bg-gray-100 text-gray-700' },
    { value: 'dinas_luar', label: 'Dinas Luar', active: 'border-violet-500 bg-violet-50 text-violet-700' },
    { value: 'izin_sakit', label: 'Izin Sakit', active: 'border-indigo-400 bg-indigo-50 text-indigo-700' },
]
const statusOptionsMap = Object.fromEntries(statusOptions.map(s => [s.value, s]))

function openKoreksi(a) {
    modalTarget.value = a
    Object.assign(mForm, { status: a.status === 'belum' ? 'hadir' : a.status, jam_masuk: a.jam_masuk ?? '', jam_pulang: a.jam_pulang ?? '', menit_terlambat: a.menit_terlambat ?? 0, keterangan: a.keterangan ?? '', alasan_koreksi: '', alasan: '' })
    mErr.alasan = ''
    showModal.value = true
}

function openManual() {
    modalTarget.value = null
    showModal.value = true
}

function submitModal() {
    const isKoreksi = modalTarget.value?.sudah_absen
    const alasan = isKoreksi ? mForm.alasan_koreksi : mForm.alasan
    mErr.alasan = alasan.trim() ? '' : 'Alasan wajib diisi'
    if (mErr.alasan) return

    mLoading.value = true

    if (isKoreksi && modalTarget.value?.absensi_id) {
        router.patch(route('admin.smart-payroll.absensi.koreksi-harian', modalTarget.value.absensi_id), { ...mForm }, {
            onSuccess: () => { showModal.value = false },
            onFinish: () => mLoading.value = false,
            preserveScroll: true,
        })
    } else {
        router.post(route('admin.smart-payroll.absensi.store-harian'), {
            tenaga_pendidik_id: modalTarget.value?.id,
            tanggal: props.tanggal,
            ...mForm,
        }, {
            onSuccess: () => { showModal.value = false },
            onFinish: () => mLoading.value = false,
        })
    }
}

// ── Modal Massal ──────────────────────────────────────────────────────────────
const showMassal = ref(false)
const massalStatus = ref('alfa')

function openMassal() { showMassal.value = true }

function submitMassal() {
    router.post(route('admin.smart-payroll.absensi.massal'), {
        tanggal: props.tanggal,
        status: massalStatus.value,
        guru_ids: belumAbsen.value.map(a => a.id),
    }, {
        onSuccess: () => { showMassal.value = false },
    })
}
</script>