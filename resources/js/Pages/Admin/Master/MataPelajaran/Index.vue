<template>
    <AdminLayout title="Mata Pelajaran" subtitle="Master Data">

        <Head title="Mata Pelajaran" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Mata Pelajaran</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ summary.aktif }} aktif dari {{ summary.total }} total mata pelajaran
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <ImportExcel :template-url="route('admin.master.mata-pelajaran.template-import')"
                    :import-url="route('admin.master.mata-pelajaran.import')" label="Import Mapel" />
                <button @click="showForm = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Mapel
                </button>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Mata Pelajaran</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Kategori</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Tingkat</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Jadwal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="m in mapel" :key="m.id"
                        :class="['hover:bg-gray-50/40 transition-colors', !m.is_aktif ? 'opacity-60' : '']">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span
                                    class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-mono font-semibold shrink-0">
                                    {{ m.kode }}
                                </span>
                                <p class="text-sm font-medium text-gray-800">{{ m.nama }}</p>
                                <span v-if="m.tipe && m.tipe !== 'reguler'" :class="[
                                    'px-2 py-0.5 rounded-lg text-xs font-semibold capitalize shrink-0',
                                    m.tipe === 'tahfidz' ? 'bg-emerald-50 text-emerald-700' : 'bg-violet-50 text-violet-700'
                                ]">{{ m.tipe }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span v-if="m.kategori" class="text-xs px-2 py-1 rounded-lg bg-gray-100 text-gray-600">
                                {{ m.kategori }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span v-if="m.tingkat" class="text-sm text-gray-600 capitalize">{{ m.tingkat }}</span>
                            <span v-else class="text-xs text-gray-400">Semua tingkat</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="text-sm font-semibold text-gray-700">{{ m.jumlah_jadwal }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
                                m.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span
                                    :class="['w-1.5 h-1.5 rounded-full', m.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ m.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1">
                                <button @click="editMapel(m)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button @click="hapusMapel(m)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!mapel.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                            Belum ada mata pelajaran. Tambahkan sekarang.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal tambah/edit -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeForm" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-5">
                        {{ editTarget ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran' }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Nama <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.nama" type="text" placeholder="cth: Matematika, Fisika, Tahfidz"
                                :class="inputCls(form.errors?.nama)" />
                            <p v-if="form.errors?.nama" class="mt-1 text-xs text-red-500">{{ form.errors.nama }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Kode <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.kode" type="text" placeholder="cth: MTK, FIS, THF"
                                :class="inputCls(form.errors?.kode)" style="text-transform:uppercase" />
                            <p v-if="form.errors?.kode" class="mt-1 text-xs text-red-500">{{ form.errors.kode }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori</label>
                                <input v-model="form.kategori" type="text" placeholder="cth: Umum, Agama, Ekskul"
                                    :class="inputCls()" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tingkat</label>
                                <input v-model="form.tingkat" type="text" placeholder="cth: VII, VIII, IX"
                                    :class="inputCls()" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipe Pembelajaran</label>
                            <select v-model="form.tipe" :class="inputCls()">
                                <option value="reguler">Reguler (sekolah)</option>
                                <option value="tahfidz">Tahfidz (setoran hafalan)</option>
                                <option value="tahsin">Tahsin (materi + tes)</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Tahfidz/Tahsin memakai form jurnal Smart Tahfidz.</p>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button @click="closeForm"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submitForm" :disabled="saving"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ saving ? 'Menyimpan...' : (editTarget ? 'Simpan' : 'Tambah') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'
import ImportExcel from '@/Components/ImportExcel.vue'

const props = defineProps({
    mapel: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)

const form = reactive({ nama: '', kode: '', kategori: '', tingkat: '', tipe: 'reguler', errors: {} })

function editMapel(m) {
    editTarget.value = m
    Object.assign(form, { nama: m.nama, kode: m.kode, kategori: m.kategori ?? '', tingkat: m.tingkat ?? '', tipe: m.tipe ?? 'reguler', errors: {} })
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editTarget.value = null
    Object.assign(form, { nama: '', kode: '', kategori: '', tingkat: '', tipe: 'reguler', errors: {} })
}

function submitForm() {
    saving.value = true
    const payload = { nama: form.nama, kode: form.kode, kategori: form.kategori, tingkat: form.tingkat, tipe: form.tipe }

    if (editTarget.value) {
        router.put(route('admin.master.mata-pelajaran.update', editTarget.value.id), payload, {
            onSuccess: () => closeForm(),
            onError: (e) => { form.errors = e },
            onFinish: () => saving.value = false,
        })
    } else {
        router.post(route('admin.master.mata-pelajaran.store'), payload, {
            onSuccess: () => closeForm(),
            onError: (e) => { form.errors = e },
            onFinish: () => saving.value = false,
        })
    }
}

async function hapusMapel(m) {
    if (!(await confirm({ title: `Nonaktifkan "${m.nama}"?`, variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' }))) return
    router.delete(route('admin.master.mata-pelajaran.destroy', m.id), { preserveScroll: true })
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