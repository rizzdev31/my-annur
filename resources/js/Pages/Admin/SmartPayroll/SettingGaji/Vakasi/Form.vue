<template>
    <AdminLayout :title="isEdit ? 'Edit Setting Vakasi' : 'Setting Vakasi Baru'" subtitle="Pengaturan">

        <Head :title="isEdit ? 'Edit Setting Vakasi' : 'Setting Vakasi Baru'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.setting-gaji.vakasi.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <h2 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Setting Vakasi' : 'Setting Vakasi Baru' }}
            </h2>
        </div>

        <div class="max-w-2xl space-y-4">
            <form @submit.prevent="submit">

                <!-- Ringkasan error validasi (agar tidak gagal diam-diam) -->
                <div v-if="Object.keys(form.errors).length"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-semibold mb-1">Gagal menyimpan — periksa isian berikut:</p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <li v-for="(e, k) in form.errors" :key="k">{{ e }}</li>
                    </ul>
                </div>

                <!-- Nama & Tipe -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nama Setting <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nama" type="text"
                            placeholder="cth: Vakasi Hadir Harian, Vakasi Mengajar JP, Vakasi Tugas Luar"
                            :class="inputCls(form.errors.nama)" />
                        <ErrMsg :e="form.errors.nama" />
                    </div>

                    <!-- Tipe Aktivitas -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipe Aktivitas <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="t in tipeOpts" :key="t.value" type="button"
                                @click="form.tipe_aktivitas = t.value; syncSatuan()" :class="[
                                    'flex flex-col gap-0.5 px-4 py-3 rounded-xl border-2 text-left transition-all',
                                    form.tipe_aktivitas === t.value ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'
                                ]">
                                <span class="text-sm font-medium"
                                    :class="form.tipe_aktivitas === t.value ? 'text-indigo-700' : 'text-gray-700'">
                                    {{ t.label }}
                                </span>
                                <span class="text-xs"
                                    :class="form.tipe_aktivitas === t.value ? 'text-indigo-500' : 'text-gray-400'">
                                    {{ t.desc }}
                                </span>
                            </button>
                        </div>
                        <ErrMsg :e="form.errors.tipe_aktivitas" />
                    </div>

                    <!-- Satuan + Nominal -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Satuan <span class="text-red-500">*</span>
                            </label>
                            <select v-model="form.satuan" :class="inputCls(form.errors.satuan)">
                                <option v-for="s in satuanByTipe" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </option>
                            </select>
                            <ErrMsg :e="form.errors.satuan" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Nominal <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                                <input v-model.number="form.nominal" type="number" min="0" step="1000"
                                    :class="inputCls(form.errors.nominal) + ' pl-9'" />
                            </div>
                            <p v-if="form.nominal > 0" class="text-xs text-indigo-600 mt-1">
                                {{ formatRp(form.nominal) }} {{ form.satuan ? '/' + labelSatuan[form.satuan] : '' }}
                            </p>
                            <ErrMsg :e="form.errors.nominal" />
                        </div>
                    </div>

                    <!-- Info mengajar -->
                    <div v-if="form.tipe_aktivitas === 'absen_mengajar'"
                        class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
                        Vakasi mengajar: setiap JP terlaksana × nominal. Contoh: 20 JP × {{ formatRp(form.nominal) }} =
                        <strong>{{ formatRp(form.nominal * 20) }}</strong>
                    </div>

                    <!-- Khusus Lembur -->
                    <div v-if="form.tipe_aktivitas === 'lembur'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Minimal Kerja (menit) <span class="text-red-500">*</span>
                                </label>
                                <input v-model.number="form.min_durasi_menit" type="number" min="1" step="30"
                                    :class="inputCls(form.errors.min_durasi_menit)" />
                                <p class="text-xs text-gray-400 mt-1">
                                    Durasi lembur minimal agar berhak vakasi. {{ form.min_durasi_menit ? '≈ ' + (form.min_durasi_menit / 60).toFixed(1) + ' jam' : '' }}
                                </p>
                                <ErrMsg :e="form.errors.min_durasi_menit" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Tenggang Upload (menit)
                                </label>
                                <input v-model.number="form.batas_grace_menit" type="number" min="0" step="15"
                                    :class="inputCls(form.errors.batas_grace_menit)" />
                                <p class="text-xs text-gray-400 mt-1">Batas upload bukti setelah jam selesai. Default 60.</p>
                                <ErrMsg :e="form.errors.batas_grace_menit" />
                            </div>
                        </div>
                        <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl text-xs text-indigo-700">
                            Lembur flat: <strong>{{ formatRp(form.nominal) }}</strong> per lembur sah,
                            asalkan durasi ≥ {{ form.min_durasi_menit || 0 }} menit. Upload bukti dibuka setelah jam selesai
                            (jam mulai + durasi), ditutup setelah +{{ form.batas_grace_menit || 60 }} menit.
                        </div>
                    </div>

                    <!-- Berlaku mulai (tidak berlaku untuk lembur — tarif flat global) -->
                    <div v-if="form.tipe_aktivitas !== 'lembur'">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Berlaku Mulai <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.berlaku_mulai" type="date" :class="inputCls(form.errors.berlaku_mulai)" />
                        <ErrMsg :e="form.errors.berlaku_mulai" />
                    </div>
                </div>

                <!-- Lingkup Berlaku — disembunyikan untuk lembur (admin pilih tarif saat input lembur) -->
                <div v-if="form.tipe_aktivitas !== 'lembur'" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Lingkup Berlaku</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Tentukan untuk siapa vakasi ini berlaku</p>
                    </div>

                    <!-- Pilih lingkup -->
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="l in lingkupOpts" :key="l.value" type="button" @click="form.lingkup = l.value"
                            :class="[
                                'flex flex-col gap-0.5 px-4 py-3 rounded-xl border-2 text-left transition-all',
                                form.lingkup === l.value ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'
                            ]">
                            <span class="text-sm font-medium"
                                :class="form.lingkup === l.value ? 'text-indigo-700' : 'text-gray-700'">
                                {{ l.label }}
                            </span>
                            <span class="text-xs"
                                :class="form.lingkup === l.value ? 'text-indigo-500' : 'text-gray-400'">
                                {{ l.desc }}
                            </span>
                        </button>
                    </div>

                    <!-- Pilih jabatan (jika per_jabatan atau custom) -->
                    <Transition name="slide">
                        <div v-if="form.lingkup === 'per_jabatan' || form.lingkup === 'custom'" class="space-y-2">
                            <p class="text-xs font-medium text-gray-600">Pilih Jabatan:</p>
                            <div class="grid grid-cols-2 gap-2">
                                <label v-for="j in jabatan" :key="j.id"
                                    :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition-all',
                                        form.jabatan_ids.includes(j.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="checkbox" :value="j.id" v-model="form.jabatan_ids"
                                        class="rounded text-indigo-600" />
                                    <div>
                                        <p class="text-sm text-gray-700">{{ j.nama_jabatan }}</p>
                                        <p class="text-xs text-gray-400 capitalize">{{ j.tipe }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </Transition>

                    <!-- Pilih individu (jika per_individu atau custom) -->
                    <Transition name="slide">
                        <div v-if="form.lingkup === 'per_individu' || form.lingkup === 'custom'" class="space-y-2">
                            <p class="text-xs font-medium text-gray-600">Pilih Individu:</p>
                            <input v-model="searchGuru" type="text" placeholder="Cari nama guru..."
                                class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white mb-2" />
                            <div class="max-h-48 overflow-y-auto space-y-1.5">
                                <label v-for="g in guruFiltered" :key="g.id"
                                    :class="['flex items-center gap-3 px-3 py-2 rounded-xl border cursor-pointer transition-all',
                                        form.tenaga_pendidik_ids.includes(g.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']">
                                    <input type="checkbox" :value="g.id" v-model="form.tenaga_pendidik_ids"
                                        class="rounded text-indigo-600" />
                                    <div>
                                        <p class="text-sm text-gray-700">{{ g.nama }}</p>
                                        <p class="text-xs text-gray-400">{{ g.jabatan }}</p>
                                    </div>
                                </label>
                            </div>
                            <p class="text-xs text-gray-400">{{ form.tenaga_pendidik_ids.length }} individu dipilih</p>
                            <ErrMsg :e="form.errors.tenaga_pendidik_ids" />
                        </div>
                    </Transition>

                    <!-- Semua -->
                    <p v-if="form.lingkup === 'semua'" class="text-sm text-emerald-600 flex items-center gap-2">
                        <span
                            class="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center text-xs">✓</span>
                        Berlaku untuk semua tenaga pendidik
                    </p>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('admin.smart-payroll.setting-gaji.vakasi.index')"
                        class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan' : 'Buat Setting') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, h } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Inline component (render function — runtime build tidak punya compiler template) ──
const ErrMsg = {
    props: { e: String },
    render() {
        return this.e ? h('p', { class: 'mt-1 text-xs text-red-500' }, this.e) : null
    },
}

const props = defineProps({
    vakasi: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.vakasi)
const searchGuru = ref('')

const form = useForm({
    nama: props.vakasi?.nama ?? '',
    tipe_aktivitas: props.vakasi?.tipe_aktivitas ?? 'absen_harian',
    satuan: props.vakasi?.satuan ?? 'per_hari',
    nominal: props.vakasi?.nominal ?? 0,
    lingkup: props.vakasi?.lingkup ?? 'semua',
    berlaku_untuk_semua: props.vakasi?.berlaku_untuk_semua ?? true,
    jabatan_ids: props.vakasi?.jabatan_ids ?? [],
    tenaga_pendidik_ids: props.vakasi?.tenaga_pendidik_ids ?? [],
    berlaku_mulai: props.vakasi?.berlaku_mulai ?? new Date().toISOString().slice(0, 10),
    // Khusus lembur:
    min_durasi_menit: props.vakasi?.min_durasi_menit ?? 180,
    batas_grace_menit: props.vakasi?.batas_grace_menit ?? 60,
})

const tipeOpts = [
    { value: 'absen_harian', label: 'Kehadiran Harian', desc: 'Per hari hadir kerja' },
    { value: 'absen_mengajar', label: 'Mengajar (per JP)', desc: 'Per jam pelajaran terlaksana' },
    { value: 'tugas_tambahan', label: 'Tugas Tambahan', desc: 'Tugas luar, event, dll.' },
    { value: 'lembur', label: 'Lembur', desc: 'Flat per lembur, min. jam kerja' },
    { value: 'piket', label: 'Guru Piket', desc: 'Flat per hari tugas piket (laporan harian terisi)' },
    { value: 'tasmi', label: 'Tasmi\' (Tahfidz)', desc: 'Flat per tasmi juz yang diuji penguji' },
    { value: 'tasnif', label: 'Tasnif (Tahsin)', desc: 'Flat per ujian kenaikan level' },
    { value: 'ekstrakurikuler', label: 'Ekstrakurikuler', desc: 'Flat per pertemuan ekskul (absensi terisi)' },
]

const lingkupOpts = [
    { value: 'semua', label: 'Semua Guru', desc: 'Berlaku untuk semua tenaga pendidik' },
    { value: 'per_jabatan', label: 'Per Jabatan', desc: 'Hanya untuk jabatan tertentu' },
    { value: 'per_individu', label: 'Per Individu', desc: 'Hanya untuk guru tertentu' },
    { value: 'custom', label: 'Jabatan + Individu', desc: 'Kombinasi jabatan & individu tertentu' },
]

const satuanAll = {
    absen_harian: [{ value: 'per_hari', label: 'Per Hari' }],
    absen_mengajar: [{ value: 'per_jp', label: 'Per JP (Jam Pelajaran)' }],
    tugas_jabatan: [{ value: 'per_bulan', label: 'Per Bulan' }, { value: 'per_tugas', label: 'Per Tugas' }],
    tugas_tambahan: [{ value: 'per_tugas', label: 'Per Tugas Selesai' }, { value: 'per_jam', label: 'Per Jam Kerja' }],
    lembur: [{ value: 'per_tugas', label: 'Per Lembur (flat)' }],
    piket: [{ value: 'per_hari', label: 'Per Hari Tugas Piket' }],
    tasmi: [{ value: 'per_tugas', label: 'Per Tasmi (flat)' }],
    tasnif: [{ value: 'per_tugas', label: 'Per Tasnif (flat)' }],
    ekstrakurikuler: [{ value: 'per_pertemuan', label: 'Per Pertemuan (flat)' }],
}

const satuanByTipe = computed(() => satuanAll[form.tipe_aktivitas] ?? [])

function syncSatuan() {
    const opts = satuanAll[form.tipe_aktivitas]
    if (opts?.length) form.satuan = opts[0].value
}

const labelSatuan = { per_hari: 'hari', per_jp: 'JP', per_tugas: 'tugas', per_jam: 'jam', per_bulan: 'bulan' }

const guruFiltered = computed(() => {
    if (!searchGuru.value) return props.guru
    const s = searchGuru.value.toLowerCase()
    return props.guru.filter(g =>
        g.nama.toLowerCase().includes(s) || g.jabatan.toLowerCase().includes(s)
    )
})

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    isEdit.value
        ? form.put(route('admin.smart-payroll.setting-gaji.vakasi.update', props.vakasi.id))
        : form.post(route('admin.smart-payroll.setting-gaji.vakasi.store'))
}
</script>


<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
}

.slide-enter-to,
.slide-leave-from {
    max-height: 600px;
}
</style>