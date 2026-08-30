<template>
    <AdminLayout title="Potongan Gaji" subtitle="Smart Payroll">
        <Head title="Potongan Gaji" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Potongan Gaji per Guru</h2>
            <p class="text-sm text-gray-400 mt-0.5">Potongan tetap per guru (voucher, simpanan, LAZISMU, pinjaman). Murni potongan gaji — tidak terkait absensi/mengajar.</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-5">
            <button @click="tab = 'guru'" :class="tabCls('guru')">Per Guru</button>
            <button @click="tab = 'jenis'" :class="tabCls('jenis')">Kelola Jenis ({{ jenisList.length }})</button>
        </div>

        <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-4">{{ msg.text }}</p>

        <!-- ══ PER GURU ══ -->
        <div v-if="tab === 'guru'">
            <input v-model="cari" placeholder="Cari guru…" class="w-full max-w-sm px-4 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 mb-4" />
            <div class="space-y-2.5">
                <div v-for="g in guruTersaring" :key="g.id" @click="bukaGuru(g)"
                    class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-4 cursor-pointer hover:border-indigo-300 transition-colors">
                    <img v-if="g.foto" :src="g.foto" class="w-11 h-11 rounded-full object-cover shrink-0" />
                    <div v-else class="w-11 h-11 rounded-full bg-indigo-100 grid place-items-center font-bold text-indigo-600 shrink-0">{{ g.nama?.charAt(0) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                        <p class="text-xs text-gray-400">{{ g.jabatan }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold" :class="g.total > 0 ? 'text-red-600' : 'text-gray-300'">{{ g.total > 0 ? '− ' + rupiah(g.total) : '—' }}</p>
                        <p class="text-[11px] text-gray-400">total potongan</p>
                    </div>
                </div>
                <p v-if="!guruTersaring.length" class="text-center text-sm text-gray-400 py-10">Tidak ada guru.</p>
            </div>
        </div>

        <!-- ══ KELOLA JENIS ══ -->
        <div v-else>
            <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-50 mb-4">
                <div v-for="j in jenisList" :key="j.id" class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ j.nama }}</p>
                        <p class="text-xs text-gray-400">{{ j.kategori_label }}<span v-if="!j.tampil_di_slip"> · tidak di slip</span></p>
                    </div>
                    <button @click="toggleJenis(j)" :class="['text-xs font-semibold px-2.5 py-1 rounded-full', j.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400']">{{ j.is_aktif ? 'Aktif' : 'Nonaktif' }}</button>
                    <button @click="editJenis(j)" class="text-xs font-semibold text-indigo-600 px-2">Edit</button>
                    <button @click="hapusJenis(j)" class="text-xs font-semibold text-red-500 px-1">Hapus</button>
                </div>
                <p v-if="!jenisList.length" class="text-center text-sm text-gray-400 py-6">Belum ada jenis potongan.</p>
            </div>

            <!-- Form tambah/edit jenis -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-sm font-bold text-gray-800 mb-3">{{ jenisForm.id ? 'Edit Jenis' : 'Tambah Jenis Potongan' }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input v-model="jenisForm.nama" placeholder="Nama (cth: Voucher An Nur Mart)" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500" />
                    <select v-model="jenisForm.kategori" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500">
                        <option value="wajib">Potongan Wajib</option>
                        <option value="simpanan">Simpanan</option>
                        <option value="pinjaman">Simpan Pinjam</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 mt-3 text-xs text-gray-600">
                    <input type="checkbox" v-model="jenisForm.tampil_di_slip" class="w-4 h-4 rounded text-indigo-600" /> Tampilkan di slip gaji
                </label>
                <div class="flex gap-2 mt-3">
                    <button @click="simpanJenis" :disabled="jenisSaving" class="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-50">{{ jenisSaving ? '…' : (jenisForm.id ? 'Simpan' : 'Tambah') }}</button>
                    <button v-if="jenisForm.id" @click="resetJenis" class="px-4 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                </div>
            </div>
        </div>

        <!-- Modal isi potongan per guru -->
        <div v-if="showGuru" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showGuru = false" />
            <div class="relative bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col shadow-xl">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900">Potongan — {{ guruAktif?.nama }}</h3>
                    <button @click="showGuru = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <div v-for="it in guruItems" :key="it.jenis_id">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ it.nama }} <span class="text-gray-300">· {{ katLabel(it.kategori) }}</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Rp</span>
                            <input v-model.number="it.nominal" type="number" min="0" placeholder="0" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-indigo-500 tabular-nums" />
                        </div>
                    </div>
                    <p v-if="!guruItems.length" class="text-sm text-gray-400 text-center py-4">Belum ada jenis potongan aktif. Tambah di tab "Kelola Jenis".</p>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex items-center gap-3">
                    <span class="text-sm text-gray-500">Total: <b class="text-red-600 tabular-nums">{{ rupiah(totalModal) }}</b></span>
                    <button @click="simpanGuru" :disabled="guruSaving" class="ml-auto px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-50">{{ guruSaving ? 'Menyimpan…' : 'Simpan' }}</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    jenis: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
})

const tab = ref('guru')
const msg = ref(null)
const cari = ref('')
const jenisList = ref([...props.jenis])
const guruList = ref([...props.guru])

const tabCls = (t) => ['px-4 py-2 rounded-xl text-sm font-semibold', tab.value === t ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600']
const katLabel = (k) => ({ wajib: 'Wajib', simpanan: 'Simpanan', pinjaman: 'Pinjaman', lainnya: 'Lainnya' }[k] ?? k)
function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

const guruTersaring = computed(() => {
    const q = cari.value.trim().toLowerCase()
    return q ? guruList.value.filter(g => g.nama.toLowerCase().includes(q)) : guruList.value
})

// ── Per guru ──
const showGuru = ref(false)
const guruAktif = ref(null)
const guruItems = ref([])
const guruSaving = ref(false)
const totalModal = computed(() => guruItems.value.reduce((s, i) => s + (Number(i.nominal) || 0), 0))

async function bukaGuru(g) {
    guruAktif.value = g; guruItems.value = []; showGuru.value = true
    try {
        const res = await window.axios.get(`/admin/smart-payroll/potongan/guru/${g.id}`)
        guruItems.value = (res.data.data?.items ?? []).map(i => ({ ...i, nominal: i.nominal || null }))
    } catch (e) { msg.value = { ok: false, text: 'Gagal memuat data guru.' } }
}
async function simpanGuru() {
    guruSaving.value = true
    try {
        const items = guruItems.value.map(i => ({ jenis_id: i.jenis_id, nominal: Number(i.nominal) || 0 }))
        const res = await window.axios.post(`/admin/smart-payroll/potongan/guru/${guruAktif.value.id}`, { items })
        const g = guruList.value.find(x => x.id === guruAktif.value.id)
        if (g) g.total = res.data.data?.total ?? 0
        showGuru.value = false
        msg.value = { ok: true, text: `Potongan ${guruAktif.value.nama} tersimpan.` }
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' } }
    finally { guruSaving.value = false }
}

// ── Kelola jenis ──
const jenisForm = ref({ id: null, nama: '', kategori: 'wajib', tampil_di_slip: true })
const jenisSaving = ref(false)
function resetJenis() { jenisForm.value = { id: null, nama: '', kategori: 'wajib', tampil_di_slip: true } }
function editJenis(j) { jenisForm.value = { id: j.id, nama: j.nama, kategori: j.kategori, tampil_di_slip: j.tampil_di_slip } }
async function simpanJenis() {
    if (!jenisForm.value.nama.trim()) { msg.value = { ok: false, text: 'Nama wajib diisi.' }; return }
    jenisSaving.value = true
    try {
        const f = jenisForm.value
        const body = { nama: f.nama.trim(), kategori: f.kategori, tampil_di_slip: f.tampil_di_slip, is_aktif: true }
        let res
        if (f.id) res = await window.axios.put(`/admin/smart-payroll/potongan/jenis/${f.id}`, body)
        else res = await window.axios.post('/admin/smart-payroll/potongan/jenis', body)
        const data = res.data.data
        if (f.id) { const i = jenisList.value.findIndex(x => x.id === f.id); if (i !== -1) jenisList.value[i] = data }
        else jenisList.value.push(data)
        jenisList.value.sort((a, b) => (a.urutan - b.urutan) || a.nama.localeCompare(b.nama))
        resetJenis()
        msg.value = { ok: true, text: 'Jenis potongan tersimpan.' }
    } catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan jenis.' } }
    finally { jenisSaving.value = false }
}
async function toggleJenis(j) {
    try { const res = await window.axios.patch(`/admin/smart-payroll/potongan/jenis/${j.id}/toggle`); j.is_aktif = res.data.data.is_aktif }
    catch (e) { msg.value = { ok: false, text: 'Gagal.' } }
}
async function hapusJenis(j) {
    if (!confirm(`Hapus "${j.nama}"? Nominal guru untuk item ini juga terhapus.`)) return
    try { await window.axios.delete(`/admin/smart-payroll/potongan/jenis/${j.id}`); jenisList.value = jenisList.value.filter(x => x.id !== j.id); msg.value = { ok: true, text: 'Dihapus.' } }
    catch (e) { msg.value = { ok: false, text: 'Gagal menghapus.' } }
}
</script>
