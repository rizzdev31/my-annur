<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const items = ref([])
const pinjaman = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const msg = ref(null)
const form = ref({ inventaris_id: '', jumlah: 1, keperluan: '', tanggal: '', jam_mulai: '', jam_selesai: '' })

const statusClass = (s) => ({
    disetujui: 'text-emerald-600 bg-emerald-50', dipinjam: 'text-blue-600 bg-blue-50',
    pending: 'text-amber-600 bg-amber-50', menunggu: 'text-amber-600 bg-amber-50',
    ditolak: 'text-red-600 bg-red-50', dikembalikan: 'text-gray-500 bg-gray-100', selesai: 'text-gray-500 bg-gray-100',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true
    try {
        const [a, b] = await Promise.all([api.get('/inventaris'), api.get('/inventaris/peminjaman')])
        items.value = a.data.data ?? a.data ?? []
        pinjaman.value = b.data.data ?? b.data ?? []
    } catch (_) {/* diamkan */} finally { loading.value = false }
}
onMounted(load)

async function ajukan() {
    msg.value = null
    const f = form.value
    if (!f.inventaris_id || !f.keperluan.trim() || !f.tanggal || !f.jam_mulai || !f.jam_selesai) {
        msg.value = { ok: false, text: 'Lengkapi barang, keperluan, tanggal, dan jam.' }
        return
    }
    saving.value = true
    try {
        await api.post('/inventaris/peminjaman', {
            inventaris_id: f.inventaris_id, jumlah: Number(f.jumlah) || 1,
            keperluan: f.keperluan.trim(), tanggal: f.tanggal,
            jam_mulai: f.jam_mulai, jam_selesai: f.jam_selesai,
        })
        msg.value = { ok: true, text: 'Pengajuan peminjaman terkirim.' }
        showForm.value = false
        form.value = { inventaris_id: '', jumlah: 1, keperluan: '', tanggal: '', jam_mulai: '', jam_selesai: '' }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim.') }
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Inventaris" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <button @click="showForm = !showForm"
                class="w-full py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm mb-4 active:scale-[0.99] transition">
                {{ showForm ? 'Tutup Form' : '+ Ajukan Peminjaman' }}
            </button>

            <div v-if="showForm" class="rounded-2xl bg-white border border-gray-100 p-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Barang</label>
                    <select v-model="form.inventaris_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]">
                        <option value="">— pilih barang —</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.nama }} ({{ i.jumlah_total }} {{ i.satuan }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Jumlah</label>
                        <input v-model="form.jumlah" type="number" min="1" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input v-model="form.tanggal" type="date" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Jam Mulai</label>
                        <input v-model="form.jam_mulai" type="time" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Jam Selesai</label>
                        <input v-model="form.jam_selesai" type="time" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Keperluan</label>
                    <input v-model="form.keperluan" type="text" placeholder="untuk apa dipinjam" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                </div>
                <button @click="ajukan" :disabled="saving" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">
                    {{ saving ? 'Mengirim…' : 'Kirim' }}
                </button>
            </div>

            <h2 class="text-sm font-bold text-gray-800 mb-2">Peminjaman Saya</h2>
            <div v-if="!pinjaman.length" class="py-8 text-center text-sm text-gray-400">Belum ada peminjaman.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="p in pinjaman" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">{{ p.inventaris || p.nama_barang || p.barang || 'Barang' }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="statusClass(p.status)">{{ p.status_label || p.status }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ p.tanggal }} · {{ (p.jam_mulai || '').slice(0,5) }}–{{ (p.jam_selesai || '').slice(0,5) }}<span v-if="p.jumlah"> · {{ p.jumlah }} unit</span></p>
                    <p v-if="p.keperluan" class="text-xs text-gray-500 mt-1">{{ p.keperluan }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
