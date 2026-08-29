<template>
    <AdminLayout :title="isEdit ? 'Edit Tugas Tambahan' : 'Buat Tugas Tambahan'" subtitle="Smart Payroll">

        <Head :title="isEdit ? 'Edit Tugas Tambahan' : 'Buat Tugas Tambahan'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.tugas-tambahan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Tugas Tambahan' : 'Buat Tugas Tambahan' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ isEdit ? 'Ubah detail tugas' : 'Buat tugas ad-hoc untuk guru tertentu' }}
                </p>
            </div>
        </div>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-5">

                <!-- ── INFORMASI DASAR ──────────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <p class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-3">Informasi Tugas</p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Judul Tugas <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.judul" type="text" placeholder="cth: Buat Proposal Kegiatan Akhir Tahun"
                            :class="inputCls(form.errors.judul)" />
                        <p v-if="form.errors.judul" class="mt-1 text-xs text-red-500">{{ form.errors.judul }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea v-model="form.deskripsi" rows="3"
                            placeholder="Detail tugas, instruksi, atau catatan tambahan..."
                            :class="inputCls(form.errors.deskripsi) + ' resize-none'" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Tanggal Mulai <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.tanggal_mulai" type="date"
                                :class="inputCls(form.errors.tanggal_mulai)" />
                            <p v-if="form.errors.tanggal_mulai" class="mt-1 text-xs text-red-500">{{
                                form.errors.tanggal_mulai }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Batas Selesai</label>
                            <input v-model="form.tanggal_selesai" type="date"
                                :class="inputCls(form.errors.tanggal_selesai)" />
                        </div>
                    </div>
                </div>

                <!-- ── TIPE PENGERJAAN ──────────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <p class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-3">Cara Pengerjaan</p>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Mandiri -->
                        <button type="button" @click="form.tipe_pengerjaan = 'mandiri'"
                            :class="['text-left p-4 rounded-xl border-2 transition-all', form.tipe_pengerjaan === 'mandiri' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300 bg-white']">
                            <div class="text-2xl mb-2">🙋</div>
                            <p
                                :class="['text-sm font-semibold', form.tipe_pengerjaan === 'mandiri' ? 'text-blue-700' : 'text-gray-800']">
                                Mandiri
                            </p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">
                                Guru mengerjakan sendiri dan upload bukti hasil kerja (foto, link, atau teks)
                            </p>
                        </button>

                        <!-- Absen Kegiatan -->
                        <button type="button" @click="form.tipe_pengerjaan = 'absen_kegiatan'"
                            :class="['text-left p-4 rounded-xl border-2 transition-all', form.tipe_pengerjaan === 'absen_kegiatan' ? 'border-violet-500 bg-violet-50' : 'border-gray-200 hover:border-gray-300 bg-white']">
                            <div class="text-2xl mb-2">📋</div>
                            <p
                                :class="['text-sm font-semibold', form.tipe_pengerjaan === 'absen_kegiatan' ? 'text-violet-700' : 'text-gray-800']">
                                Absen Kegiatan
                            </p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">
                                Guru bertugas mengabsen kehadiran peserta kegiatan bersama (briefing, rapat, dll)
                            </p>
                        </button>
                    </div>

                    <!-- Info absen_kegiatan -->
                    <div v-if="form.tipe_pengerjaan === 'absen_kegiatan'"
                        class="flex items-start gap-3 p-3.5 bg-violet-50 border border-violet-200 rounded-xl">
                        <span class="text-violet-500 mt-0.5">ℹ️</span>
                        <p class="text-xs text-violet-700 leading-relaxed">
                            Setelah tugas dibuat dan penerima ditetapkan, guru yang bertugas akan membuka halaman
                            <strong>Absensi Kegiatan</strong> untuk mencatat kehadiran peserta. Peserta yang hadir
                            otomatis mendapat vakasi sesuai setting.
                        </p>
                    </div>

                    <!-- Wajib Laporan (hanya untuk mandiri) -->
                    <div v-if="form.tipe_pengerjaan === 'mandiri'">
                        <div class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 cursor-pointer hover:border-orange-300 transition-colors"
                            @click="form.wajib_laporan = !form.wajib_laporan">
                            <div :class="['relative rounded-full flex-shrink-0 transition-colors', form.wajib_laporan ? 'bg-orange-500' : 'bg-gray-300']"
                                style="height:22px;width:40px;">
                                <span
                                    :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200', form.wajib_laporan ? 'translate-x-5' : 'translate-x-0.5']" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Wajib Upload Bukti</p>
                                <p class="text-xs text-gray-400">Vakasi = 0 jika tidak upload bukti dan belum
                                    diverifikasi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TARGET PENERIMA ──────────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <p class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-3">Target Penerima</p>

                    <!-- Pilih tipe target -->
                    <div class="flex gap-2">
                        <button v-for="t in tipeOptions" :key="t.val" type="button"
                            @click="form.tipe = t.val; form.penerima_ids = []"
                            :class="['flex-1 py-2.5 px-3 rounded-xl border text-sm font-semibold transition-all', form.tipe === t.val ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300']">
                            {{ t.label }}
                        </button>
                    </div>

                    <!-- Pilih individu -->
                    <div v-if="form.tipe === 'individu'">
                        <label class="block text-xs font-medium text-gray-500 mb-2">Pilih Guru (bisa lebih dari
                            satu)</label>
                        <div class="space-y-1.5 max-h-56 overflow-y-auto border border-gray-200 rounded-xl p-2">
                            <label v-for="g in guru" :key="g.id"
                                class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" :value="g.id" v-model="form.penerima_ids"
                                    class="w-4 h-4 rounded text-indigo-600" />
                                <img v-if="g.foto" :src="g.foto" class="w-7 h-7 rounded-full object-cover" />
                                <div v-else
                                    class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                    {{ g.nama?.charAt(0) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ g.nama }}</p>
                                    <p class="text-xs text-gray-400">{{ g.jabatan }}</p>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">
                            {{ form.penerima_ids.length }} guru dipilih
                        </p>
                    </div>

                    <!-- Pilih jabatan -->
                    <div v-if="form.tipe === 'jabatan'">
                        <label class="block text-xs font-medium text-gray-500 mb-2">Pilih Jabatan</label>
                        <div class="space-y-1.5 max-h-44 overflow-y-auto border border-gray-200 rounded-xl p-2">
                            <label v-for="j in jabatan" :key="j.id"
                                class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" :value="j.id" v-model="form.penerima_ids"
                                    class="w-4 h-4 rounded text-indigo-600" />
                                <span :class="['px-2 py-0.5 rounded-md text-xs font-bold', tipeBadgeCls(j.tipe)]">
                                    {{ j.kode_jabatan }}
                                </span>
                                <span class="text-sm text-gray-700">{{ j.nama_jabatan }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Semua -->
                    <div v-if="form.tipe === 'semua'"
                        class="flex items-center gap-3 p-3.5 bg-indigo-50 border border-indigo-100 rounded-xl">
                        <span class="text-lg">👥</span>
                        <p class="text-sm text-indigo-700">
                            Tugas akan dikirim ke <strong>semua tenaga pendidik aktif</strong>
                        </p>
                    </div>
                </div>

                <!-- ── VAKASI ────────────────────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <p class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-3">Vakasi</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Setting Vakasi</label>
                            <!-- Gunakan :value="null" agar setting_vakasi_id tetap null (bukan '') saat dipilih -->
                            <select
                                :value="form.setting_vakasi_id ?? ''"
                                @change="form.setting_vakasi_id = $event.target.value || null"
                                :class="inputCls(form.errors.setting_vakasi_id)">
                                <option value="">Tanpa Vakasi</option>
                                <option v-for="v in vakasi" :key="v.id" :value="v.id">
                                    {{ v.nama }} — Rp {{ Number(v.nominal).toLocaleString('id-ID') }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Override Nominal</label>
                            <!-- Controlled binding: kosong → null (bukan 0 atau '') -->
                            <input
                                :value="form.vakasi_override ?? ''"
                                @input="form.vakasi_override = $event.target.value !== '' ? Number($event.target.value) : null"
                                type="number" min="0" step="1000"
                                placeholder="Kosongkan = pakai setting"
                                :class="inputCls(form.errors.vakasi_override)" />
                            <p class="text-xs text-gray-400 mt-1">Isi jika ingin nominal khusus</p>
                        </div>
                    </div>

                    <!-- Info vakasi mandiri -->
                    <div v-if="form.tipe_pengerjaan === 'mandiri' && form.setting_vakasi_id"
                        class="flex items-start gap-3 p-3.5 bg-blue-50 border border-blue-100 rounded-xl">
                        <span class="text-blue-500 shrink-0">💰</span>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            Guru yang menyelesaikan tugas dan buktinya diverifikasi akan mendapat:
                            <strong>{{ vakasiDipilihNominal }}</strong>
                        </p>
                    </div>

                    <!-- INFO KUNCI: vakasi absen_kegiatan berlaku untuk PENGABSEN dan PESERTA -->
                    <div v-if="form.tipe_pengerjaan === 'absen_kegiatan'" class="rounded-xl border overflow-hidden">
                        <div class="bg-violet-600 px-4 py-3">
                            <p class="text-sm font-semibold text-white">Skema Vakasi Absen Kegiatan</p>
                            <p class="text-xs text-violet-200 mt-0.5">Otomatis terdistribusi saat kegiatan diselesaikan</p>
                        </div>
                        <div class="p-4 space-y-3 bg-violet-50">
                            <div class="flex items-start gap-3">
                                <span class="text-lg shrink-0">🙋</span>
                                <div>
                                    <p class="text-sm font-semibold text-violet-800">Guru Pengabsen (penerima tugas)</p>
                                    <p class="text-xs text-violet-600 mt-0.5">
                                        Vakasi masuk ke slip gaji otomatis saat kegiatan diselesaikan
                                        — masuk kolom <strong>Vakasi Tugas Tambahan</strong>
                                    </p>
                                    <p v-if="form.setting_vakasi_id" class="text-xs font-bold text-violet-700 mt-1">
                                        Default: {{ vakasiDipilihNominal }} (dapat di-override per penerima)
                                    </p>
                                </div>
                            </div>
                            <div class="h-px bg-violet-200" />
                            <div class="flex items-start gap-3">
                                <span class="text-lg shrink-0">👥</span>
                                <div>
                                    <p class="text-sm font-semibold text-violet-800">Guru Peserta (yang diabsen hadir)</p>
                                    <p class="text-xs text-violet-600 mt-0.5">
                                        Vakasi masuk ke slip gaji otomatis saat kegiatan diselesaikan
                                        — masuk kolom <strong>Vakasi Peserta Kegiatan</strong>
                                    </p>
                                    <p v-if="form.setting_vakasi_id" class="text-xs font-bold text-violet-700 mt-1">
                                        {{ vakasiDipilihNominal }} per orang
                                    </p>
                                    <p v-else class="text-xs text-violet-400 mt-1 italic">
                                        Pilih template vakasi di atas untuk menetapkan nominal
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aksi -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('admin.smart-payroll.tugas-tambahan.index')"
                        class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-60">
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Tugas') }}
                    </button>
                </div>

            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    tugas: { type: Object, default: null },
    guru: { type: Array, default: () => [] },
    jabatan: { type: Array, default: () => [] },
    vakasi: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.tugas)

const form = useForm({
    judul:            props.tugas?.judul ?? '',
    deskripsi:        props.tugas?.deskripsi ?? '',
    // Gunakan _raw (format Y-m-d) yang diisi controller edit() — bukan versi tampilan 'd M Y'
    tanggal_mulai:    props.tugas?.tanggal_mulai_raw  ?? '',
    tanggal_selesai:  props.tugas?.tanggal_selesai_raw ?? '',
    tipe:             props.tugas?.tipe ?? 'individu',
    tipe_pengerjaan:  props.tugas?.tipe_pengerjaan ?? 'mandiri',
    penerima_ids:     [],
    // Nullable FK/numeric: kirim null (bukan '') agar server tidak perlu konversi
    // setting_vakasi_id: null → server menyimpan NULL di DB (tidak ada vakasi)
    // vakasi_override: null  → server menyimpan NULL (pakai setting default)
    setting_vakasi_id: props.tugas?.setting_vakasi_id ?? null,
    vakasi_override:   props.tugas?.vakasi_override   ?? null,
    wajib_laporan:    props.tugas?.wajib_laporan ?? false,
    status:           props.tugas?.status ?? 'aktif',
})

// Computed: vakasi yang dipilih
const vakasiDipilih = computed(() => props.vakasi.find(v => v.id == form.setting_vakasi_id))
const vakasiDipilihNominal = computed(() => {
    if (!vakasiDipilih.value) return '—'
    return 'Rp ' + Number(vakasiDipilih.value.nominal).toLocaleString('id-ID')
})

const tipeOptions = [
    { val: 'individu', label: 'Individu' },
    { val: 'jabatan', label: 'Per Jabatan' },
    { val: 'semua', label: 'Semua Guru' },
]

function tipeBadgeCls(tipe) {
    return {
        struktural: 'bg-indigo-100 text-indigo-700',
        fungsional: 'bg-violet-100 text-violet-700',
        mengajar: 'bg-teal-100 text-teal-700',
    }[tipe] ?? 'bg-gray-100 text-gray-700'
}

function inputCls(err) {
    const base = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return err
        ? base + ' border-red-300 focus:border-red-500 focus:ring-red-100'
        : base + ' border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'
}

function submit() {
    if (isEdit.value) {
        form.put(route('admin.smart-payroll.tugas-tambahan.update', props.tugas.id))
    } else {
        form.post(route('admin.smart-payroll.tugas-tambahan.store'))
    }
}
</script>