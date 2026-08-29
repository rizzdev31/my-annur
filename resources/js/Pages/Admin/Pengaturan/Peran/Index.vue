<template>
    <AdminLayout title="Kelola Peran" subtitle="Akun & Peran">
        <Head title="Kelola Peran" />

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kelola Peran</h2>
                <p class="text-sm text-gray-400 mt-0.5">Buat peran & pilih akses fitur satu per satu — fleksibel per fitur.</p>
            </div>
            <button @click="buka()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Tambah Peran
            </button>
        </div>

        <!-- Daftar peran -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div v-for="p in peran" :key="p.id"
                class="bg-white rounded-2xl border border-gray-200 p-5" :class="!p.is_aktif ? 'opacity-70' : ''">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-900">{{ p.nama }}</h3>
                            <span v-if="p.is_bawaan" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600">Bawaan</span>
                            <span v-if="!p.is_aktif" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Nonaktif</span>
                        </div>
                        <p v-if="p.deskripsi" class="text-xs text-gray-500 mt-1 leading-relaxed">{{ p.deskripsi }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button @click="buka(p)" title="Edit" class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button @click="toggle(p)" :title="p.is_aktif ? 'Nonaktifkan' : 'Aktifkan'" class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </button>
                        <button v-if="!p.is_bawaan" @click="hapus(p)" title="Hapus" class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span v-for="m in p.modul" :key="m" class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-gray-100 text-gray-600">{{ namaModul(m) }}</span>
                    <span v-if="!p.modul.length" class="text-xs text-gray-400 italic">Belum ada modul</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-3">{{ p.jumlah_akun }} akun memakai peran ini</p>
            </div>
        </div>

        <!-- Modal form -->
        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="px-6 pt-5 pb-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">{{ form.id ? 'Edit Peran' : 'Tambah Peran' }}</h3>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Peran</label>
                            <input v-model="form.nama" type="text" placeholder="mis. Keuangan" :class="inp(form.errors.nama)" />
                            <p v-if="form.errors.nama" class="text-xs text-red-600 mt-1">{{ form.errors.nama }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi (opsional)</label>
                            <input v-model="form.deskripsi" type="text" :class="inp(form.errors.deskripsi)" />
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">Akses fitur <span class="text-gray-400 font-normal">({{ form.modul.length }} dipilih)</span></label>
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <button type="button" @click="pilihSemua" class="text-indigo-600 hover:text-indigo-700">Pilih semua</button>
                                    <span class="text-gray-300">·</span>
                                    <button type="button" @click="form.modul = []" class="text-gray-500 hover:text-gray-700">Kosongkan</button>
                                </div>
                            </div>
                            <div class="space-y-2.5 max-h-[46vh] overflow-y-auto pr-1 -mr-1">
                                <div v-for="(items, kat) in modulByKategori" :key="kat" class="rounded-xl border border-gray-100 overflow-hidden">
                                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-b border-gray-100">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ kat }}</p>
                                        <button type="button" @click="toggleGrup(items)"
                                            class="text-[11px] font-semibold" :class="grupSemua(items) ? 'text-gray-500 hover:text-gray-700' : 'text-indigo-600 hover:text-indigo-700'">
                                            {{ grupSemua(items) ? 'Batal grup' : 'Pilih grup' }}
                                        </button>
                                    </div>
                                    <div class="p-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                        <label v-for="m in items" :key="m.kode"
                                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                            :class="form.modul.includes(m.kode) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                            <input type="checkbox" :value="m.kode" v-model="form.modul" class="w-4 h-4 rounded text-indigo-600" />
                                            <span class="text-sm text-gray-700">{{ m.nama }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
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
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    peran: { type: Array, default: () => [] },
    modulOpsi: { type: Array, default: () => [] },
})

const showModal = ref(false)
const form = useForm({ id: null, nama: '', deskripsi: '', modul: [] })

// Kelompokkan opsi modul per kategori (urutan mengikuti config).
const modulByKategori = computed(() => {
    const g = {}
    for (const m of props.modulOpsi) (g[m.kategori ?? 'Lainnya'] ??= []).push(m)
    return g
})
function grupSemua(items) { return items.length > 0 && items.every(m => form.modul.includes(m.kode)) }
function toggleGrup(items) {
    const kodes = items.map(m => m.kode)
    if (grupSemua(items)) form.modul = form.modul.filter(k => !kodes.includes(k))
    else form.modul = [...new Set([...form.modul, ...kodes])]
}
function pilihSemua() { form.modul = props.modulOpsi.map(m => m.kode) }

function namaModul(kode) {
    return props.modulOpsi.find(m => m.kode === kode)?.nama ?? kode
}
function buka(p = null) {
    if (p) { form.id = p.id; form.nama = p.nama; form.deskripsi = p.deskripsi ?? ''; form.modul = [...p.modul] }
    else { form.reset(); form.id = null; form.modul = [] }
    form.clearErrors()
    showModal.value = true
}
function simpan() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; form.reset() } }
    if (form.id) form.put(route('admin.peran.update', form.id), opts)
    else form.post(route('admin.peran.store'), opts)
}
function toggle(p) {
    router.patch(route('admin.peran.toggle', p.id), {}, { preserveScroll: true })
}
async function hapus(p) {
    if (!(await confirm({ title: `Hapus peran "${p.nama}"?`, message: 'Akun yang memakainya kehilangan akses peran ini.', variant: 'danger', irreversible: true, confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.peran.destroy', p.id), { preserveScroll: true })
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
