<template>
    <AdminLayout title="Kelola Akun" subtitle="Akun & Peran">
        <Head title="Kelola Akun" />

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kelola Akun</h2>
                <p class="text-sm text-gray-400 mt-0.5">Akun admin & peran yang diberikan (akses fitur per bagian).</p>
            </div>
            <button @click="buka()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Tambah Akun
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                        <th class="px-5 py-3 text-left">Nama</th>
                        <th class="px-5 py-3 text-left">Peran</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in akun" :key="a.id" :class="a.status !== 'aktif' ? 'opacity-60' : ''">
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-gray-800">{{ a.name }}</p>
                            <p class="text-xs text-gray-400">{{ a.email }} · {{ a.username }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="p in a.peran" :key="p.id" class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-600">{{ p.nama }}</span>
                                <span v-if="!a.peran.length" class="text-xs text-amber-500 italic">Belum ada peran</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span :class="['text-[11px] font-semibold px-2 py-0.5 rounded-full', a.status === 'aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                {{ a.status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1">
                                <button @click="buka(a)" title="Edit" class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button @click="toggle(a)" :title="a.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'" class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </button>
                                <button @click="hapus(a)" title="Hapus" class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!akun.length"><td colspan="4" class="py-14 text-center text-sm text-gray-400">Belum ada akun admin. Klik "Tambah Akun".</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Modal form -->
        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="px-6 pt-5 pb-4 border-b border-gray-100">
                        <h3 class="text-lg font-bold text-gray-900">{{ form.id ? 'Edit Akun' : 'Tambah Akun' }}</h3>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama</label>
                                <input v-model="form.name" type="text" :class="inp(form.errors.name)" />
                                <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                                <input v-model="form.email" type="email" :class="inp(form.errors.email)" />
                                <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                                <input v-model="form.username" type="text" :class="inp(form.errors.username)" />
                                <p v-if="form.errors.username" class="text-xs text-red-600 mt-1">{{ form.errors.username }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Password {{ form.id ? '(kosongkan bila tak diubah)' : '' }}
                                </label>
                                <input v-model="form.password" type="password" autocomplete="new-password" :class="inp(form.errors.password)" />
                                <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Peran (akses fitur)</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label v-for="p in peranOpsi" :key="p.id"
                                    class="flex items-center gap-2.5 px-3 py-2 rounded-xl border cursor-pointer transition-colors"
                                    :class="form.peran.includes(p.id) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="checkbox" :value="p.id" v-model="form.peran" class="w-4 h-4 rounded text-indigo-600" />
                                    <span class="text-sm text-gray-700">{{ p.nama }}</span>
                                </label>
                                <p v-if="!peranOpsi.length" class="text-xs text-gray-400 italic col-span-2">Belum ada peran aktif — buat di "Kelola Peran" dulu.</p>
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
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

defineProps({
    akun: { type: Array, default: () => [] },
    peranOpsi: { type: Array, default: () => [] },
})

const showModal = ref(false)
const form = useForm({ id: null, name: '', email: '', username: '', password: '', peran: [] })

function buka(a = null) {
    if (a) {
        form.id = a.id; form.name = a.name; form.email = a.email; form.username = a.username
        form.password = ''; form.peran = [...a.peran_ids]
    } else { form.reset(); form.id = null; form.peran = [] }
    form.clearErrors()
    showModal.value = true
}
function simpan() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; form.reset() } }
    if (form.id) form.put(route('admin.akun.update', form.id), opts)
    else form.post(route('admin.akun.store'), opts)
}
function toggle(a) {
    router.patch(route('admin.akun.toggle', a.id), {}, { preserveScroll: true })
}
async function hapus(a) {
    if (!(await confirm({ title: `Hapus akun "${a.name}"?`, variant: 'danger', irreversible: true, confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.akun.destroy', a.id), { preserveScroll: true })
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
