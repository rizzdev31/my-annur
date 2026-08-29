<template>
    <AdminLayout :title="isEdit ? 'Edit Tugas Jabatan' : 'Tambah Tugas Jabatan'" subtitle="Smart Payroll">

        <Head :title="isEdit ? 'Edit Tugas Jabatan' : 'Tambah Tugas Jabatan'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.tugas-jabatan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Tugas Jabatan' : 'Tambah Tugas Jabatan' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ isEdit ? 'Ubah tugas: ' + form.nama_tugas : 'Buat tugas baru untuk jabatan tertentu' }}
                </p>
            </div>
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5">

                <!-- Info jabatan dipilih -->
                <div v-if="jabatanDipilih"
                    class="flex items-center gap-3 px-4 py-3 bg-indigo-50 border border-indigo-100 rounded-xl">
                    <div
                        :class="['w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold shrink-0', bgTipe(jabatanDipilih.tipe)]">
                        {{ jabatanDipilih.kode_jabatan }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-indigo-800">{{ jabatanDipilih.nama_jabatan }}</p>
                        <p class="text-xs text-indigo-500 capitalize">{{ jabatanDipilih.tipe }}</p>
                    </div>
                </div>

                <!-- ── INFORMASI DASAR ─────────────────────────────── -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <p class="text-sm font-semibold text-gray-800 border-b border-gray-100 pb-3">Informasi Tugas</p>

                    <!-- Jabatan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Jabatan <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.jabatan_id" :class="inputCls(form.errors.jabatan_id)"
                            @change="updateJabatanDipilih">
                            <option value="">-- Pilih Jabatan --</option>
                            <optgroup v-for="(items, tipe) in jabatanByTipe" :key="tipe" :label="labelTipe[tipe]">
                                <option v-for="j in items" :key="j.id" :value="j.id">
                                    {{ j.nama_jabatan }} ({{ j.kode_jabatan }})
                                </option>
                            </optgroup>
                        </select>
                        <p v-if="form.errors.jabatan_id" class="mt-1 text-xs text-red-500">{{ form.errors.jabatan_id }}
                        </p>
                    </div>

                    <!-- Nama Tugas -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nama Tugas <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nama_tugas" type="text"
                            placeholder="cth: Mengisi jurnal kelas, Absen briefing bulanan..."
                            :class="inputCls(form.errors.nama_tugas)" />
                        <p v-if="form.errors.nama_tugas" class="mt-1 text-xs text-red-500">{{ form.errors.nama_tugas }}
                        </p>
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea v-model="form.deskripsi" rows="2" placeholder="Detail tugas yang harus dikerjakan..."
                            :class="inputCls(form.errors.deskripsi) + ' resize-none'" />
                    </div>

                    <!-- Frekuensi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Frekuensi <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.frekuensi" :class="inputCls(form.errors.frekuensi)">
                            <option value="harian">Harian</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="bulanan">Bulanan</option>
                            <option value="insidental">Insidental</option>
                        </select>
                    </div>
                </div>

                <!-- ── TIPE PENGERJAAN ──────────────────────────────── -->
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
                                Guru bertugas mengabsen peserta kegiatan bersama (briefing, rapat, dll)
                            </p>
                        </button>
                    </div>

                    <!-- Toggle: Perlu Verifikasi -->
                    <div class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 cursor-pointer hover:border-indigo-300 transition-colors"
                        @click="form.perlu_verifikasi = !form.perlu_verifikasi">
                        <div :class="['relative rounded-full flex-shrink-0 transition-colors', form.perlu_verifikasi ? 'bg-indigo-500' : 'bg-gray-300']"
                            style="height:22px;width:40px;">
                            <span
                                :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200', form.perlu_verifikasi ? 'translate-x-5' : 'translate-x-0.5']" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Perlu Verifikasi Admin</p>
                            <p class="text-xs text-gray-400">
                                {{ form.perlu_verifikasi
                                    ? 'Realisasi guru harus disetujui admin agar dihitung di kinerja.'
                                    : 'Realisasi langsung sah & dihitung tanpa verifikasi.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── PENILAIAN KINERJA (tanpa vakasi) ─────────────── -->
                <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5 flex items-start gap-3">
                    <span class="text-indigo-500 shrink-0 mt-0.5 text-lg">📊</span>
                    <p class="text-xs text-indigo-700 leading-relaxed">
                        Tugas jabatan <strong>tidak menghasilkan vakasi</strong>. Penyelesaiannya dinilai di
                        <strong>Kinerja</strong> bulanan sebagai faktor kelayakan jabatan. Target dihitung per bulan
                        sesuai frekuensi (harian = tiap hari kerja, mingguan = tiap minggu, bulanan = sekali);
                        melewatkan satu pengerjaan menurunkan skor. Deadline pengerjaan harian = jam masuk.
                    </p>
                </div>

                <!-- Aksi -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('admin.smart-payroll.tugas-jabatan.index')"
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
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Tambah Tugas') }}
                    </button>
                </div>

            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    tugas: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.tugas)
const queryJabatanId = new URLSearchParams(window.location.search).get('jabatan_id') ?? ''

const form = useForm({
    jabatan_id: props.tugas?.jabatan_id ?? queryJabatanId ?? '',
    nama_tugas: props.tugas?.nama_tugas ?? '',
    deskripsi: props.tugas?.deskripsi ?? '',
    frekuensi: props.tugas?.frekuensi ?? 'bulanan',
    tipe_pengerjaan: props.tugas?.tipe_pengerjaan ?? 'mandiri',
    perlu_verifikasi: props.tugas?.perlu_verifikasi ?? true,
})

const labelTipe = { struktural: 'Struktural', fungsional: 'Fungsional', mengajar: 'Mengajar' }

const jabatanByTipe = computed(() => {
    const groups = {}
    props.jabatan.forEach(j => {
        if (!groups[j.tipe]) groups[j.tipe] = []
        groups[j.tipe].push(j)
    })
    return groups
})

const jabatanDipilih = ref(
    props.jabatan.find(j => j.id === (parseInt(form.jabatan_id) || 0)) ?? null
)

function updateJabatanDipilih() {
    jabatanDipilih.value = props.jabatan.find(j => j.id === form.jabatan_id) ?? null
}

function bgTipe(tipe) {
    return {
        struktural: 'bg-indigo-100 text-indigo-700',
        fungsional: 'bg-violet-100 text-violet-700',
        mengajar: 'bg-teal-100 text-teal-700'
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
        form.put(route('admin.smart-payroll.tugas-jabatan.update', props.tugas.id))
    } else {
        form.post(route('admin.smart-payroll.tugas-jabatan.store'))
    }
}
</script>