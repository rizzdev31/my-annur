<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import { kompresFoto } from '../foto'
import PageHeader from '../components/PageHeader.vue'

const list = ref([])
const isPetugas = ref(false)
const loading = ref(true)
const error = ref('')
const msg = ref(null)
const expand = ref(null)
const busy = ref(null)

const statusInfo = (s) => ({
    menunggu:          { t: 'Menunggu Validasi', c: 'text-amber-600 bg-amber-50' },
    dalam_pengecekan:  { t: 'Dalam Pengecekan',  c: 'text-blue-600 bg-blue-50' },
    selesai:           { t: 'Selesai',           c: 'text-emerald-600 bg-emerald-50' },
    ditolak:           { t: 'Ditolak',           c: 'text-gray-500 bg-gray-100' },
}[s] || { t: s, c: 'text-gray-500 bg-gray-100' })

async function load() {
    loading.value = true; error.value = ''
    try {
        const [st, df] = await Promise.all([api.get('/health/status'), api.get('/health')])
        isPetugas.value = !!(st.data.data?.is_petugas)
        list.value = df.data.data ?? df.data ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data kesehatan.'
    } finally { loading.value = false }
}
onMounted(load)

// ── Lapor ───────────────────────────────────────────────────────────────────
const showLapor = ref(false)
const santriQ = ref('')
const santriResults = ref([])
const santriSel = ref(null)
const deskripsi = ref('')
const foto = ref(null)
const fotoPreview = ref(null)
const saving = ref(false)
let searchTimer = null

function bukaLapor() {
    showLapor.value = true; santriQ.value = ''; santriResults.value = []; santriSel.value = null
    deskripsi.value = ''; foto.value = null; fotoPreview.value = null; msg.value = null
}
function cariSantri() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(async () => {
        try {
            const res = await api.get('/health/santri', { params: { q: santriQ.value } })
            santriResults.value = res.data.data ?? res.data ?? []
        } catch (_) {/* diamkan */}
    }, 300)
}
function pilihSantri(s) { santriSel.value = s; santriResults.value = []; santriQ.value = s.nama }
async function pilihFoto(e) { let f = e.target.files?.[0]; if (f) f = await kompresFoto(f); foto.value = f || null; fotoPreview.value = f ? URL.createObjectURL(f) : null }

async function kirimLapor() {
    msg.value = null
    if (!santriSel.value) { msg.value = { ok: false, text: 'Pilih santri dulu.' }; return }
    if (!deskripsi.value.trim()) { msg.value = { ok: false, text: 'Isi deskripsi keluhan.' }; return }
    saving.value = true
    try {
        const fd = new FormData()
        fd.append('santri_id', santriSel.value.id)
        fd.append('deskripsi_penyakit', deskripsi.value.trim())
        if (foto.value) fd.append('foto', foto.value)
        await api.post('/health/lapor', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        showLapor.value = false
        msg.value = { ok: true, text: 'Laporan terkirim, menunggu validasi Bagian Kesehatan.' }
        await load()
    } catch (e) {
        const errs = e.response?.data?.errors
        msg.value = { ok: false, text: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Gagal mengirim.') }
    } finally { saving.value = false }
}

// ── Tindak lanjut (petugas) ─────────────────────────────────────────────────
async function setujui(l) {
    busy.value = l.id; msg.value = null
    try { await api.post(`/health/${l.id}/setujui`); msg.value = { ok: true, text: 'Disetujui — wali diberi tahu.' }; await load() }
    catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal.' } }
    finally { busy.value = null }
}
async function tolak(l) {
    if (!confirm('Tolak & hapus laporan ini?')) return
    busy.value = l.id; msg.value = null
    try { await api.post(`/health/${l.id}/tolak`); msg.value = { ok: true, text: 'Laporan ditolak.' }; await load() }
    catch (e) { msg.value = { ok: false, text: e.response?.data?.message || 'Gagal.' } }
    finally { busy.value = null }
}

const cek = ref(null)
const keputusan = ref('pengecekan')
const cekCatatan = ref('')
const cekSaving = ref(false)
function bukaCek(l) { cek.value = l; keputusan.value = 'pengecekan'; cekCatatan.value = ''; msg.value = null }
async function submitCek() {
    cekSaving.value = true
    try {
        await api.post(`/health/${cek.value.id}/pengecekan`, { keputusan: keputusan.value, catatan: cekCatatan.value.trim() || null })
        cek.value = null; msg.value = { ok: true, text: 'Pemantauan tercatat.' }; await load()
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyimpan.' }
    } finally { cekSaving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Smart Health" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <button @click="bukaLapor" class="w-full py-3 rounded-2xl bg-red-500 text-white font-bold text-sm mb-4 active:scale-[0.99] transition">
                + Lapor Santri Sakit
            </button>

            <p class="text-[11px] text-gray-400 mb-3">
                {{ isPetugas ? 'Anda Petugas Kesehatan — semua kasus tampil untuk ditindak.' : 'Menampilkan laporan yang Anda buat.' }}
            </p>

            <div v-if="!list.length" class="pt-10 text-center text-sm text-gray-400">Belum ada laporan kesehatan.</div>

            <ul v-else class="space-y-3">
                <li v-for="l in list" :key="l.id" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ l.santri }}</p>
                            <p class="text-[11px] text-gray-400">oleh {{ l.pelapor }} · {{ l.tanggal }}</p>
                        </div>
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0" :class="statusInfo(l.status).c">{{ statusInfo(l.status).t }}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">{{ l.penyakit }}</p>
                    <p v-if="l.hari_pengecekan" class="text-[11px] text-blue-500 mt-1">Dipantau {{ l.hari_pengecekan }} hari</p>

                    <button v-if="l.riwayat?.length" @click="expand = expand === l.id ? null : l.id" class="mt-2 text-[11px] font-semibold text-[#0C78FF]">
                        {{ expand === l.id ? 'Sembunyikan timeline' : 'Lihat timeline' }}
                    </button>
                    <div v-if="expand === l.id" class="mt-2 pl-3 border-l-2 border-gray-100 space-y-2">
                        <div v-for="(r, i) in l.riwayat" :key="i">
                            <p class="text-[11px] font-semibold text-gray-700">{{ r.judul }}</p>
                            <p class="text-[10px] text-gray-400">{{ r.waktu }}<span v-if="r.oleh"> · {{ r.oleh }}</span></p>
                            <p v-if="r.catatan" class="text-[11px] text-gray-500">{{ r.catatan }}</p>
                        </div>
                    </div>

                    <!-- Aksi petugas -->
                    <div v-if="isPetugas && l.status === 'menunggu'" class="flex gap-2 mt-3">
                        <button @click="setujui(l)" :disabled="busy === l.id" class="flex-1 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold disabled:opacity-60">Setujui</button>
                        <button @click="tolak(l)" :disabled="busy === l.id" class="flex-1 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-bold disabled:opacity-60">Tolak</button>
                    </div>
                    <button v-else-if="isPetugas && l.status === 'dalam_pengecekan'" @click="bukaCek(l)"
                        class="w-full py-2 rounded-xl bg-[#0C78FF] text-white text-xs font-bold mt-3">Catat Pemantauan</button>
                </li>
            </ul>
        </template>

        <!-- Sheet Lapor -->
        <Transition name="pop">
            <div v-if="showLapor" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b max-h-[92vh] overflow-y-auto">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-4">Lapor Santri Sakit</h3>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Santri</label>
                    <div v-if="santriSel" class="flex items-center justify-between bg-emerald-50 rounded-xl px-3 py-2.5 mb-3">
                        <span class="text-sm font-semibold text-emerald-700">{{ santriSel.nama }}</span>
                        <button @click="santriSel = null; santriQ = ''" class="text-[11px] text-emerald-600 font-bold">Ganti</button>
                    </div>
                    <template v-else>
                        <input v-model="santriQ" @input="cariSantri" type="text" placeholder="Cari nama / NIP santri…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-2" />
                        <ul v-if="santriResults.length" class="border border-gray-100 rounded-xl mb-3 max-h-40 overflow-y-auto divide-y divide-gray-50">
                            <li v-for="s in santriResults" :key="s.id" @click="pilihSantri(s)" class="px-3 py-2 text-sm text-gray-700 active:bg-gray-50">
                                {{ s.nama }} <span class="text-[11px] text-gray-400">· {{ s.nip }}</span>
                            </li>
                        </ul>
                    </template>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Keluhan / Penyakit</label>
                    <textarea v-model="deskripsi" rows="2" placeholder="mis. demam, pusing sejak pagi…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-3"></textarea>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Foto (opsional)</label>
                    <div v-if="fotoPreview" class="mb-2"><img :src="fotoPreview" class="w-full h-36 object-cover rounded-xl" /></div>
                    <input type="file" accept="image/*" capture="environment" @change="pilihFoto" class="block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-red-50 file:text-red-500 file:text-xs file:font-semibold mb-4" />

                    <div class="flex gap-3">
                        <button @click="showLapor = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="kirimLapor" :disabled="saving" class="flex-1 py-3 rounded-xl bg-red-500 text-white font-bold text-sm disabled:opacity-60">
                            {{ saving ? 'Mengirim…' : 'Kirim Laporan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Sheet Pengecekan -->
        <Transition name="pop">
            <div v-if="cek" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">Catat Pemantauan</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ cek.santri }} — {{ cek.penyakit }}</p>

                    <label class="block text-[11px] font-medium text-gray-600 mb-1">Keputusan</label>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <button v-for="k in [['sembuh','Sembuh'],['pengecekan','Cek Lagi'],['darurat','Darurat']]" :key="k[0]" @click="keputusan = k[0]"
                            class="py-2 rounded-xl text-[11px] font-bold border" :class="keputusan === k[0] ? 'bg-[#0C78FF] text-white border-[#0C78FF]' : 'bg-white text-gray-500 border-gray-200'">{{ k[1] }}</button>
                    </div>
                    <textarea v-model="cekCatatan" rows="2" placeholder="Catatan (opsional)" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none mb-4"></textarea>

                    <div class="flex gap-3">
                        <button @click="cek = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="submitCek" :disabled="cekSaving" class="flex-1 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">
                            {{ cekSaving ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>
