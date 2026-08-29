<template>
    <AdminLayout title="Rubrik Kategori Piket" subtitle="Guru Piket">

        <Head title="Rubrik Kategori Piket" />

        <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Rubrik Kategori Penilaian</h2>
                <p class="text-sm text-gray-400 mt-0.5">Poin baku per kategori → konsisten antar-penilai. Apresiasi menambah, catatan mengurangi sub-skor piket (mulai 100).</p>
            </div>
            <button @click="bukaTambah" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">+ Kategori</button>
        </div>

        <!-- Form tambah/edit -->
        <div v-if="form.show" class="bg-white rounded-2xl border border-indigo-200 p-5 mb-5">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-semibold text-gray-800">{{ form.id ? 'Edit' : 'Tambah' }} Kategori</p>
                <button @click="form.show = false" class="text-xs text-gray-500 hover:text-gray-700">✕ Tutup</button>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nama Kategori</label>
                    <input v-model="form.nama" :class="inp" placeholder="mis. Metode interaktif / Telat masuk kelas" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Jenis</label>
                    <div class="flex gap-2">
                        <button v-for="j in ['apresiasi','catatan']" :key="j" @click="form.jenis = j"
                            :class="['flex-1 py-2 rounded-xl text-sm font-semibold border', form.jenis === j ? (j==='apresiasi'?'bg-emerald-600 text-white border-emerald-600':'bg-red-600 text-white border-red-600') : 'bg-white text-gray-600 border-gray-200']">
                            {{ j === 'apresiasi' ? 'Apresiasi (+)' : 'Catatan (−)' }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dimensi (untuk laporan)</label>
                    <select v-model="form.dimensi" :class="inp">
                        <option value="disiplin">Disiplin</option>
                        <option value="tugas">Tugas</option>
                        <option value="administrasi">Administrasi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Bobot Dampak (1–10)</label>
                    <input v-model.number="form.poin" type="number" min="1" max="10" step="1" :class="inp" />
                    <p class="text-[11px] text-gray-400 mt-1">Skala 1 (ringan) – 10 (berat). Memengaruhi skor kinerja piket.</p>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.is_aktif" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        Aktif
                    </label>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button @click="simpan" :disabled="!valid || saving"
                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                    {{ saving ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-4 py-2.5 text-left">Kategori</th>
                    <th class="px-3 py-2.5 text-center">Jenis</th>
                    <th class="px-3 py-2.5 text-center">Dimensi</th>
                    <th class="px-3 py-2.5 text-center">Poin</th>
                    <th class="px-3 py-2.5 text-center">Status</th>
                    <th class="px-3 py-2.5 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="k in kategori" :key="k.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-2.5 font-semibold text-gray-700">{{ k.nama }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span :class="['text-xs font-semibold px-2 py-0.5 rounded', k.jenis==='apresiasi' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600']">
                                {{ k.jenis === 'apresiasi' ? '+ Apresiasi' : '− Catatan' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600 capitalize">{{ k.dimensi }}</span></td>
                        <td class="px-3 py-2.5 text-center font-semibold" :class="k.jenis==='apresiasi' ? 'text-emerald-600' : 'text-red-500'">
                            {{ k.jenis==='apresiasi' ? '+' : '−' }}{{ k.poin }}
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span :class="['text-xs font-semibold', k.is_aktif ? 'text-emerald-600' : 'text-gray-400']">{{ k.is_aktif ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <button @click="bukaEdit(k)" class="text-xs font-semibold text-indigo-600 hover:underline mr-3">Edit</button>
                            <button @click="hapus(k)" class="text-xs font-semibold text-red-500 hover:underline">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!kategori.length"><td colspan="6" class="py-12 text-center text-gray-400 text-sm">Belum ada kategori. Tambah lewat tombol "+ Kategori".</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

defineProps({ kategori: { type: Array, default: () => [] } })

const blank = { show: false, id: null, nama: '', jenis: 'apresiasi', dimensi: 'tugas', poin: 3, is_aktif: true }
const form = reactive({ ...blank })
const saving = computed(() => false)

const valid = computed(() => form.nama.trim() && form.poin >= 1 && form.poin <= 10)

function bukaTambah() { Object.assign(form, blank, { show: true }) }
function bukaEdit(k) { Object.assign(form, { show: true, id: k.id, nama: k.nama, jenis: k.jenis, dimensi: k.dimensi, poin: k.poin, is_aktif: k.is_aktif }) }

function simpan() {
    if (!valid.value) return
    const payload = { nama: form.nama.trim(), jenis: form.jenis, dimensi: form.dimensi, poin: form.poin, is_aktif: form.is_aktif }
    const opts = { preserveScroll: true, onSuccess: () => { form.show = false } }
    if (form.id) router.put(route('admin.piket.kategori.update', form.id), payload, opts)
    else router.post(route('admin.piket.kategori.store'), payload, opts)
}

async function hapus(k) {
    if (!(await confirm({ title: `Hapus kategori "${k.nama}"?`, message: 'Bila sudah dipakai, hanya akan dinonaktifkan.', variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.piket.kategori.destroy', k.id), { preserveScroll: true })
}

const inp = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>
