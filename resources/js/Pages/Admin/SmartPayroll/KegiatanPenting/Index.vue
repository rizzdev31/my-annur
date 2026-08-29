<template>
    <AdminLayout title="Kegiatan Penting" subtitle="Guru Piket">
        <Head title="Kegiatan Penting" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Kegiatan Penting Guru</h1>
                <p class="text-sm text-gray-400 mt-0.5">Kegiatan wajib (mis. Sholat Dzuhur) yang dicatat guru piket. Sasaran mengikuti mukim/non-mukim.</p>
            </div>
            <Link :href="route('admin.smart-payroll.kegiatan-penting.laporan')"
                class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Laporan Harian
            </Link>
        </div>

        <!-- Form tambah/edit -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-5">
            <h2 class="font-semibold text-gray-800 mb-3">{{ form.id ? 'Edit Kegiatan' : 'Tambah Kegiatan' }}</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="lg:col-span-2">
                    <label class="block text-[11px] text-gray-500 mb-1">Nama kegiatan</label>
                    <input v-model="form.nama" placeholder="cth: Sholat Dzuhur Berjamaah" class="w-full rounded-lg border-gray-200 text-sm" />
                </div>
                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Sasaran</label>
                    <select v-model="form.sasaran" class="w-full rounded-lg border-gray-200 text-sm">
                        <option value="semua">Semua</option>
                        <option value="mukim">Mukim (pesantren)</option>
                        <option value="non_mukim">Non-mukim (sekolah)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Jam</label>
                    <input v-model="form.jam" type="time" class="w-full rounded-lg border-gray-200 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-1">Poin hadir</label>
                        <input v-model.number="form.poin_hadir" type="number" min="0" class="w-full rounded-lg border-gray-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-1">Poin absen</label>
                        <input v-model.number="form.poin_absen" type="number" min="0" class="w-full rounded-lg border-gray-200 text-sm" />
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button @click="simpan" :disabled="form.processing || !form.nama || !form.jam"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.id ? 'Simpan Perubahan' : 'Tambah' }}
                </button>
                <button v-if="form.id" @click="reset" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm">Batal</button>
            </div>
        </div>

        <!-- Daftar -->
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase text-gray-400">
                    <tr>
                        <th class="text-left px-4 py-2.5">Kegiatan</th>
                        <th class="text-left px-4 py-2.5">Sasaran</th>
                        <th class="text-center px-4 py-2.5">Jam</th>
                        <th class="text-center px-4 py-2.5">Poin (H/A)</th>
                        <th class="text-center px-4 py-2.5">Aktif</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!kegiatan.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada kegiatan.</td></tr>
                    <tr v-for="k in kegiatan" :key="k.id" class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ k.nama }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ labelSasaran(k.sasaran) }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ k.jam }}</td>
                        <td class="px-4 py-3 text-center tabular-nums text-gray-600">+{{ k.poin_hadir }} / −{{ k.poin_absen }}</td>
                        <td class="px-4 py-3 text-center">
                            <button @click="toggle(k)" :class="k.is_aktif ? 'bg-emerald-500' : 'bg-gray-300'" class="relative rounded-full" style="height:20px;width:36px;">
                                <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full transition-transform" :class="k.is_aktif ? 'translate-x-4' : 'translate-x-0.5'"></span>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button @click="edit(k)" class="text-indigo-600 text-xs font-semibold mr-3">Edit</button>
                            <button @click="hapus(k)" class="text-red-500 text-xs font-semibold">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ kegiatan: { type: Array, default: () => [] } })

const form = useForm({ id: null, nama: '', sasaran: 'semua', jam: '12:00', poin_hadir: 1, poin_absen: 1, is_aktif: true })

function labelSasaran(s) { return { semua: 'Semua', mukim: 'Mukim (pesantren)', non_mukim: 'Non-mukim (sekolah)' }[s] ?? s }
function reset() { form.reset(); form.id = null }
function edit(k) { form.id = k.id; form.nama = k.nama; form.sasaran = k.sasaran; form.jam = k.jam; form.poin_hadir = k.poin_hadir; form.poin_absen = k.poin_absen; form.is_aktif = k.is_aktif }
function simpan() {
    const opts = { preserveScroll: true, onSuccess: () => reset() }
    if (form.id) form.put(route('admin.smart-payroll.kegiatan-penting.update', form.id), opts)
    else form.post(route('admin.smart-payroll.kegiatan-penting.store'), opts)
}
function toggle(k) { router.patch(route('admin.smart-payroll.kegiatan-penting.toggle', k.id), {}, { preserveScroll: true }) }
function hapus(k) {
    if (!window.confirm(`Hapus kegiatan "${k.nama}"?`)) return
    router.delete(route('admin.smart-payroll.kegiatan-penting.destroy', k.id), { preserveScroll: true })
}
</script>
