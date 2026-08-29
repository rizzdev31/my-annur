<template>
    <AdminLayout title="Tahun Ajaran" subtitle="Master Data">

        <Head title="Tahun Ajaran" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Tahun Ajaran</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kelola tahun ajaran aktif untuk jadwal mengajar</p>
            </div>
            <button @click="openForm()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Tahun Ajaran
            </button>
        </div>

        <!-- Info aktif -->
        <div v-if="tahunAktif"
            class="mb-5 flex items-center gap-3 px-5 py-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl">
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-emerald-700">
                Tahun ajaran aktif saat ini:
                <strong>{{ tahunAktif.label }}</strong>
                ({{ tahunAktif.tanggal_mulai }} – {{ tahunAktif.tanggal_selesai }})
            </p>
        </div>
        <div v-else class="mb-5 flex items-center gap-3 px-5 py-3.5 bg-amber-50 border border-amber-200 rounded-2xl">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-sm text-amber-700">Belum ada tahun ajaran yang aktif. Set salah satu sebagai aktif.</p>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Tahun Ajaran</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Periode</th>
                        <th
                            class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                            Jadwal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tahunAjaran" :key="t.id"
                        :class="['hover:bg-gray-50/40 transition-colors', t.is_aktif ? 'bg-emerald-50/20' : '']">

                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-lg',
                                    t.semester === 'ganjil' ? 'bg-indigo-100' : 'bg-violet-100']">
                                    {{ t.semester === 'ganjil' ? '①' : '②' }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-800">{{ t.nama }}</p>
                                        <span v-if="t.is_aktif"
                                            class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                                            AKTIF
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 capitalize mt-0.5">Semester {{ t.semester }}</p>
                                </div>
                            </div>
                        </td>

                        <td class="px-5 py-4 hidden md:table-cell">
                            <p class="text-sm text-gray-700">{{ t.tanggal_mulai }}</p>
                            <p class="text-xs text-gray-400">s/d {{ t.tanggal_selesai }}</p>
                        </td>

                        <td class="px-5 py-4 text-center hidden lg:table-cell">
                            <p class="text-sm font-semibold text-gray-700">{{ t.jumlah_jadwal }}</p>
                            <p class="text-xs text-gray-400">jadwal</p>
                        </td>

                        <td class="px-5 py-4">
                            <span :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium',
                                t.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span
                                    :class="['w-1.5 h-1.5 rounded-full', t.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ t.is_aktif ? 'Aktif' : 'Tidak Aktif' }}
                            </span>
                        </td>

                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1">
                                <!-- Set aktif -->
                                <button v-if="!t.is_aktif" @click="setAktif(t)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                                    title="Jadikan aktif">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <!-- Edit -->
                                <button @click="openForm(t)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                    title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <!-- Hapus -->
                                <button v-if="!t.is_aktif" @click="hapus(t)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                                    title="Hapus">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!tahunAjaran.length">
                        <td colspan="5" class="py-14 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center text-xl">
                                    📅</div>
                                <p class="text-sm text-gray-500">Belum ada tahun ajaran. Buat sekarang.</p>
                                <button @click="openForm()"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                    Tambah Tahun Ajaran
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah / Edit -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeForm" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">

                    <h3 class="text-base font-semibold text-gray-900 mb-5">
                        {{ editTarget ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Nama <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.nama" type="text" placeholder="cth: 2025/2026, 2026/2027"
                                :class="inputCls(errors.nama)" />
                            <p v-if="errors.nama" class="mt-1 text-xs text-red-500">{{ errors.nama }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Semester <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" @click="form.semester = 'ganjil'" :class="[
                                    'flex items-center gap-2 px-4 py-3 rounded-xl border-2 transition-all text-sm font-medium',
                                    form.semester === 'ganjil'
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                ]">
                                    <span class="text-lg">①</span> Ganjil
                                </button>
                                <button type="button" @click="form.semester = 'genap'" :class="[
                                    'flex items-center gap-2 px-4 py-3 rounded-xl border-2 transition-all text-sm font-medium',
                                    form.semester === 'genap'
                                        ? 'border-violet-500 bg-violet-50 text-violet-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                ]">
                                    <span class="text-lg">②</span> Genap
                                </button>
                            </div>
                            <p v-if="errors.semester" class="mt-1 text-xs text-red-500">{{ errors.semester }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Tanggal Mulai <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.tanggal_mulai" type="date"
                                    :class="inputCls(errors.tanggal_mulai)" />
                                <p v-if="errors.tanggal_mulai" class="mt-1 text-xs text-red-500">{{ errors.tanggal_mulai
                                    }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Tanggal Selesai <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.tanggal_selesai" type="date" :min="form.tanggal_mulai"
                                    :class="inputCls(errors.tanggal_selesai)" />
                                <p v-if="errors.tanggal_selesai" class="mt-1 text-xs text-red-500">{{
                                    errors.tanggal_selesai }}</p>
                            </div>
                        </div>

                        <!-- Preview durasi -->
                        <div v-if="form.tanggal_mulai && form.tanggal_selesai"
                            class="px-4 py-2.5 bg-gray-50 rounded-xl text-xs text-gray-600 flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Durasi: <strong class="text-indigo-700 ml-1">{{ hitungDurasi }}</strong>
                        </div>

                        <div v-if="!editTarget" class="flex items-start gap-2.5 px-4 py-3 bg-indigo-50 rounded-xl">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs text-indigo-700">
                                Tahun ajaran baru dibuat dengan status <strong>tidak aktif</strong>.
                                Klik tombol ✓ untuk mengaktifkan setelah dibuat.
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button @click="closeForm"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submit" :disabled="saving"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ saving ? 'Menyimpan...' : (editTarget ? 'Simpan' : 'Buat') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    tahunAjaran: { type: Array, default: () => [] },
    aktif: { type: Number, default: null },
})

const tahunAktif = computed(() => props.tahunAjaran.find(t => t.is_aktif) ?? null)

// ── Modal ─────────────────────────────────────────────────────────────────────
const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const errors = ref({})

const form = reactive({
    nama: '',
    semester: 'ganjil',
    tanggal_mulai: '',
    tanggal_selesai: '',
})

const hitungDurasi = computed(() => {
    if (!form.tanggal_mulai || !form.tanggal_selesai) return '—'
    const mulai = new Date(form.tanggal_mulai)
    const selesai = new Date(form.tanggal_selesai)
    const hari = Math.round((selesai - mulai) / (1000 * 60 * 60 * 24))
    if (hari <= 0) return 'Tanggal tidak valid'
    const bulan = Math.floor(hari / 30)
    return bulan > 0 ? `±${bulan} bulan (${hari} hari)` : `${hari} hari`
})

function openForm(t = null) {
    editTarget.value = t
    if (t) {
        Object.assign(form, {
            nama: t.nama,
            semester: t.semester,
            tanggal_mulai: t.tanggal_mulai_raw ?? '',
            tanggal_selesai: t.tanggal_selesai_raw ?? '',
        })
    } else {
        Object.assign(form, { nama: '', semester: 'ganjil', tanggal_mulai: '', tanggal_selesai: '' })
    }
    errors.value = {}
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editTarget.value = null
    errors.value = {}
}

function submit() {
    saving.value = true
    const payload = { ...form }

    if (editTarget.value) {
        router.put(route('admin.master.tahun-ajaran.update', editTarget.value.id), payload, {
            onSuccess: () => closeForm(),
            onError: (e) => { errors.value = e },
            onFinish: () => saving.value = false,
        })
    } else {
        router.post(route('admin.master.tahun-ajaran.store'), payload, {
            onSuccess: () => closeForm(),
            onError: (e) => { errors.value = e },
            onFinish: () => saving.value = false,
        })
    }
}

function setAktif(t) {
    router.patch(route('admin.master.tahun-ajaran.set-aktif', t.id), {}, { preserveScroll: true })
}

async function hapus(t) {
    if (!(await confirm({ title: 'Hapus tahun ajaran?', message: `${t.nama} — ${t.semester}`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.master.tahun-ajaran.destroy', t.id), { preserveScroll: true })
}

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
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