<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const list = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const msg = ref(null)
const form = ref({ judul: '', deskripsi: '', tanggal: '', jam_mulai: '', durasi_menit: 60 })

const statusClass = (s) => ({
    disetujui: 'text-emerald-600 bg-emerald-50', approved: 'text-emerald-600 bg-emerald-50',
    pending: 'text-amber-600 bg-amber-50', menunggu: 'text-amber-600 bg-amber-50',
    ditolak: 'text-red-600 bg-red-50',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true
    try {
        const res = await api.get('/lembur')
        list.value = res.data.data ?? res.data ?? []
    } catch (_) {/* diamkan */} finally { loading.value = false }
}
onMounted(load)

async function ajukan() {
    msg.value = null
    const f = form.value
    if (!f.judul.trim() || !f.tanggal || !f.jam_mulai || !f.durasi_menit) {
        msg.value = { ok: false, text: 'Lengkapi judul, tanggal, jam mulai, dan durasi.' }
        return
    }
    saving.value = true
    try {
        await api.post('/lembur/ajukan', {
            judul: f.judul.trim(), deskripsi: f.deskripsi.trim() || null,
            tanggal: f.tanggal, jam_mulai: f.jam_mulai, durasi_menit: Number(f.durasi_menit),
        })
        msg.value = { ok: true, text: 'Pengajuan lembur terkirim.' }
        showForm.value = false
        form.value = { judul: '', deskripsi: '', tanggal: '', jam_mulai: '', durasi_menit: 60 }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim.') }
    } finally { saving.value = false }
}

const jamMenit = (m) => { m = Number(m) || 0; const h = Math.floor(m / 60), mn = m % 60; return (h ? h + 'j ' : '') + (mn ? mn + 'm' : (h ? '' : '0m')) }
</script>

<template>
    <div>
        <PageHeader title="Lembur" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <button @click="showForm = !showForm"
                class="w-full py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm mb-4 active:scale-[0.99] transition">
                {{ showForm ? 'Tutup Form' : '+ Ajukan Lembur' }}
            </button>

            <div v-if="showForm" class="rounded-2xl bg-white border border-gray-100 p-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Judul</label>
                    <input v-model="form.judul" type="text" placeholder="mis. Persiapan acara" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Deskripsi (opsional)</label>
                    <textarea v-model="form.deskripsi" rows="2" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"></textarea>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input v-model="form.tanggal" type="date" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Jam</label>
                        <input v-model="form.jam_mulai" type="time" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Durasi(m)</label>
                        <input v-model="form.durasi_menit" type="number" min="1" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" /></div>
                </div>
                <button @click="ajukan" :disabled="saving" class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">
                    {{ saving ? 'Mengirim…' : 'Kirim' }}
                </button>
            </div>

            <div v-if="!list.length" class="pt-10 text-center text-sm text-gray-400">Belum ada pengajuan lembur.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="l in list" :key="l.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">{{ l.judul }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="statusClass(l.status)">{{ l.status_label || l.status || '—' }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ l.tanggal }} · {{ (l.jam_mulai || '').slice(0,5) }} · {{ jamMenit(l.durasi_menit) }}</p>
                    <p v-if="l.deskripsi" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ l.deskripsi }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
