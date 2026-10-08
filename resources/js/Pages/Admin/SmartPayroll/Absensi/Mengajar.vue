<template>
    <AdminLayout title="Absensi Mengajar" subtitle="Smart Payroll">

        <Head title="Absensi Mengajar" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Absensi Mengajar</h2>
                <p class="text-sm text-gray-400 mt-0.5">{{ hari }}</p>
            </div>
            <input v-model="fTanggal" type="date" @change="applyFilter"
                class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ ringkasan.total }}</p>
                <p class="text-xs text-gray-400 mt-1">Total Sesi</p>
            </div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ ringkasan.terlaksana }}</p>
                <p class="text-xs text-emerald-500 mt-1">Terlaksana</p>
            </div>
            <div class="bg-violet-50 rounded-xl border border-violet-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-violet-700">{{ ringkasan.pengganti ?? 0 }}</p>
                <p class="text-xs text-violet-400 mt-1">Pengganti</p>
            </div>
            <div class="bg-amber-50 rounded-xl border border-amber-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-amber-700">{{ ringkasan.izin ?? 0 }}</p>
                <p class="text-xs text-amber-500 mt-1">Izin (Hangus)</p>
            </div>
            <div class="bg-red-50 rounded-xl border border-red-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-red-600">{{ ringkasan.tidak }}</p>
                <p class="text-xs text-red-400 mt-1">Tidak Terlaksana</p>
            </div>
            <div class="bg-gray-50 rounded-xl border border-gray-200 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-gray-500">{{ ringkasan.belum }}</p>
                <p class="text-xs text-gray-400 mt-1">Belum Input</p>
            </div>
            <div class="bg-indigo-50 rounded-xl border border-indigo-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ ringkasan.total_jp }}</p>
                <p class="text-xs text-indigo-400 mt-1">Total JP</p>
            </div>
            <div class="bg-teal-50 rounded-xl border border-teal-100 px-3 py-2.5 text-center">
                <p class="text-2xl font-bold text-teal-700">{{ ringkasan.jp_terlaksana }}</p>
                <p class="text-xs text-teal-400 mt-1">JP Terlaksana</p>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-800">Jadwal Mengajar Hari Ini</p>
                <input v-model="fSearch" type="text" placeholder="Cari guru..."
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-44" />
            </div>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Guru & Pelajaran
                        </th>
                        <th
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Jadwal</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">JP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in absensiFiltered" :key="a.jadwal_id" class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <img v-if="a.foto" :src="a.foto" class="w-8 h-8 rounded-full object-cover shrink-0" />
                                <div v-else
                                    class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center shrink-0 text-xs font-bold text-teal-700">
                                    {{ a.nama?.charAt(0) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ a.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ a.mata_pelajaran }} · {{ a.kelas }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center hidden md:table-cell">
                            <p class="text-sm text-gray-700">{{ a.jam_mulai }} – {{ a.jam_selesai }}</p>
                            <p class="text-xs text-gray-400">{{ a.ruangan }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-sm font-bold text-indigo-700">{{ a.jp_terlaksana ?? 0 }}</span>
                                <span class="text-xs text-gray-400">/ {{ a.jumlah_jp }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span
                                :class="[`text-xs font-semibold px-2.5 py-1 rounded-lg`, statusMengajarCls(a.status)]">
                                {{ statusMengajarLabel[a.status] ?? a.status }}
                            </span>
                            <span v-if="a.is_koreksi" class="ml-1 text-xs text-amber-500">✎</span>
                            <p v-if="a.status === 'pengganti' && a.pengganti_nama"
                                class="text-xs text-violet-600 mt-1">🔄 oleh {{ a.pengganti_nama }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <button @click="openInput(a)"
                                :class="[`px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors`,
                                    a.sudah_absen ? `border-amber-200 text-amber-600 hover:bg-amber-50` : `border-indigo-200 text-indigo-600 hover:bg-indigo-50`]">
                                {{ a.sudah_absen ? 'Koreksi' : 'Input' }}
                            </button>
                            <!-- Inval cepat: admin mengalihkan sesi tanpa menunggu izin guru -->
                            <button v-if="a.boleh_inval && !a.digantikan_oleh" @click="bukaInval(a)"
                                class="ml-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-violet-200 text-violet-600 hover:bg-violet-50">
                                Inval
                            </button>
                            <button v-else-if="a.digantikan_oleh && a.boleh_inval" @click="bukaInval(a)"
                                class="ml-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-violet-200 text-violet-600 hover:bg-violet-50">
                                Ganti Inval
                            </button>
                            <button v-if="a.digantikan_oleh && a.absensi_id && a.boleh_inval" @click="batalInval(a)"
                                class="ml-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 text-gray-500 hover:bg-gray-50">
                                Batal
                            </button>
                            <span v-if="a.is_ujian" class="ml-1 text-[10px] font-semibold text-rose-600">ujian</span>
                        </td>
                    </tr>
                    <tr v-if="!absensiFiltered.length">
                        <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada jadwal mengajar untuk hari ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Inval Cepat -->
        <div v-if="invalTarget" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto p-6">
                <h3 class="text-base font-semibold text-gray-900">Inval Cepat</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-1">
                    {{ invalTarget.mata_pelajaran }} · {{ invalTarget.kelas }} ·
                    {{ (invalTarget.jam_mulai || '').slice(0,5) }}–{{ (invalTarget.jam_selesai || '').slice(0,5) }} ·
                    guru {{ invalTarget.nama }}
                </p>
                <p class="text-[11px] leading-snug rounded-lg px-3 py-2 mb-3"
                    :class="invalIzin ? 'bg-sky-50 text-sky-800' : 'bg-amber-50 text-amber-800'">
                    <template v-if="invalIzin">Guru pengampu sedang <b>{{ invalIzin }}</b> pada tanggal ini.</template>
                    <template v-else>
                        Guru pengampu <b>tidak punya izin resmi</b> hari ini — inval tetap bisa diberikan
                        karena keputusan ini milik admin, dan alasannya dicatat.
                    </template>
                </p>

                <input v-model="fInval.alasan" type="text" placeholder="Alasan (dicatat di log koreksi)"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm mb-3" />
                <input v-model="cariInval" type="text" placeholder="Cari nama guru…"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm mb-3" />

                <p v-if="invalLoading" class="py-8 text-center text-sm text-gray-400">Memeriksa jadwal setiap guru…</p>
                <div v-else class="rounded-xl border border-gray-200 divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <button v-for="c in calonTampil" :key="c.id" type="button" :disabled="!c.boleh"
                        @click="kirimInval(c)"
                        class="w-full text-left px-3 py-2.5 hover:bg-gray-50 disabled:bg-gray-50/50 disabled:cursor-not-allowed">
                        <span class="block text-sm font-medium truncate"
                            :class="c.boleh ? 'text-gray-800' : 'text-gray-400'">{{ c.nama }}</span>
                        <span v-if="c.alasan" class="block text-[11px] text-red-500">{{ c.alasan }}</span>
                        <span v-else-if="c.id === invalTarget.digantikan_oleh"
                            class="block text-[11px] text-violet-600">pengganti saat ini</span>
                    </button>
                    <p v-if="!calonTampil.length" class="px-3 py-6 text-center text-sm text-gray-400">Tidak ada guru yang cocok.</p>
                </div>

                <button @click="invalTarget = null"
                    class="w-full mt-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
            </div>
        </div>

        <!-- Modal Input Mengajar -->
        <div v-if="showModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ modalTarget?.sudah_absen ? 'Koreksi' : 'Input' }} Absensi Mengajar
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ modalTarget?.nama }} · {{ modalTarget?.mata_pelajaran }} · {{ tanggal }}
                    </p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                class="text-red-500">*</span></label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="s in statusMengajarOptions" :key="s.value" type="button"
                                @click="mForm.status = s.value" :class="[`py-2 rounded-xl border-2 text-xs font-semibold transition-all text-center`,
                                    mForm.status === s.value ? s.active : `border-gray-200 text-gray-600`]">
                                {{ s.label }}
                            </button>
                        </div>
                    </div>
                    <!-- Guru pengganti (status = pengganti) -->
                    <div v-if="mForm.status === 'pengganti'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Guru Pengganti <span class="text-red-500">*</span></label>
                        <select v-model.number="mForm.digantikan_oleh"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-500 bg-white">
                            <option :value="null">Pilih guru pengganti...</option>
                            <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Vakasi JP sesi ini dibayarkan ke guru pengganti.</p>
                    </div>
                    <!-- JP Terlaksana -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">JP Terlaksana</label>
                        <input v-model.number="mForm.jp_terlaksana" type="number" min="0" :max="modalTarget?.jumlah_jp"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        <p class="text-xs text-gray-400 mt-1">JP jadwal: {{ modalTarget?.jumlah_jp }}</p>
                    </div>
                    <!-- Jam aktual -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Mulai Aktual</label>
                            <input v-model="mForm.jam_mulai_aktual" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Selesai Aktual</label>
                            <input v-model="mForm.jam_selesai_aktual" type="time"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                    </div>
                    <!-- Materi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Materi</label>
                        <input v-model="mForm.materi" type="text" placeholder="Topik yang diajarkan..."
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                    </div>
                    <!-- Alasan koreksi -->
                    <div v-if="modalTarget?.sudah_absen">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Alasan Koreksi <span
                                class="text-red-500">*</span></label>
                        <textarea v-model="mForm.alasan_koreksi" rows="2" :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none bg-white resize-none`,
                            mErr.alasan ? `border-red-300` : `border-gray-200 focus:border-indigo-500`]" />
                        <p v-if="mErr.alasan" class="mt-1 text-xs text-red-500">{{ mErr.alasan }}</p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showModal = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">Batal</button>
                    <button @click="submitModal" :disabled="mLoading"
                        class="flex-1 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ mLoading ? 'Menyimpan...' : 'Simpan' }}
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
    filters: { type: Object, default: () => ({}) },
    guruOpsi: { type: Array, default: () => [] },
})

const fTanggal = ref(props.filters.tanggal ?? props.tanggal)
const fSearch = ref('')

const statusMengajarLabel = { hadir: 'Hadir', terlaksana: 'Terlaksana', tidak_terlaksana: 'Tidak Terlaksana', pengganti: 'Pengganti', izin: 'Izin (Hangus)', libur: 'Libur', belum: 'Belum Input' }
const statusMengajarCls = (s) => ({
    hadir: 'bg-emerald-50 text-emerald-700', terlaksana: 'bg-teal-50 text-teal-700',
    tidak_terlaksana: 'bg-red-50 text-red-600', pengganti: 'bg-violet-50 text-violet-700',
    izin: 'bg-amber-50 text-amber-700',
    libur: 'bg-gray-100 text-gray-500', belum: 'bg-gray-100 text-gray-400',
}[s] ?? 'bg-gray-100 text-gray-500')

const statusMengajarOptions = [
    { value: 'terlaksana', label: 'Terlaksana', active: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'tidak_terlaksana', label: 'Tidak Terlaksana', active: 'border-red-500 bg-red-50 text-red-700' },
    { value: 'pengganti', label: 'Pengganti', active: 'border-violet-500 bg-violet-50 text-violet-700' },
    { value: 'izin', label: 'Izin (Hangus)', active: 'border-amber-500 bg-amber-50 text-amber-700' },
    { value: 'libur', label: 'Libur', active: 'border-gray-400 bg-gray-100 text-gray-700' },
]

const absensiFiltered = computed(() => {
    if (!fSearch.value) return props.absensi
    const s = fSearch.value.toLowerCase()
    return props.absensi.filter(a => a.nama.toLowerCase().includes(s) || a.mata_pelajaran.toLowerCase().includes(s))
})

function applyFilter() {
    router.get(route('admin.smart-payroll.absensi.mengajar'), { tanggal: fTanggal.value }, { preserveState: true })
}

const showModal = ref(false)
const mLoading = ref(false)
const modalTarget = ref(null)
// ── Inval cepat ────────────────────────────────────────────────────────────
const invalTarget = ref(null)
const invalCalon = ref([])
const invalIzin = ref(null)
const invalLoading = ref(false)
const cariInval = ref('')
const fInval = reactive({ alasan: '' })

const calonTampil = computed(() => {
    const q = cariInval.value.trim().toLowerCase()
    return q ? invalCalon.value.filter(c => c.nama.toLowerCase().includes(q)) : invalCalon.value
})

async function bukaInval(a) {
    invalTarget.value = a; invalCalon.value = []; invalIzin.value = null
    cariInval.value = ''; fInval.alasan = ''; invalLoading.value = true
    try {
        const res = await fetch(
            route('admin.smart-payroll.absensi.calon-inval', a.jadwal_id) + '?tanggal=' + props.tanggal,
            { headers: { Accept: 'application/json' } })
        const d = (await res.json())?.data
        invalCalon.value = d?.calon ?? []
        invalIzin.value = d?.izin_guru ?? null
    } catch (_) { invalCalon.value = [] } finally { invalLoading.value = false }
}

function kirimInval(c) {
    router.post(route('admin.smart-payroll.absensi.inval-cepat'), {
        jadwal_id: invalTarget.value.jadwal_id,
        tanggal: props.tanggal,
        pengganti_id: c.id,
        alasan: fInval.alasan || null,
    }, { preserveScroll: true, onSuccess: () => { invalTarget.value = null } })
}

function batalInval(a) {
    if (!confirm('Batalkan inval sesi ini? Sesi kembali ke guru pengampunya.')) return
    router.post(route('admin.smart-payroll.absensi.batal-inval', a.absensi_id), {}, { preserveScroll: true })
}

const mForm = reactive({ status: 'terlaksana', digantikan_oleh: null, jp_terlaksana: 0, jam_mulai_aktual: '', jam_selesai_aktual: '', materi: '', keterangan: '', alasan_koreksi: '' })
const mErr = reactive({ alasan: '' })

function openInput(a) {
    modalTarget.value = a
    Object.assign(mForm, { status: a.status === 'belum' ? 'terlaksana' : a.status, digantikan_oleh: a.digantikan_oleh ?? null, jp_terlaksana: a.jp_terlaksana ?? a.jumlah_jp, jam_mulai_aktual: a.jam_mulai_aktual ?? a.jam_mulai, jam_selesai_aktual: a.jam_selesai_aktual ?? a.jam_selesai, materi: a.materi ?? '', keterangan: '', alasan_koreksi: '' })
    mErr.alasan = ''
    showModal.value = true
}

function submitModal() {
    // Validasi: pengganti wajib pilih guru
    if (mForm.status === 'pengganti' && !mForm.digantikan_oleh) {
        alert('Pilih guru pengganti terlebih dahulu.')
        return
    }
    if (modalTarget.value?.sudah_absen) {
        mErr.alasan = mForm.alasan_koreksi.trim() ? '' : 'Alasan wajib diisi'
        if (mErr.alasan) return

        mLoading.value = true
        router.patch(route('admin.smart-payroll.absensi.koreksi-mengajar', modalTarget.value.absensi_id), { ...mForm }, {
            onSuccess: () => { showModal.value = false },
            onFinish: () => mLoading.value = false,
        })
    } else {
        mLoading.value = true
        router.post(route('admin.smart-payroll.absensi.store-mengajar'), {
            jadwal_mengajar_id: modalTarget.value.jadwal_id,
            tanggal: props.tanggal,
            ...mForm,
        }, {
            onSuccess: () => { showModal.value = false },
            onFinish: () => mLoading.value = false,
        })
    }
}
</script>