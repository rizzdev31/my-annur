<template>
    <AdminLayout title="Inventaris" subtitle="Sarana & Peminjaman">
        <Head title="Inventaris" />

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Inventaris & Peminjaman</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kelola sarana (benda, ruang, bangunan) & pengajuan pemakaian guru</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('admin.inventaris.rekap')"
                    class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                    Rekap Bulanan
                </Link>
                <button @click="openTambah"
                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                    + Inventaris
                </button>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3">
                <p class="text-2xl font-bold text-gray-800">{{ ringkasan.total_item }}</p>
                <p class="text-xs text-gray-400">Total Item</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                <p class="text-2xl font-bold text-amber-600">{{ ringkasan.pending }}</p>
                <p class="text-xs text-amber-500">Menunggu Persetujuan</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <p class="text-2xl font-bold text-emerald-600">{{ ringkasan.dipakai_hari_ini }}</p>
                <p class="text-xs text-emerald-500">Dipakai Hari Ini</p>
            </div>
        </div>

        <!-- Monitor hari ini -->
        <div v-if="hariIni.length" class="bg-white border border-gray-200 rounded-2xl p-4 mb-5">
            <h3 class="text-sm font-bold text-gray-800 mb-2">📍 Sedang/akan dipakai hari ini</h3>
            <div class="flex flex-col gap-1.5">
                <div v-for="p in hariIni" :key="p.id" class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="font-semibold text-gray-800">{{ p.jam }}</span>
                    <span class="px-2 py-0.5 rounded bg-gray-100">{{ p.inventaris }}</span>
                    <span>oleh {{ p.peminjam }}</span>
                    <span class="text-gray-400">· {{ p.keperluan }}</span>
                </div>
            </div>
        </div>

        <!-- Pengajuan -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-bold text-gray-800">Pengajuan Pemakaian</h3>
                <div class="flex gap-1">
                    <button v-for="s in statusTabs" :key="s.v" @click="setStatus(s.v)"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                            filterStatus === s.v ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                        {{ s.label }}
                    </button>
                </div>
            </div>
            <div v-if="!pengajuan.length" class="p-8 text-center text-sm text-gray-400">Tidak ada data.</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-400 text-xs">
                    <tr>
                        <th class="text-left px-5 py-2 font-medium">Peminjam</th>
                        <th class="text-left px-3 py-2 font-medium">Item</th>
                        <th class="text-left px-3 py-2 font-medium">Tanggal</th>
                        <th class="text-left px-3 py-2 font-medium">Jam</th>
                        <th class="text-left px-3 py-2 font-medium">Keperluan</th>
                        <th class="text-left px-3 py-2 font-medium">Status</th>
                        <th class="text-right px-5 py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in pengajuan" :key="p.id" class="border-t border-gray-50 hover:bg-gray-50/60">
                        <td class="px-5 py-2.5 font-medium text-gray-800">{{ p.peminjam }}</td>
                        <td class="px-3 py-2.5">{{ p.inventaris }} <span class="text-[10px] text-gray-400">({{ p.kategori }})</span></td>
                        <td class="px-3 py-2.5 text-gray-600">{{ p.tanggal_label }}</td>
                        <td class="px-3 py-2.5 font-semibold text-gray-700">{{ p.jam }}</td>
                        <td class="px-3 py-2.5 text-gray-500 text-xs max-w-[160px] truncate">{{ p.keperluan }}</td>
                        <td class="px-3 py-2.5">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusBadge(p.status)]">{{ statusLabel(p.status) }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                            <template v-if="p.status === 'diajukan'">
                                <button @click="setujui(p)" class="text-xs text-emerald-600 hover:underline mr-2">Setujui</button>
                                <button @click="tolak(p)" class="text-xs text-red-500 hover:underline">Tolak</button>
                            </template>
                            <template v-else-if="p.status === 'disetujui'">
                                <button @click="selesai(p)" class="text-xs text-indigo-600 hover:underline mr-2">Selesai</button>
                                <button @click="batal(p)" class="text-xs text-gray-500 hover:underline">Batal</button>
                            </template>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Master Inventaris -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Daftar Inventaris</h3>
            </div>
            <div v-if="!inventaris.length" class="p-8 text-center text-sm text-gray-400">Belum ada inventaris.</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-400 text-xs">
                    <tr>
                        <th class="text-left px-5 py-2 font-medium">Kode</th>
                        <th class="text-left px-3 py-2 font-medium">Nama</th>
                        <th class="text-left px-3 py-2 font-medium">Kategori</th>
                        <th class="text-left px-3 py-2 font-medium">Lokasi</th>
                        <th class="text-center px-3 py-2 font-medium">Jumlah</th>
                        <th class="text-center px-3 py-2 font-medium">Persetujuan</th>
                        <th class="text-center px-3 py-2 font-medium">Aktif</th>
                        <th class="text-right px-5 py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="i in inventaris" :key="i.id" class="border-t border-gray-50 hover:bg-gray-50/60">
                        <td class="px-5 py-2.5 font-mono text-xs text-gray-500">{{ i.kode }}</td>
                        <td class="px-3 py-2.5 font-medium text-gray-800">{{ i.nama }}</td>
                        <td class="px-3 py-2.5 capitalize">{{ i.kategori }}</td>
                        <td class="px-3 py-2.5 text-gray-500">{{ i.lokasi || '—' }}</td>
                        <td class="px-3 py-2.5 text-center">{{ i.jumlah_total }} {{ i.satuan }}</td>
                        <td class="px-3 py-2.5 text-center">{{ i.perlu_persetujuan ? 'Ya' : 'Auto' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <span :class="i.is_aktif ? 'text-emerald-600' : 'text-gray-400'">{{ i.is_aktif ? '✓' : '✕' }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                            <button @click="openEdit(i)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</button>
                            <button @click="hapus(i)" class="text-xs text-red-500 hover:underline">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal master -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showForm = false">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-gray-800 mb-4">{{ form.id ? 'Edit' : 'Tambah' }} Inventaris</h3>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs text-gray-500 col-span-1">Kode
                        <input v-model="form.kode" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" placeholder="LAB-IPA-01" />
                    </label>
                    <label class="text-xs text-gray-500 col-span-1">Kategori
                        <select v-model="form.kategori" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm capitalize">
                            <option v-for="k in kategoriOpsi" :key="k" :value="k">{{ k }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-500 col-span-2">Nama
                        <input v-model="form.nama" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </label>
                    <label class="text-xs text-gray-500 col-span-2">Lokasi
                        <input v-model="form.lokasi" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </label>
                    <label class="text-xs text-gray-500">Jumlah total
                        <input v-model.number="form.jumlah_total" type="number" min="1" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" />
                    </label>
                    <label class="text-xs text-gray-500">Satuan
                        <input v-model="form.satuan" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm" placeholder="unit" />
                    </label>
                    <label class="text-xs text-gray-500">Kondisi
                        <select v-model="form.kondisi" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
                            <option value="baik">Baik</option>
                            <option value="perlu_perbaikan">Perlu Perbaikan</option>
                            <option value="rusak">Rusak</option>
                        </select>
                    </label>
                    <div class="flex items-center gap-4 text-xs text-gray-600 mt-5">
                        <label class="flex items-center gap-1.5"><input type="checkbox" v-model="form.perlu_persetujuan" /> Perlu persetujuan</label>
                        <label class="flex items-center gap-1.5"><input type="checkbox" v-model="form.is_aktif" /> Aktif</label>
                    </div>
                    <label class="text-xs text-gray-500 col-span-2">Keterangan
                        <textarea v-model="form.keterangan" rows="2" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"></textarea>
                    </label>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button @click="showForm = false" class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600">Batal</button>
                    <button @click="simpan" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Simpan</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    inventaris:   { type: Array, default: () => [] },
    pengajuan:    { type: Array, default: () => [] },
    hariIni:      { type: Array, default: () => [] },
    filterStatus: { type: String, default: 'diajukan' },
    kategoriOpsi: { type: Array, default: () => [] },
    ringkasan:    { type: Object, default: () => ({}) },
})

const statusTabs = [
    { v: 'diajukan', label: 'Menunggu' },
    { v: 'disetujui', label: 'Disetujui' },
    { v: 'ditolak', label: 'Ditolak' },
    { v: 'selesai', label: 'Selesai' },
    { v: 'semua', label: 'Semua' },
]
const opts = { preserveScroll: true }

function setStatus(v) {
    router.get(route('admin.inventaris.index'), { status: v }, { preserveState: false })
}

// ── Keputusan pengajuan ──
const setujui = (p) => router.patch(route('admin.inventaris.peminjaman.setujui', p.id), {}, opts)
const selesai = (p) => router.patch(route('admin.inventaris.peminjaman.selesai', p.id), {}, opts)
const batal   = async (p) => { if (await confirm({ title: 'Batalkan peminjaman ini?', variant: 'danger', confirmLabel: 'Ya, Batalkan' })) router.patch(route('admin.inventaris.peminjaman.batal', p.id), {}, opts) }
const tolak   = (p) => {
    const alasan = window.prompt('Alasan penolakan:')
    if (!alasan) return
    router.patch(route('admin.inventaris.peminjaman.tolak', p.id), { alasan }, opts)
}

// ── Master form ──
const showForm = ref(false)
const form = reactive({})
const blank = () => ({ id: null, kode: '', nama: '', kategori: 'ruang', lokasi: '', jumlah_total: 1,
    satuan: 'unit', kondisi: 'baik', perlu_persetujuan: true, is_aktif: true, keterangan: '' })

function openTambah() { Object.assign(form, blank()); showForm.value = true }
function openEdit(i)  { Object.assign(form, { ...i }); showForm.value = true }
function simpan() {
    const cb = { ...opts, onSuccess: () => { showForm.value = false } }
    if (form.id) router.patch(route('admin.inventaris.update', form.id), { ...form }, cb)
    else router.post(route('admin.inventaris.store'), { ...form }, cb)
}
async function hapus(i) {
    if (await confirm({ title: `Hapus "${i.nama}"?`, variant: 'danger', confirmLabel: 'Ya, Hapus' })) router.delete(route('admin.inventaris.destroy', i.id), opts)
}

// ── Label/badge ──
function statusLabel(s) { return { diajukan: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak', selesai: 'Selesai', dibatalkan: 'Dibatalkan' }[s] ?? s }
function statusBadge(s) {
    return { diajukan: 'bg-amber-50 text-amber-600', disetujui: 'bg-emerald-50 text-emerald-600',
             ditolak: 'bg-red-50 text-red-500', selesai: 'bg-indigo-50 text-indigo-600',
             dibatalkan: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500'
}
</script>
