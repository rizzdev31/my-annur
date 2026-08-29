<template>
    <AdminLayout title="Smart Eksekusi" subtitle="Smart Habbit">

        <Head title="Smart Eksekusi" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Smart Eksekusi</h2>
                <p class="text-sm text-gray-400 mt-0.5">Lapor santri ke RamahAnak: pelanggaran / apresiasi / konselor. Hasil = menunggu keputusan Guru BK.</p>
            </div>
            <Link :href="route('admin.smart-habbit.outbox.index')" class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Monitor Outbox →</Link>
        </div>

        <div v-if="!enabled" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
            Integrasi RamahAnak <b>nonaktif</b>. Laporan tetap tersimpan di outbox & terkirim otomatis saat diaktifkan (RAMAHANAK_ENABLED + token).
        </div>

        <div class="max-w-2xl bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
            <!-- Jenis -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5">Jenis Laporan</label>
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="j in jenisOpsi" :key="j.v" @click="f.jenis = j.v; f.kode = ''"
                        :class="['py-2.5 rounded-xl border-2 text-sm font-semibold', f.jenis === j.v ? j.cls : 'border-gray-200 text-gray-600']">
                        {{ j.label }}
                    </button>
                </div>
            </div>

            <!-- Pelaku (pelanggaran/apresiasi) -->
            <div v-if="f.jenis !== 'konselor'">
                <label class="block text-xs font-medium text-gray-500 mb-1">Santri Pelaku</label>
                <select v-model.number="f.pelaku_santri_id" :class="inp">
                    <option :value="null">Pilih santri...</option>
                    <option v-for="s in santriOpsi" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nip }})</option>
                </select>
            </div>

            <!-- Korban (pelanggaran opsional / konselor wajib) -->
            <div v-if="f.jenis === 'pelanggaran' || f.jenis === 'konselor'">
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    {{ f.jenis === 'konselor' ? 'Santri (terlapor/korban)' : 'Santri Korban (opsional)' }}
                </label>
                <select v-model.number="f.korban_santri_id" :class="inp">
                    <option :value="null">{{ f.jenis === 'konselor' ? 'Pilih santri...' : 'Tanpa korban' }}</option>
                    <option v-for="s in santriOpsi" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nip }})</option>
                </select>
            </div>

            <!-- Kode -->
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kode {{ f.jenis }}</label>
                <select v-model="f.kode" :class="inp">
                    <option value="">Pilih kode...</option>
                    <option v-for="k in kodeAktif" :key="k.kode" :value="k.kode">
                        {{ k.kode }} — {{ k.label || k.kategori }}{{ k.poin != null ? ` (${k.poin} poin)` : '' }}
                    </option>
                </select>
                <p v-if="!kodeAktif.length" class="text-xs text-amber-600 mt-1">
                    Kode belum tersinkron. Buka <Link :href="route('admin.smart-habbit.outbox.index')" class="underline">Monitor Outbox</Link> → "Tarik Kode".
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                    <input v-model="f.tanggal" type="date" :class="inp" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Catatan (opsional)</label>
                <textarea v-model="f.catatan" rows="2" :class="inp" placeholder="Keterangan kejadian..."></textarea>
            </div>

            <button @click="submit" :disabled="!valid || loading"
                class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                {{ loading ? 'Mengirim...' : 'Kirim Laporan' }}
            </button>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    kode: { type: Object, default: () => ({ pelanggaran: [], apresiasi: [], konselor: [] }) },
    santriOpsi: { type: Array, default: () => [] },
    enabled: { type: Boolean, default: false },
})

const jenisOpsi = [
    { v: 'pelanggaran', label: 'Pelanggaran', cls: 'border-red-500 bg-red-50 text-red-700' },
    { v: 'apresiasi', label: 'Apresiasi', cls: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { v: 'konselor', label: 'Konselor', cls: 'border-violet-500 bg-violet-50 text-violet-700' },
]

const today = new Date().toISOString().slice(0, 10)
const f = reactive({ jenis: 'pelanggaran', kode: '', tanggal: today, catatan: '', pelaku_santri_id: null, korban_santri_id: null })
const loading = ref(false)

const kodeAktif = computed(() => props.kode[f.jenis] ?? [])
const valid = computed(() => {
    if (!f.kode || !f.tanggal) return false
    if (f.jenis === 'konselor') return !!f.korban_santri_id
    return !!f.pelaku_santri_id
})

function submit() {
    if (!valid.value) return
    loading.value = true
    router.post(route('admin.smart-habbit.eksekusi.store'), { ...f }, {
        preserveScroll: true,
        onFinish: () => { loading.value = false },
        onSuccess: () => { f.kode = ''; f.catatan = ''; f.pelaku_santri_id = null; f.korban_santri_id = null },
    })
}

const inp = 'w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white'
</script>
