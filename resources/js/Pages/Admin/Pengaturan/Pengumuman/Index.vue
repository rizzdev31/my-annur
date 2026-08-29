<template>
    <AdminLayout title="Pengumuman" subtitle="Pamflet Aplikasi">
        <Head title="Pengumuman" />

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Pengumuman / Pamflet</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Pamflet ini tampil sebagai pop-up saat guru membuka aplikasi. Hanya <b>satu</b> yang aktif dalam satu waktu.
                </p>
            </div>
            <button @click="buka()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Tambah Pengumuman
            </button>
        </div>

        <!-- Grid pamflet -->
        <div v-if="pengumuman.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="p in pengumuman" :key="p.id"
                class="bg-white rounded-2xl border overflow-hidden flex flex-col transition-shadow hover:shadow-md"
                :class="p.aktif ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-gray-200'">
                <!-- Thumbnail (rasio mobile 9:16) -->
                <div class="relative bg-gray-100" style="aspect-ratio: 9 / 16">
                    <img :src="p.gambar_url" :alt="p.judul || 'Pamflet'" class="absolute inset-0 w-full h-full object-cover" />
                    <span v-if="p.aktif"
                        class="absolute top-2 left-2 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500 text-white shadow">
                        ● AKTIF
                    </span>
                </div>
                <div class="p-3 flex flex-col gap-2 flex-1">
                    <p class="text-sm font-semibold text-gray-800 line-clamp-2 min-h-[2.5rem]">
                        {{ p.judul || 'Tanpa judul' }}
                    </p>
                    <p class="text-[11px] text-gray-400">{{ p.updated_at }}</p>
                    <div class="flex items-center gap-1 mt-auto pt-1">
                        <button @click="toggle(p)"
                            :class="['flex-1 text-[11px] font-semibold px-2 py-1.5 rounded-lg transition-colors',
                                p.aktif ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                            {{ p.aktif ? 'Aktif' : 'Aktifkan' }}
                        </button>
                        <button @click="buka(p)" title="Edit" class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button @click="hapus(p)" title="Hapus" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-400">Belum ada pengumuman. Klik "Tambah Pengumuman" untuk mengunggah pamflet.</p>
        </div>

        <!-- Modal form -->
        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="px-6 pt-5 pb-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">{{ form.id ? 'Edit Pengumuman' : 'Tambah Pengumuman' }}</h3>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <!-- Upload gambar + preview -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Pamflet <span class="text-gray-400">(rasio mobile disarankan 9:16, mis. 1080×1920 atau lebih kecil)</span>
                            </label>
                            <div class="flex items-start gap-4">
                                <div class="w-28 shrink-0 rounded-xl border border-dashed border-gray-300 bg-gray-50 overflow-hidden"
                                    style="aspect-ratio: 9 / 16">
                                    <img v-if="preview" :src="preview" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-[11px] text-gray-400 text-center px-2">
                                        Pratinjau
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <input type="file" accept="image/*" @change="pilihGambar"
                                        class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100" />
                                    <p class="text-[11px] text-gray-400 mt-2">JPG/PNG/WEBP, maks 4MB. {{ form.id ? 'Kosongkan bila tak ingin mengganti gambar.' : '' }}</p>
                                    <p v-if="form.errors.gambar" class="text-xs text-red-600 mt-1">{{ form.errors.gambar }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Judul <span class="text-gray-400">(opsional)</span></label>
                            <input v-model="form.judul" type="text" placeholder="mis. Info Libur Maulid Nabi" :class="inp(form.errors.judul)" />
                            <p v-if="form.errors.judul" class="text-xs text-red-600 mt-1">{{ form.errors.judul }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Link (opsional)</label>
                            <input v-model="form.link_url" type="url" placeholder="https://..." :class="inp(form.errors.link_url)" />
                            <p class="text-[11px] text-gray-400 mt-1">Bila diisi, pamflet bisa diketuk untuk membuka tautan ini.</p>
                            <p v-if="form.errors.link_url" class="text-xs text-red-600 mt-1">{{ form.errors.link_url }}</p>
                        </div>

                        <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer"
                            :class="form.aktif ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200'">
                            <input type="checkbox" v-model="form.aktif" class="w-4 h-4 rounded text-indigo-600" />
                            <span class="text-sm text-gray-700">Tampilkan sebagai pop-up sekarang <span class="text-gray-400">(menonaktifkan pamflet lain)</span></span>
                        </label>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                        <button @click="showModal = false" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100">Batal</button>
                        <button @click="simpan" :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

defineProps({
    pengumuman: { type: Array, default: () => [] },
})

const showModal = ref(false)
const preview   = ref(null)
const form = useForm({ id: null, judul: '', link_url: '', gambar: null, aktif: true })

function buka(p = null) {
    if (p) {
        form.id = p.id; form.judul = p.judul || ''; form.link_url = p.link_url || ''
        form.gambar = null; form.aktif = p.aktif
        preview.value = p.gambar_url
    } else {
        form.reset(); form.id = null; form.aktif = true
        preview.value = null
    }
    form.clearErrors()
    showModal.value = true
}

function pilihGambar(e) {
    const file = e.target.files?.[0]
    form.gambar = file || null
    preview.value = file ? URL.createObjectURL(file) : preview.value
}

function simpan() {
    const opts = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showModal.value = false; form.reset(); preview.value = null },
    }
    // Route update memakai POST (upload file) — tanpa method spoofing PUT.
    if (form.id) form.post(route('admin.pengumuman.update', form.id), opts)
    else form.post(route('admin.pengumuman.store'), opts)
}

function toggle(p) {
    router.patch(route('admin.pengumuman.toggle', p.id), {}, { preserveScroll: true })
}

async function hapus(p) {
    if (!(await confirm({ title: `Hapus pengumuman "${p.judul || 'ini'}"?`, variant: 'danger', irreversible: true, confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.pengumuman.destroy', p.id), { preserveScroll: true })
}

const inp = (e) => [
    'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white',
    e ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100',
]
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity .2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
