<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const stats = ref(null)
const list = ref([])
const jenis = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const msg = ref(null)

const form = ref({ setting_jenis_pengajuan_id: '', tanggal_mulai: '', tanggal_selesai: '', alasan: '', dokumen: null })

const statusClass = (s) => ({
    disetujui: 'text-emerald-600 bg-emerald-50',
    pending: 'text-amber-600 bg-amber-50',
    ditolak: 'text-red-600 bg-red-50',
}[s] || 'text-gray-500 bg-gray-100')

async function load() {
    loading.value = true
    try {
        const [r, j] = await Promise.all([api.get('/izin'), api.get('/izin/jenis')])
        const d = r.data.data ?? r.data
        stats.value = d.stats ?? null
        list.value = d.riwayat ?? d.list ?? []
        jenis.value = j.data.data ?? j.data ?? []
    } catch (_) {/* diamkan */} finally { loading.value = false }
}
onMounted(load)

function pilihDokumen(e) { form.value.dokumen = e.target.files?.[0] || null }

async function ajukan() {
    msg.value = null
    const f = form.value
    if (!f.setting_jenis_pengajuan_id || !f.tanggal_mulai || !f.tanggal_selesai || f.alasan.trim().length < 10) {
        msg.value = { ok: false, text: 'Lengkapi jenis, tanggal, dan alasan (min. 10 karakter).' }
        return
    }
    saving.value = true
    try {
        const fd = new FormData()
        fd.append('setting_jenis_pengajuan_id', f.setting_jenis_pengajuan_id)
        fd.append('tanggal_mulai', f.tanggal_mulai)
        fd.append('tanggal_selesai', f.tanggal_selesai)
        fd.append('alasan', f.alasan.trim())
        if (f.dokumen) fd.append('dokumen', f.dokumen)
        await api.post('/izin', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        msg.value = { ok: true, text: 'Pengajuan terkirim.' }
        showForm.value = false
        form.value = { setting_jenis_pengajuan_id: '', tanggal_mulai: '', tanggal_selesai: '', alasan: '', dokumen: null }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim pengajuan.') }
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Pengajuan Izin" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <!-- Ringkasan -->
            <div v-if="stats" class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-amber-500">{{ stats.pending ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Pending</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-emerald-600">{{ stats.disetujui ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Disetujui</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                    <p class="text-xl font-extrabold text-red-500">{{ stats.ditolak ?? 0 }}</p>
                    <p class="text-[10px] text-gray-400">Ditolak</p>
                </div>
            </div>

            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <button @click="showForm = !showForm"
                class="w-full py-3 rounded-2xl bg-[#0C78FF] text-white font-bold text-sm mb-4 active:scale-[0.99] transition">
                {{ showForm ? 'Tutup Form' : '+ Ajukan Izin' }}
            </button>

            <!-- Form -->
            <div v-if="showForm" class="rounded-2xl bg-white border border-gray-100 p-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Pengajuan</label>
                    <select v-model="form.setting_jenis_pengajuan_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]">
                        <option value="">— pilih —</option>
                        <option v-for="j in jenis" :key="j.id" :value="j.id">{{ j.nama }}<span v-if="j.sisa_kuota != null"> (sisa {{ j.sisa_kuota }})</span></option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mulai</label>
                        <input v-model="form.tanggal_mulai" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Selesai</label>
                        <input v-model="form.tanggal_selesai" type="date" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan</label>
                    <textarea v-model="form.alasan" rows="3" placeholder="Tuliskan alasan (min. 10 karakter)…"
                        class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dokumen (opsional, PDF/gambar)</label>
                    <input type="file" accept=".pdf,image/*" @change="pilihDokumen" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold" />
                </div>
                <button @click="ajukan" :disabled="saving"
                    class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm disabled:opacity-60">
                    {{ saving ? 'Mengirim…' : 'Kirim Pengajuan' }}
                </button>
            </div>

            <!-- Riwayat -->
            <div v-if="!list.length" class="pt-10 text-center text-sm text-gray-400">Belum ada pengajuan.</div>
            <ul v-else class="space-y-2.5">
                <li v-for="p in list" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-3.5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-gray-800">{{ p.jenis || p.jenis_nama || p.kategori || 'Izin' }}</p>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full capitalize" :class="statusClass(p.status)">{{ p.status_label || p.status }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ p.tanggal_mulai }} — {{ p.tanggal_selesai }}<span v-if="p.jumlah_hari"> · {{ p.jumlah_hari }} hari</span></p>
                    <p v-if="p.alasan" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ p.alasan }}</p>
                </li>
            </ul>
        </template>
    </div>
</template>
