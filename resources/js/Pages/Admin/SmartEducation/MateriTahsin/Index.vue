<template>
    <AdminLayout title="Materi Tahsin" subtitle="Smart Education">

        <Head title="Materi Tahsin" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Materi Tahsin</h2>
                <p class="text-sm text-gray-400 mt-0.5">Susun materi terstruktur per level (1–5 + Persiapan Tahfidz). Tiap materi selesai → wajib tes/penilaian.</p>
            </div>
            <button @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Tambah Materi
            </button>
        </div>

        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="opt in levelOpsi" :key="opt.value" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-violet-50/60 border-b border-violet-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-violet-700">{{ opt.label }}</h3>
                    <span class="text-xs text-gray-400">{{ byLevel(opt.value).length }} materi</span>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="(m, i) in byLevel(opt.value)" :key="m.id" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50/50">
                        <span class="w-6 h-6 rounded-lg bg-gray-100 text-gray-500 text-xs font-bold flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                        <p class="flex-1 text-sm text-gray-800">{{ m.nama }}</p>
                        <button @click="openEdit(m)" class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button @click="hapus(m)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <p v-if="!byLevel(opt.value).length" class="px-4 py-6 text-center text-xs text-gray-400">Belum ada materi.</p>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeForm" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-5">{{ editTarget ? 'Edit Materi' : 'Tambah Materi' }}</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Level <span class="text-red-500">*</span></label>
                                <select v-model.number="form.level" :class="inputCls(form.errors?.level)">
                                    <option v-for="opt in levelOpsi" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Urutan</label>
                                <input v-model.number="form.urutan" type="number" min="0" :class="inputCls()" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Materi <span class="text-red-500">*</span></label>
                            <input v-model="form.nama" type="text" placeholder="cth: Makhraj huruf, Mad thabi'i, Ghunnah"
                                :class="inputCls(form.errors?.nama)" />
                            <p v-if="form.errors?.nama" class="mt-1 text-xs text-red-500">{{ form.errors.nama }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="closeForm" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                        <button @click="submit" :disabled="saving"
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

const props = defineProps({
    materi: { type: Array, default: () => [] },
    levelOpsi: { type: Array, default: () => [] },
})

const byLevel = (lv) => props.materi.filter(m => m.level === lv)

const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const blank = () => ({ level: 1, urutan: 0, nama: '', errors: {} })
const form = reactive(blank())

function openCreate() { editTarget.value = null; Object.assign(form, blank()); showForm.value = true }
function openEdit(m) {
    editTarget.value = m
    Object.assign(form, { level: m.level, urutan: m.urutan, nama: m.nama, errors: {} })
    showForm.value = true
}
function closeForm() { showForm.value = false; editTarget.value = null; Object.assign(form, blank()) }
function submit() {
    saving.value = true
    const payload = { level: form.level, urutan: form.urutan, nama: form.nama }
    const opts = { preserveScroll: true, onSuccess: () => closeForm(), onError: (e) => { form.errors = e }, onFinish: () => saving.value = false }
    if (editTarget.value) router.put(route('admin.smart-education.materi-tahsin.update', editTarget.value.id), payload, opts)
    else router.post(route('admin.smart-education.materi-tahsin.store'), payload, opts)
}
async function hapus(m) {
    if (!(await confirm({ title: `Hapus materi "${m.nama}"?`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.smart-education.materi-tahsin.destroy', m.id), { preserveScroll: true })
}
function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease; }
.modal-leave-active { transition: all 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
