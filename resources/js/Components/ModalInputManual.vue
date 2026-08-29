<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')" />

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Input Manual Pengajuan</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Buat pengajuan izin atas nama tenaga pendidik</p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                    <!-- Pilih Guru -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tenaga Pendidik <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.tenaga_pendidik_id" :class="inputCls(errors.tenaga_pendidik_id)">
                            <option value="">-- Pilih Tenaga Pendidik --</option>
                            <option v-for="g in guruList" :key="g.id" :value="g.id">
                                {{ g.nama }} — {{ g.jabatan }}
                            </option>
                        </select>
                        <ErrMsg :e="errors.tenaga_pendidik_id" />
                    </div>

                    <!-- Jenis Pengajuan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Jenis Pengajuan <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.setting_jenis_pengajuan_id"
                            :class="inputCls(errors.setting_jenis_pengajuan_id)" @change="onJenisChange">
                            <option value="">-- Pilih Jenis --</option>
                            <optgroup v-for="(items, kat) in jenisByKategori" :key="kat" :label="labelKat[kat]">
                                <option v-for="j in items" :key="j.id" :value="j.id">
                                    {{ j.nama }}
                                    <template v-if="j.kuota_per_tahun"> (kuota: {{ j.kuota_per_tahun }}
                                        hari/thn)</template>
                                </option>
                            </optgroup>
                        </select>
                        <ErrMsg :e="errors.setting_jenis_pengajuan_id" />

                        <!-- Info jenis yang dipilih -->
                        <div v-if="jenisDipilih" class="mt-2 p-3 bg-indigo-50 rounded-xl space-y-1">
                            <p class="text-xs text-indigo-700">
                                📅 Maks. <strong>{{ jenisDipilih.max_hari_per_pengajuan }} hari</strong> per pengajuan
                                <template v-if="jenisDipilih.kuota_per_tahun">
                                    · Kuota: <strong>{{ jenisDipilih.kuota_per_tahun }} hari/tahun</strong>
                                </template>
                            </p>
                            <p class="text-xs text-indigo-700">
                                💰 {{ labelPengaruhGaji[jenisDipilih.pengaruh_gaji] }}
                                <template v-if="jenisDipilih.auto_approve">
                                    · ✅ Auto approve
                                </template>
                            </p>
                            <p v-if="jenisDipilih.butuh_dokumen" class="text-xs text-amber-700">
                                📎 Perlu dokumen: {{ jenisDipilih.keterangan_dokumen || 'wajib dilampirkan' }}
                            </p>
                        </div>
                    </div>

                    <!-- Tanggal -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Tanggal Mulai <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.tanggal_mulai" type="date" :class="inputCls(errors.tanggal_mulai)"
                                @change="hitungHari" />
                            <ErrMsg :e="errors.tanggal_mulai" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Tanggal Selesai <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.tanggal_selesai" type="date" :min="form.tanggal_mulai"
                                :class="inputCls(errors.tanggal_selesai)" @change="hitungHari" />
                            <ErrMsg :e="errors.tanggal_selesai" />
                        </div>
                    </div>

                    <!-- Info jumlah hari -->
                    <div v-if="estimasiHari > 0"
                        class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 rounded-xl text-sm text-gray-600">
                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Estimasi <strong class="text-indigo-600 mx-1">{{ estimasiHari }} hari kerja</strong>
                        <span v-if="jenisDipilih && estimasiHari > jenisDipilih.max_hari_per_pengajuan"
                            class="text-red-500 text-xs ml-1">
                            ⚠️ Melebihi batas {{ jenisDipilih.max_hari_per_pengajuan }} hari
                        </span>
                    </div>

                    <!-- Alasan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="form.alasan" rows="3" placeholder="Tuliskan alasan pengajuan izin..."
                            :class="inputCls(errors.alasan) + ' resize-none'">
    </textarea>
                        <ErrMsg :e="errors.alasan" />
                    </div>

                    <!-- Upload Dokumen -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Dokumen Pendukung
                            <span v-if="jenisDipilih?.butuh_dokumen" class="text-red-500">*</span>
                            <span v-else class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <label
                            class="flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed border-gray-300 hover:border-indigo-400 hover:bg-indigo-50/30 cursor-pointer transition-colors">
                            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p v-if="dokumenNama" class="text-sm font-medium text-indigo-700 truncate">
                                    {{ dokumenNama }}
                                </p>
                                <p v-else class="text-sm text-gray-500">Klik untuk pilih file</p>
                                <p class="text-xs text-gray-400 mt-0.5">PDF, JPG, PNG · Maks 5MB</p>
                            </div>
                            <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="onDokumenChange" />
                        </label>
                        <button v-if="dokumenNama" type="button" @click="removeDokumen"
                            class="mt-1.5 text-xs text-red-400 hover:text-red-500">
                            Hapus dokumen
                        </button>
                        <ErrMsg :e="errors.file_dokumen" />
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex gap-3 px-6 pb-5 pt-1 border-t border-gray-100">
                    <button type="button" @click="$emit('close')"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button @click="submit" :disabled="loading"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors disabled:opacity-60 flex items-center justify-center gap-2">
                        <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ loading ? 'Menyimpan...' : 'Buat Pengajuan' }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    show: { type: Boolean, default: false },
    guruList: { type: Array, default: () => [] },
    jenisList: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'success'])

// ── State ─────────────────────────────────────────────────────────────────────
const loading = ref(false)
const errors = ref({})
const dokumenNama = ref('')
const estimasiHari = ref(0)

const form = ref({
    tenaga_pendidik_id: '',
    setting_jenis_pengajuan_id: '',
    tanggal_mulai: '',
    tanggal_selesai: '',
    alasan: '',
    file_dokumen: null,
})

// Reset saat dibuka
watch(() => props.show, (val) => {
    if (val) {
        form.value = {
            tenaga_pendidik_id: '',
            setting_jenis_pengajuan_id: '',
            tanggal_mulai: '',
            tanggal_selesai: '',
            alasan: '',
            file_dokumen: null,
        }
        errors.value = {}
        dokumenNama.value = ''
        estimasiHari.value = 0
    }
})

// ── Computed ──────────────────────────────────────────────────────────────────
const jenisByKategori = computed(() => {
    const groups = {}
    props.jenisList.forEach(j => {
        if (!groups[j.kategori]) groups[j.kategori] = []
        groups[j.kategori].push(j)
    })
    return groups
})

const jenisDipilih = computed(() =>
    props.jenisList.find(j => j.id === form.value.setting_jenis_pengajuan_id) ?? null
)

const labelKat = { sakit: '🏥 Sakit', izin: '✋ Izin', cuti: '🏖️ Cuti', dinas: '🚗 Dinas' }

const labelPengaruhGaji = {
    tidak_potong: 'Gaji tidak dipotong',
    potong_absensi: 'Tidak dapat vakasi harian',
    potong_sebagian: 'Gaji dipotong sebagian',
    potong_penuh: 'Gaji dipotong penuh',
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function onJenisChange() { hitungHari() }

function hitungHari() {
    if (!form.value.tanggal_mulai || !form.value.tanggal_selesai) return
    const mulai = new Date(form.value.tanggal_mulai)
    const selesai = new Date(form.value.tanggal_selesai)
    if (selesai < mulai) { estimasiHari.value = 0; return }

    // Hitung hari kalender (estimasi kasar, hari libur tidak diketahui di FE)
    let count = 0
    const cur = new Date(mulai)
    while (cur <= selesai) {
        const day = cur.getDay()
        if (day !== 0) count++ // skip Minggu saja (estimasi)
        cur.setDate(cur.getDate() + 1)
    }
    estimasiHari.value = count
}

function onDokumenChange(e) {
    const file = e.target.files[0]
    if (file) {
        form.value.file_dokumen = file
        dokumenNama.value = file.name
    }
}

function removeDokumen() {
    form.value.file_dokumen = null
    dokumenNama.value = ''
}

function inputCls(err) {
    const base = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return err
        ? `${base} border-red-300 focus:border-red-500 focus:ring-red-100`
        : `${base} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submit() {
    errors.value = {}

    // Validasi lokal dasar
    const required = ['tenaga_pendidik_id', 'setting_jenis_pengajuan_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan']
    let valid = true
    required.forEach(f => {
        if (!form.value[f]) {
            errors.value[f] = 'Field ini wajib diisi.'
            valid = false
        }
    })
    if (!valid) return

    loading.value = true

    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
        if (v !== null && v !== '') fd.append(k, v)
    })

    router.post(route('admin.smart-payroll.pengajuan-izin.store'), fd, {
        onSuccess: () => { emit('success'); emit('close') },
        onError: (e) => { errors.value = e },
        onFinish: () => { loading.value = false },
    })
}
</script>

<!-- ErrMsg inline -->
<script>
const ErrMsg = {
    props: { e: String },
    template: `<p v-if="e" class="mt-1 text-xs text-red-500 flex items-center gap-1">
        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>{{ e }}</p>`,
}
export { ErrMsg }
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