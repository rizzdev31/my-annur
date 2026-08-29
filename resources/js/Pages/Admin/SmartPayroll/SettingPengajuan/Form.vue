<template>
    <AdminLayout :title="isEdit ? 'Edit Jenis Pengajuan' : 'Tambah Jenis Pengajuan'" subtitle="Smart Payroll">

        <Head :title="isEdit ? 'Edit Jenis Pengajuan' : 'Tambah Jenis Pengajuan'" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.setting-pengajuan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Jenis Pengajuan' : 'Tambah Jenis Pengajuan' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ isEdit ? `Ubah konfigurasi ${form.nama}` : 'Buat jenis pengajuan izin baru' }}
                </p>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- ── Kolom Kiri: Identitas ─────────────────────────────── -->
                <div class="space-y-4">

                    <!-- Identitas -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Identitas</h3>
                        <div class="space-y-3.5">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Nama Jenis <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.nama" type="text" placeholder="cth: Cuti Tahunan"
                                    :class="inputCls(form.errors.nama)" />
                                <ErrMsg :e="form.errors.nama" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Kode <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.kode" type="text" placeholder="cth: CUTI_TAHUNAN"
                                    :disabled="isEdit"
                                    :class="[inputCls(form.errors.kode), isEdit ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : '']" />
                                <p class="text-xs text-gray-400 mt-1">Huruf kapital, angka, underscore. Tidak bisa
                                    diubah setelah dibuat.</p>
                                <ErrMsg :e="form.errors.kode" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Kategori <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.kategori" :class="inputCls(form.errors.kategori)">
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="sakit">🏥 Sakit</option>
                                    <option value="izin">✋ Izin</option>
                                    <option value="cuti">🏖️ Cuti</option>
                                    <option value="dinas">🚗 Dinas</option>
                                </select>
                                <ErrMsg :e="form.errors.kategori" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                                <textarea v-model="form.deskripsi" rows="3"
                                    placeholder="Penjelasan singkat tentang jenis pengajuan ini..."
                                    :class="inputCls(form.errors.deskripsi) + ' resize-none'"></textarea>
                                <ErrMsg :e="form.errors.deskripsi" />
                            </div>
                        </div>
                    </div>

                    <!-- Preview Badge -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Preview Badge</h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span :class="['px-3 py-1.5 rounded-lg text-sm font-medium', badgeClass]">
                                {{ form.nama || 'Nama Jenis' }}
                            </span>
                            <span v-if="form.auto_approve"
                                class="px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700">
                                Auto approve
                            </span>
                            <span v-if="form.butuh_dokumen"
                                class="px-2.5 py-1 rounded-lg text-xs font-medium bg-amber-50 text-amber-700">
                                Butuh dokumen
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ── Kolom Kanan: Aturan & Konfigurasi ─────────────────── -->
                <div class="xl:col-span-2 space-y-4">

                    <!-- Aturan Pengajuan -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Aturan Pengajuan</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Maks. Hari per Pengajuan <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input v-model.number="form.max_hari_per_pengajuan" type="number" min="1"
                                        :class="inputCls(form.errors.max_hari_per_pengajuan)" />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">hari</span>
                                </div>
                                <ErrMsg :e="form.errors.max_hari_per_pengajuan" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kuota per Tahun</label>
                                <div class="relative">
                                    <input v-model.number="form.kuota_per_tahun" type="number" min="1"
                                        placeholder="Kosongkan = tidak terbatas"
                                        :class="inputCls(form.errors.kuota_per_tahun)" />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">hari/thn</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak ada batas tahunan.</p>
                                <ErrMsg :e="form.errors.kuota_per_tahun" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Min. Hari Pengajuan Sebelumnya <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input v-model.number="form.min_hari_pengajuan_sebelumnya" type="number" min="0"
                                        :class="inputCls(form.errors.min_hari_pengajuan_sebelumnya)" />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">hari</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">0 = bisa mendadak hari itu juga.</p>
                                <ErrMsg :e="form.errors.min_hari_pengajuan_sebelumnya" />
                            </div>

                            <!-- Auto Approve toggle -->
                            <div class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 hover:border-indigo-200 transition-colors cursor-pointer"
                                @click="form.auto_approve = !form.auto_approve">
                                <div :class="[
                                    'relative mt-0.5 w-10 h-5.5 rounded-full transition-colors flex-shrink-0 cursor-pointer',
                                    form.auto_approve ? 'bg-indigo-600' : 'bg-gray-300'
                                ]" style="height:22px; width:40px;">
                                    <span :class="[
                                        'absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                        form.auto_approve ? 'translate-x-5' : 'translate-x-0.5'
                                    ]"></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">Auto Approve</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Langsung disetujui tanpa review superadmin
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dokumen -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-800">Dokumen Pendukung</h3>
                            <button type="button" @click="form.butuh_dokumen = !form.butuh_dokumen" :class="[
                                'relative w-10 rounded-full transition-colors cursor-pointer',
                                form.butuh_dokumen ? 'bg-indigo-600' : 'bg-gray-300'
                            ]" style="height:22px; width:40px;">
                                <span :class="[
                                    'absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                    form.butuh_dokumen ? 'translate-x-5' : 'translate-x-0.5'
                                ]"></span>
                            </button>
                        </div>

                        <Transition name="slide">
                            <div v-if="form.butuh_dokumen">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Keterangan Dokumen Yang Dibutuhkan
                                </label>
                                <input v-model="form.keterangan_dokumen" type="text"
                                    placeholder="cth: Surat dokter, SK Cuti, Surat tugas..."
                                    :class="inputCls(form.errors.keterangan_dokumen)" />
                                <ErrMsg :e="form.errors.keterangan_dokumen" />
                            </div>
                        </Transition>

                        <p v-if="!form.butuh_dokumen" class="text-sm text-gray-400">
                            Tidak memerlukan dokumen pendukung.
                        </p>
                    </div>

                    <!-- Pengaruh Gaji -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Pengaruh ke Gaji</h3>
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button v-for="opt in pengaruhGajiOpts" :key="opt.value" type="button"
                                @click="form.pengaruh_gaji = opt.value" :class="[
                                    'flex flex-col items-start gap-1 px-4 py-3 rounded-xl border-2 text-left transition-all',
                                    form.pengaruh_gaji === opt.value
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]">
                                <span class="text-sm font-medium"
                                    :class="form.pengaruh_gaji === opt.value ? 'text-indigo-700' : 'text-gray-700'">
                                    {{ opt.label }}
                                </span>
                                <span class="text-xs text-gray-400">{{ opt.desc }}</span>
                            </button>
                        </div>

                        <!-- Persen potongan jika potong_sebagian -->
                        <Transition name="slide">
                            <div v-if="form.pengaruh_gaji === 'potong_sebagian'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Persen Potongan <span class="text-red-500">*</span>
                                </label>
                                <div class="relative w-40">
                                    <input v-model.number="form.persen_potongan" type="number" min="1" max="100"
                                        placeholder="50" :class="inputCls(form.errors.persen_potongan)" />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                                </div>
                                <ErrMsg :e="form.errors.persen_potongan" />
                            </div>
                        </Transition>
                    </div>

                    <!-- Integrasi Status Kepegawaian -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">Update Status Kepegawaian</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Otomatis ubah status kepegawaian saat disetujui
                                </p>
                            </div>
                            <button type="button"
                                @click="form.update_status_kepegawaian = !form.update_status_kepegawaian" :class="[
                                    'relative rounded-full transition-colors cursor-pointer flex-shrink-0',
                                    form.update_status_kepegawaian ? 'bg-indigo-600' : 'bg-gray-300'
                                ]" style="height:22px; width:40px;">
                                <span :class="[
                                    'absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                    form.update_status_kepegawaian ? 'translate-x-5' : 'translate-x-0.5'
                                ]"></span>
                            </button>
                        </div>

                        <Transition name="slide">
                            <div v-if="form.update_status_kepegawaian" class="space-y-3.5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Status Kepegawaian Tujuan <span class="text-red-500">*</span>
                                    </label>
                                    <select v-model="form.status_kepegawaian_tujuan"
                                        :class="inputCls(form.errors.status_kepegawaian_tujuan)">
                                        <option value="">-- Pilih Status --</option>
                                        <option value="cuti">Cuti</option>
                                        <option value="cuti_sakit">Cuti Sakit</option>
                                        <option value="nonaktif_sementara">Nonaktif Sementara</option>
                                    </select>
                                    <ErrMsg :e="form.errors.status_kepegawaian_tujuan" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Min. Hari untuk Update Status
                                    </label>
                                    <div class="relative w-40">
                                        <input v-model.number="form.min_hari_untuk_update_status" type="number" min="1"
                                            placeholder="3"
                                            :class="inputCls(form.errors.min_hari_untuk_update_status)" />
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">hari</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Status kepegawaian hanya diupdate jika pengajuan ≥ jumlah hari ini.
                                    </p>
                                    <ErrMsg :e="form.errors.min_hari_untuk_update_status" />
                                </div>
                            </div>
                        </Transition>

                        <p v-if="!form.update_status_kepegawaian" class="text-sm text-gray-400">
                            Tidak mengubah status kepegawaian secara otomatis.
                        </p>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('admin.smart-payroll.setting-pengajuan.index')"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                            Batal
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200 disabled:opacity-60">
                            <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Jenis Pengajuan')
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    setting: { type: Object, default: null },
})

const isEdit = computed(() => !!props.setting)

// ── Form ──────────────────────────────────────────────────────────────────────
const form = useForm({
    nama: props.setting?.nama ?? '',
    kode: props.setting?.kode ?? '',
    kategori: props.setting?.kategori ?? '',
    deskripsi: props.setting?.deskripsi ?? '',
    max_hari_per_pengajuan: props.setting?.max_hari_per_pengajuan ?? 1,
    kuota_per_tahun: props.setting?.kuota_per_tahun ?? null,
    min_hari_pengajuan_sebelumnya: props.setting?.min_hari_pengajuan_sebelumnya ?? 0,
    butuh_dokumen: props.setting?.butuh_dokumen ?? false,
    keterangan_dokumen: props.setting?.keterangan_dokumen ?? '',
    auto_approve: props.setting?.auto_approve ?? false,
    pengaruh_gaji: props.setting?.pengaruh_gaji ?? 'potong_absensi',
    persen_potongan: props.setting?.persen_potongan ?? 0,
    update_status_kepegawaian: props.setting?.update_status_kepegawaian ?? false,
    status_kepegawaian_tujuan: props.setting?.status_kepegawaian_tujuan ?? '',
    min_hari_untuk_update_status: props.setting?.min_hari_untuk_update_status ?? 3,
})

// ── Preview badge color ───────────────────────────────────────────────────────
const badgeClass = computed(() => ({
    sakit: 'bg-blue-50 text-blue-700',
    izin: 'bg-amber-50 text-amber-700',
    cuti: 'bg-violet-50 text-violet-700',
    dinas: 'bg-indigo-50 text-indigo-700',
}[form.kategori] ?? 'bg-gray-100 text-gray-600'))

// ── Options ───────────────────────────────────────────────────────────────────
const pengaruhGajiOpts = [
    { value: 'tidak_potong', label: 'Tidak Dipotong', desc: 'Gaji tetap penuh' },
    { value: 'potong_absensi', label: 'Potong Vakasi', desc: 'Tidak dapat vakasi harian' },
    { value: 'potong_sebagian', label: 'Potong Sebagian', desc: 'Potongan persen tertentu' },
    { value: 'potong_penuh', label: 'Potong Penuh', desc: 'Tidak dapat gaji sama sekali' },
]

// ── Input class helper ────────────────────────────────────────────────────────
function inputCls(err) {
    const base = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return err
        ? `${base} border-red-300 focus:border-red-500 focus:ring-red-100`
        : `${base} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submit() {
    if (isEdit.value) {
        form.put(route('admin.smart-payroll.setting-pengajuan.update', props.setting.id))
    } else {
        form.post(route('admin.smart-payroll.setting-pengajuan.store'))
    }
}
</script>

<!-- ErrMsg inline component -->
<script>
const ErrMsg = {
    props: { e: String },
    template: `<p v-if="e" class="mt-1 text-xs text-red-500 flex items-center gap-1">
        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>{{ e }}</p>`
}
export { ErrMsg }
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
    max-height: 200px;
}
</style>